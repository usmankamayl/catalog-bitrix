<?php
/**
 * Скрипт наполнения демо-данными
 * Создаёт 3 бренда и 15 товаров в инфоблоках
 *
 * Запуск: поместить в корень сайта и открыть в браузере
 * После выполнения — удалить файл с сервера!
 */

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!CModule::IncludeModule('iblock')) {
    die('Модуль iblock не подключён');
}

// =============================================
// НАСТРОЙКИ
// =============================================
$BRANDS_IBLOCK_ID   = 5; // ID инфоблока «Бренды»
$CATALOG_IBLOCK_ID  = 4; // ID инфоблока «Каталог автозапчастей»

echo '<meta charset="utf-8">';
echo '<h2>Наполнение демо-данными</h2>';
echo '<pre>';

// =============================================
// ШАГ 1: Получаем ID разделов (модели авто)
// =============================================
$sections = [];
$rsSections = CIBlockSection::GetList(
    ['SORT' => 'ASC'],
    ['IBLOCK_ID' => $CATALOG_IBLOCK_ID, 'ACTIVE' => 'Y'],
    false,
    ['ID', 'NAME']
);
while ($sec = $rsSections->Fetch()) {
    $sections[] = $sec['ID'];
    echo "Раздел найден: [{$sec['ID']}] {$sec['NAME']}\n";
}

if (count($sections) < 3) {
    die('Ошибка: создайте минимум 3 раздела (модели авто) в инфоблоке каталога');
}

// =============================================
// ШАГ 2: Создаём бренды
// =============================================
$brandsData = [
    [
        'NAME'    => 'Bosch',
        'COUNTRY' => 'Германия',
    ],
    [
        'NAME'    => 'NGK',
        'COUNTRY' => 'Япония',
    ],
    [
        'NAME'    => 'Febest',
        'COUNTRY' => 'Япония',
    ],
];

$brandIds = [];
$el = new CIBlockElement();

echo "\n--- Создаём бренды ---\n";
foreach ($brandsData as $brand) {
    // Проверяем, не существует ли уже
    $rsExist = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $BRANDS_IBLOCK_ID, 'NAME' => $brand['NAME']],
        false,
        ['nTopCount' => 1],
        ['ID', 'NAME']
    );
    if ($exist = $rsExist->Fetch()) {
        $brandIds[$brand['NAME']] = $exist['ID'];
        echo "Бренд уже существует: [{$exist['ID']}] {$brand['NAME']}\n";
        continue;
    }

    $fields = [
        'IBLOCK_ID'         => $BRANDS_IBLOCK_ID,
        'NAME'              => $brand['NAME'],
        'ACTIVE'            => 'Y',
        'PROPERTY_VALUES'   => [
            'COUNTRY' => $brand['COUNTRY'],
        ],
    ];

    $id = $el->Add($fields);
    if ($id) {
        $brandIds[$brand['NAME']] = $id;
        echo "Создан бренд: [{$id}] {$brand['NAME']} ({$brand['COUNTRY']})\n";
    } else {
        echo "Ошибка создания бренда {$brand['NAME']}: " . $el->LAST_ERROR . "\n";
    }
}

if (count($brandIds) < 3) {
    die('Ошибка: не удалось создать бренды');
}

$boschId  = $brandIds['Bosch'];
$ngkId    = $brandIds['NGK'];
$febbestId = $brandIds['Febest'];

