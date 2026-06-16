#!/usr/bin/env php
<?php
/**
 * Импорт товаров из 1С (CommerceML 2)
 *
 * Запуск вручную:
 *   php /var/www/html/local/php_interface/cron/import_1c.php
 *
 * Настройка cron (каждый день в 03:00):
 *   0 3 * * * /usr/bin/php /var/www/html/local/php_interface/cron/import_1c.php >> /var/www/html/upload/1c_import/logs/cron.log 2>&1
 */

// =============================================
// ИНИЦИАЛИЗАЦИЯ БИТРИКСА
// =============================================
$_SERVER['DOCUMENT_ROOT'] = dirname(__FILE__, 4); // поднимаемся из local/php_interface/cron/
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);
define('BX_CRONTAB', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!CModule::IncludeModule('iblock')) {
    die('[FATAL] Модуль iblock не подключён' . PHP_EOL);
}

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

Loader::includeModule('iblock');

// =============================================
// НАСТРОЙКИ
// =============================================
const CATALOG_IBLOCK_ID = 4;
const BRANDS_IBLOCK_ID  = 5;
const BATCH_SIZE        = 50;   // размер пакета обработки
const XML_FILE          = '/upload/1c_import/import.xml';
const LOG_DIR           = '/upload/1c_import/logs';

// =============================================
// КЛАСС ИМПОРТЁРА
// =============================================
class Import1C
{
    private string $xmlFile;
    private string $logDir;
    private int    $iblockId;
    private int    $brandsIblockId;

    private int $countCreated    = 0;
    private int $countUpdated    = 0;
    private int $countDeactivated = 0;
    private int $countSkipped    = 0;
    private array $errors        = [];
    private float $startTime;

    // Кэш брендов: название => ID элемента
    private array $brandsCache = [];

    public function __construct(
        string $xmlFile,
        string $logDir,
        int $iblockId,
        int $brandsIblockId
    ) {
        $this->xmlFile        = $_SERVER['DOCUMENT_ROOT'] . $xmlFile;
        $this->logDir         = $_SERVER['DOCUMENT_ROOT'] . $logDir;
        $this->iblockId       = $iblockId;
        $this->brandsIblockId = $brandsIblockId;
        $this->startTime      = microtime(true);
    }

    public function run(): void
    {
        $this->log('=== Старт импорта ' . date('Y-m-d H:i:s') . ' ===');

        // Проверяем файл
        if (!file_exists($this->xmlFile)) {
            $this->logError('XML файл не найден: ' . $this->xmlFile);
            $this->finish();
            return;
        }

        // Парсим товары из XML потоково
        $xmlItems = $this->parseXml($this->xmlFile);
        $this->log('Товаров в XML: ' . count($xmlItems));

        if (empty($xmlItems)) {
            $this->log('Нет товаров для импорта');
            $this->finish();
            return;
        }

        // Загружаем кэш брендов
        $this->loadBrandsCache();

        // Загружаем существующие товары из БД (XML_ID => данные)
        $existingItems = $this->loadExistingItems();
        $this->log('Товаров в БД: ' . count($existingItems));

        // Обрабатываем пакетами
        $batches = array_chunk($xmlItems, BATCH_SIZE, true);
        $this->log('Пакетов: ' . count($batches) . ' (по ' . BATCH_SIZE . ' товаров)');

        $processedXmlIds = [];

        foreach ($batches as $batchIndex => $batch) {
            $this->log('Пакет ' . ($batchIndex + 1) . '/' . count($batches) . '...');

            foreach ($batch as $xmlItem) {
                try {
                    $article = $xmlItem['ARTICLE'];
                    $processedXmlIds[] = $article;

                    if (isset($existingItems[$article])) {
                        // Товар существует — обновляем
                        $this->updateItem($xmlItem, $existingItems[$article]);
                    } else {
                        // Новый товар — создаём
                        $this->createItem($xmlItem);
                    }
                } catch (Throwable $e) {
                    $this->logError(
                        'Ошибка обработки товара [' . ($xmlItem['ARTICLE'] ?? 'unknown') . ']: '
                        . $e->getMessage()
                    );
                }
            }

            // Небольшая пауза между пакетами чтобы не перегружать БД
            if ($batchIndex < count($batches) - 1) {
                usleep(50000); // 50ms
            }
        }

        // Деактивируем товары которых нет в выгрузке
        $this->deactivateMissing($existingItems, $processedXmlIds);

        $this->finish();
    }

    // =============================================
    // ПАРСИНГ XML (потоковый через XMLReader)
    // =============================================
    private function parseXml(string $xmlFile): array
    {
        $items = [];

        $reader = new XMLReader();
        if (!$reader->open($xmlFile)) {
            $this->logError('Не удалось открыть XML файл: ' . $xmlFile);
            return $items;
        }

        // Перематываем до первого элемента <Товар>
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'Товар') {
                // Читаем весь узел <Товар> как DOM-объект
                $node = $reader->expand();
                if (!$node) continue;

                $dom = new DOMDocument();
                $dom->appendChild($dom->importNode($node, true));
                $xml = simplexml_import_dom($dom->documentElement);

                $xmlId   = trim((string)$xml->Ид);
                $article = trim((string)$xml->Артикул);

                if (empty($xmlId) || empty($article)) {
                    $this->logError('Пропущен товар: нет Ид или Артикула');
                    $this->countSkipped++;
                    continue;
                }

                // Парсим свойства
                $props = [];
                foreach ($xml->ЗначенияСвойств->ЗначениеСвойства ?? [] as $prop) {
                    $name  = trim((string)$prop->Наименование);
                    $value = trim((string)$prop->Значение);
                    $props[$name] = $value;
                }

                $items[] = [
                    'XML_ID'      => $xmlId,
                    'NAME'        => trim((string)$xml->Наименование),
                    'ARTICLE'     => $article,
                    'DESCRIPTION' => trim((string)$xml->Описание),
                    'BRAND'       => $props['Бренд'] ?? '',
                    'PRICE'       => (float)($props['Цена'] ?? 0),
                    'IN_STOCK'    => (int)($props['НаличиеНаСкладе'] ?? 0),
                ];
            }
        }

        $reader->close();
        return $items;
    }

    // =============================================
    // ЗАГРУЗКА СУЩЕСТВУЮЩИХ ТОВАРОВ (по артикулу)
    // =============================================
    private function loadExistingItems(): array
    {
        $existing = [];

        $res = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $this->iblockId],
            false,
            false,
            ['ID', 'NAME', 'ACTIVE', 'PROPERTY_ARTICLE']
        );

        while ($item = $res->GetNext()) {
            $article = trim($item['PROPERTY_ARTICLE_VALUE']);
            if (!empty($article)) {
                $existing[$article] = [
                    'ID'     => $item['ID'],
                    'NAME'   => $item['NAME'],
                    'ACTIVE' => $item['ACTIVE'],
                ];
            }
        }

        return $existing;
    }

    // =============================================
    // ЗАГРУЗКА КЭША БРЕНДОВ
    // =============================================
    private function loadBrandsCache(): void
    {
        $res = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $this->brandsIblockId, 'ACTIVE' => 'Y'],
            false,
            false,
            ['ID', 'NAME']
        );

        while ($brand = $res->Fetch()) {
            $this->brandsCache[strtolower(trim($brand['NAME']))] = (int)$brand['ID'];
        }

        $this->log('Брендов в кэше: ' . count($this->brandsCache));
    }

    private function getBrandId(string $brandName): ?int
    {
        $key = strtolower(trim($brandName));
        return $this->brandsCache[$key] ?? null;
    }

    // =============================================
    // СОЗДАНИЕ НОВОГО ТОВАРА
    // =============================================
    private function createItem(array $item): void
    {
        $fields = $this->buildFields($item);
        $fields['ACTIVE'] = 'Y';

        $el = new CIBlockElement();
        $id = $el->Add($fields);

        if ($id) {
            $this->countCreated++;
            $this->log("  [CREATE] [{$id}] {$item['NAME']} (арт: {$item['ARTICLE']})");
        } else {
            throw new RuntimeException('CIBlockElement::Add failed: ' . $el->LAST_ERROR);
        }
    }

    // =============================================
    // ОБНОВЛЕНИЕ СУЩЕСТВУЮЩЕГО ТОВАРА
    // =============================================
    private function updateItem(array $xmlItem, array $dbItem): void
    {
        $fields = $this->buildFields($xmlItem);

        // Если товар был деактивирован — реактивируем
        if ($dbItem['ACTIVE'] === 'N') {
            $fields['ACTIVE'] = 'Y';
        }

        $el = new CIBlockElement();
        $result = $el->Update($dbItem['ID'], $fields);

        if ($result) {
            $this->countUpdated++;
            $this->log("  [UPDATE] [{$dbItem['ID']}] {$xmlItem['NAME']}");
        } else {
            throw new RuntimeException('CIBlockElement::Update failed: ' . $el->LAST_ERROR);
        }
    }

    // =============================================
    // ДЕАКТИВАЦИЯ ОТСУТСТВУЮЩИХ ТОВАРОВ
    // =============================================
    private function deactivateMissing(array $existingItems, array $processedXmlIds): void
    {
        $processedSet = array_flip($processedXmlIds);

        foreach ($existingItems as $xmlId => $dbItem) {
            if (!isset($processedSet[$xmlId]) && $dbItem['ACTIVE'] === 'Y') {
                $el = new CIBlockElement();
                $result = $el->Update($dbItem['ID'], ['ACTIVE' => 'N']);

                if ($result) {
                    $this->countDeactivated++;
                    $this->log("  [DEACTIVATE] [{$dbItem['ID']}] XML_ID: {$xmlId}");
                } else {
                    $this->logError(
                        'Не удалось деактивировать элемент ID=' . $dbItem['ID']
                        . ': ' . $el->LAST_ERROR
                    );
                }
            }
        }
    }

    // =============================================
    // ФОРМИРОВАНИЕ ПОЛЕЙ ЭЛЕМЕНТА
    // =============================================
    private function buildFields(array $item): array
    {
        $code = $this->makeCode($item['ARTICLE']);

        $fields = [
            'IBLOCK_ID'    => $this->iblockId,
            'NAME'         => $item['NAME'],
            'XML_ID'       => $item['XML_ID'],
            'CODE'         => $code,
            'DETAIL_TEXT'  => $item['DESCRIPTION'],
            'PREVIEW_TEXT' => $item['DESCRIPTION'],
            'PROPERTY_VALUES' => [
                'ARTICLE'  => $item['ARTICLE'],
                'PRICE'    => $item['PRICE'],
                'IN_STOCK' => $item['IN_STOCK'],
            ],
        ];

        // Привязываем бренд если нашли в справочнике
        if (!empty($item['BRAND'])) {
            $brandId = $this->getBrandId($item['BRAND']);
            if ($brandId) {
                $fields['PROPERTY_VALUES']['BRAND'] = $brandId;
            } else {
                $this->logError(
                    'Бренд не найден в справочнике: "' . $item['BRAND']
                    . '" (товар: ' . $item['ARTICLE'] . ')'
                );
            }
        }

        return $fields;
    }

    // =============================================
    // ГЕНЕРАЦИЯ СИМВОЛЬНОГО КОДА
    // =============================================
    private function makeCode(string $article): string
    {
        $code = strtolower($article);
        $code = preg_replace('/[^a-z0-9]+/', '-', $code);
        $code = trim($code, '-');
        return $code ?: 'item-' . time();
    }

    // =============================================
    // ЛОГИРОВАНИЕ
    // =============================================
    private function log(string $message): void
    {
        $line = '[' . date('H:i:s') . '] ' . $message;
        echo $line . PHP_EOL;
        $this->writeLog($line);
    }

    private function logError(string $message): void
    {
        $this->errors[] = $message;
        $this->log('[ERROR] ' . $message);
    }

    private function writeLog(string $line): void
    {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }

        $logFile = $this->logDir . '/import_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function finish(): void
    {
        $elapsed = round(microtime(true) - $this->startTime, 2);

        $this->log('');
        $this->log('=== Результаты импорта ===');
        $this->log('Создано:       ' . $this->countCreated);
        $this->log('Обновлено:     ' . $this->countUpdated);
        $this->log('Деактивировано:' . $this->countDeactivated);
        $this->log('Пропущено:     ' . $this->countSkipped);
        $this->log('Ошибок:        ' . count($this->errors));
        $this->log('Время:         ' . $elapsed . ' сек.');

        if (!empty($this->errors)) {
            $this->log('');
            $this->log('Список ошибок:');
            foreach ($this->errors as $i => $err) {
                $this->log('  ' . ($i + 1) . '. ' . $err);
            }
        }

        $this->log('=== Конец импорта ' . date('Y-m-d H:i:s') . ' ===');
        $this->log('');
    }
}

// =============================================
// ТОЧКА ВХОДА
// =============================================
$importer = new Import1C(
    XML_FILE,
    LOG_DIR,
    CATALOG_IBLOCK_ID,
    BRANDS_IBLOCK_ID
);

$importer->run();