// =============================================
// ШАГ 3: Создаём 15 товаров
// =============================================
$products = [
    // Bosch — 5 товаров
    [
        'NAME'          => 'Фильтр масляный Bosch P7053',
        'SECTION_ID'    => $sections[0],
        'BRAND'         => $boschId,
        'ARTICLE'       => 'F026407053',
        'COMPAT'        => [$sections[0], $sections[1]],
        'PRICE'         => 650,
        'IN_STOCK'      => 15,
        'PREVIEW_TEXT'  => 'Масляный фильтр высокого качества для легковых автомобилей.',
    ],
    [
        'NAME'          => 'Фильтр воздушный Bosch S0243',
        'SECTION_ID'    => $sections[0],
        'BRAND'         => $boschId,
        'ARTICLE'       => 'F026400243',
        'COMPAT'        => [$sections[0], $sections[2]],
        'PRICE'         => 890,
        'IN_STOCK'      => 8,
        'PREVIEW_TEXT'  => 'Воздушный фильтр с высокой степенью очистки.',
    ],
    [
        'NAME'          => 'Свеча зажигания Bosch Super Plus',
        'SECTION_ID'    => $sections[1],
        'BRAND'         => $boschId,
        'ARTICLE'       => '0242235666',
        'COMPAT'        => [$sections[0], $sections[1], $sections[3]],
        'PRICE'         => 320,
        'IN_STOCK'      => 42,
        'PREVIEW_TEXT'  => 'Свеча зажигания с никелевым электродом.',
    ],
    [
        'NAME'          => 'Тормозные колодки Bosch BP456',
        'SECTION_ID'    => $sections[1],
        'BRAND'         => $boschId,
        'ARTICLE'       => '0986424456',
        'COMPAT'        => [$sections[1], $sections[4]],
        'PRICE'         => 2100,
        'IN_STOCK'      => 6,
        'PREVIEW_TEXT'  => 'Передние тормозные колодки с низким уровнем шума.',
    ],
    [
        'NAME'          => 'Генератор Bosch AL0733X',
        'SECTION_ID'    => $sections[2],
        'BRAND'         => $boschId,
        'ARTICLE'       => '0986AL0733X',
        'COMPAT'        => [$sections[2]],
        'PRICE'         => 12500,
        'IN_STOCK'      => 2,
        'PREVIEW_TEXT'  => 'Восстановленный генератор Bosch.',
    ],

    // NGK — 5 товаров
    [
        'NAME'          => 'Свеча зажигания NGK BKR6E',
        'SECTION_ID'    => $sections[0],
        'BRAND'         => $ngkId,
        'ARTICLE'       => 'BKR6E-11',
        'COMPAT'        => [$sections[0], $sections[3], $sections[4]],
        'PRICE'         => 280,
        'IN_STOCK'      => 60,
        'PREVIEW_TEXT'  => 'Стандартная свеча зажигания NGK с медным электродом.',
    ],
    [
        'NAME'          => 'Свеча зажигания NGK Iridium IX',
        'SECTION_ID'    => $sections[0],
        'BRAND'         => $ngkId,
        'ARTICLE'       => 'BKR6EIX-11',
        'COMPAT'        => [$sections[0], $sections[1]],
        'PRICE'         => 780,
        'IN_STOCK'      => 25,
        'PREVIEW_TEXT'  => 'Иридиевая свеча зажигания с улучшенным поджигом смеси.',
    ],
    [
        'NAME'          => 'Провода зажигания NGK RC-ZE61',
        'SECTION_ID'    => $sections[1],
        'BRAND'         => $ngkId,
        'ARTICLE'       => 'RC-ZE61',
        'COMPAT'        => [$sections[1]],
        'PRICE'         => 1850,
        'IN_STOCK'      => 4,
        'PREVIEW_TEXT'  => 'Комплект высоковольтных проводов зажигания.',
    ],
    [
        'NAME'          => 'Лямбда-зонд NGK OZA659-EE1',
        'SECTION_ID'    => $sections[2],
        'BRAND'         => $ngkId,
        'ARTICLE'       => 'OZA659-EE1',
        'COMPAT'        => [$sections[2], $sections[3]],
        'PRICE'         => 3200,
        'IN_STOCK'      => 7,
        'PREVIEW_TEXT'  => 'Кислородный датчик (лямбда-зонд) NGK.',
    ],
    [
        'NAME'          => 'Датчик температуры NGK EF0038',
        'SECTION_ID'    => $sections[3],
        'BRAND'         => $ngkId,
        'ARTICLE'       => 'EF0038',
        'COMPAT'        => [$sections[3], $sections[4]],
        'PRICE'         => 1100,
        'IN_STOCK'      => 11,
        'PREVIEW_TEXT'  => 'Датчик температуры охлаждающей жидкости.',
    ],

    // Febest — 5 товаров
    [
        'NAME'          => 'Сайлентблок Febest TAB-245',
        'SECTION_ID'    => $sections[0],
        'BRAND'         => $febbestId,
        'ARTICLE'       => 'TAB-245',
        'COMPAT'        => [$sections[0]],
        'PRICE'         => 480,
        'IN_STOCK'      => 18,
        'PREVIEW_TEXT'  => 'Сайлентблок переднего рычага подвески.',
    ],
    [
        'NAME'          => 'Шаровая опора Febest 0220-ACA30',
        'SECTION_ID'    => $sections[1],
        'BRAND'         => $febbestId,
        'ARTICLE'       => '0220-ACA30',
        'COMPAT'        => [$sections[0], $sections[1]],
        'PRICE'         => 920,
        'IN_STOCK'      => 9,
        'PREVIEW_TEXT'  => 'Шаровая опора нижнего рычага передней подвески.',
    ],
    [
        'NAME'          => 'ШРУС наружный Febest 0110-CA4LH',
        'SECTION_ID'    => $sections[2],
        'BRAND'         => $febbestId,
        'ARTICLE'       => '0110-CA4LH',
        'COMPAT'        => [$sections[2], $sections[4]],
        'PRICE'         => 3800,
        'IN_STOCK'      => 3,
        'PREVIEW_TEXT'  => 'Наружный ШРУС левый в сборе.',
    ],
    [
        'NAME'          => 'Пыльник рулевой рейки Febest MCTS-001',
        'SECTION_ID'    => $sections[3],
        'BRAND'         => $febbestId,
        'ARTICLE'       => 'MCTS-001',
        'COMPAT'        => [$sections[3]],
        'PRICE'         => 350,
        'IN_STOCK'      => 22,
        'PREVIEW_TEXT'  => 'Пыльник рулевой рейки левый/правый.',
    ],
    [
        'NAME'          => 'Подшипник ступицы Febest DAC42800042',
        'SECTION_ID'    => $sections[4],
        'BRAND'         => $febbestId,
        'ARTICLE'       => 'DAC42800042',
        'COMPAT'        => [$sections[1], $sections[4]],
        'PRICE'         => 1650,
        'IN_STOCK'      => 0,
        'PREVIEW_TEXT'  => 'Подшипник передней ступицы.',
    ],
];

echo "\n--- Создаём товары ---\n";
foreach ($products as $product) {
    // Проверяем дубликат
    $rsExist = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $CATALOG_IBLOCK_ID, 'NAME' => $product['NAME']],
        false,
        ['nTopCount' => 1],
        ['ID', 'NAME']
    );
    if ($exist = $rsExist->Fetch()) {
        echo "Уже существует: [{$exist['ID']}] {$product['NAME']}\n";
        continue;
    }

    $fields = [
        'IBLOCK_ID'      => $CATALOG_IBLOCK_ID,
        'IBLOCK_SECTION_ID' => $product['SECTION_ID'],
        'NAME'           => $product['NAME'],
        'ACTIVE'         => 'Y',
        'PREVIEW_TEXT'   => $product['PREVIEW_TEXT'],
        'DETAIL_TEXT'    => $product['PREVIEW_TEXT'],
        'PROPERTY_VALUES' => [
            'BRAND'         => $product['BRAND'],
            'ARTICLE'       => $product['ARTICLE'],
            'COMPATIBILITY' => $product['COMPAT'],
            'PRICE'         => $product['PRICE'],
            'IN_STOCK'      => $product['IN_STOCK'],
        ],
    ];

    $id = $el->Add($fields);
    if ($id) {
        echo "Создан товар: [{$id}] {$product['NAME']} | Цена: {$product['PRICE']} ₽ | Склад: {$product['IN_STOCK']}\n";
    } else {
        echo "Ошибка: {$product['NAME']} — " . $el->LAST_ERROR . "\n";
    }
}

echo "\n✅ Готово! Удалите этот файл с сервера.\n";
echo '</pre>';
