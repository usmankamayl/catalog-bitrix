<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

class AutopartsCatalogComponent extends CBitrixComponent
{
    // ID инфоблоков — можно переопределить через параметры компонента
    const CATALOG_IBLOCK_ID = 4;
    const BRANDS_IBLOCK_ID  = 5;

    public function onPrepareComponentParams($params): array
    {
        $params['IBLOCK_ID']        = (int)($params['IBLOCK_ID'] ?? self::CATALOG_IBLOCK_ID);
        $params['BRANDS_IBLOCK_ID'] = (int)($params['BRANDS_IBLOCK_ID'] ?? self::BRANDS_IBLOCK_ID);
        $params['CACHE_TIME']       = (int)($params['CACHE_TIME'] ?? 3600);
        $params['PAGE_SIZE']        = (int)($params['PAGE_SIZE'] ?? 12);
        return $params;
    }

    public function executeComponent(): void
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль iblock не установлен');
            return;
        }

        // Параметры фильтра из GET
        $filter = $this->buildFilter();

        // Кэшируем с учётом фильтра
        $cacheId = md5(serialize($filter) . ($this->arParams['PAGE_SIZE']) . ($_GET['PAGEN_1'] ?? 1));

        if ($this->startResultCache($this->arParams['CACHE_TIME'], $cacheId)) {
            $this->arResult['ITEMS']  = $this->getItems($filter);
            $this->arResult['BRANDS'] = $this->getBrands();
            $this->arResult['FILTER'] = $filter;
            $this->arResult['IBLOCK_ID'] = $this->arParams['IBLOCK_ID'];

            // Теги кэша — сбрасываем при изменении инфоблока
            $this->SetResultCacheKeys(['ITEMS', 'BRANDS', 'FILTER']);

            $this->includeComponentTemplate();
            $this->endResultCache();
        }
    }

    private function buildFilter(): array
    {
        $filter = [
            'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
            'ACTIVE'    => 'Y',
        ];

        // Фильтр по бренду
        if (!empty($_GET['BRAND']) && is_numeric($_GET['BRAND'])) {
            $filter['PROPERTY_BRAND'] = (int)$_GET['BRAND'];
        }

        // Фильтр по наличию
        if (!empty($_GET['IN_STOCK'])) {
            $filter['>PROPERTY_IN_STOCK'] = 0;
        }

        // Фильтр по цене (min)
        if (!empty($_GET['PRICE_FROM']) && is_numeric($_GET['PRICE_FROM'])) {
            $filter['>=PROPERTY_PRICE'] = (float)$_GET['PRICE_FROM'];
        }

        // Фильтр по цене (max)
        if (!empty($_GET['PRICE_TO']) && is_numeric($_GET['PRICE_TO'])) {
            $filter['<=PROPERTY_PRICE'] = (float)$_GET['PRICE_TO'];
        }

        return $filter;
    }

    private function getItems(array $filter): array
    {
        $items = [];

        $res = CIBlockElement::GetList(
            ['SORT' => 'ASC'],
            $filter,
            false,
            ['nPageSize' => $this->arParams['PAGE_SIZE'], 'iNumPage' => (int)($_GET['PAGEN_1'] ?? 1)],
            ['ID', 'NAME', 'CODE', 'PREVIEW_TEXT', 'PREVIEW_PICTURE', 'DETAIL_PAGE_URL',
             'PROPERTY_BRAND', 'PROPERTY_ARTICLE', 'PROPERTY_PRICE', 'PROPERTY_IN_STOCK']
        );

        while ($item = $res->GetNext()) {
            // Название бренда
            if (!empty($item['PROPERTY_BRAND_VALUE'])) {
                $brandEl = CIBlockElement::GetByID($item['PROPERTY_BRAND_VALUE'])->GetNext();
                $item['BRAND_NAME'] = $brandEl ? $brandEl['NAME'] : '';
            } else {
                $item['BRAND_NAME'] = '';
            }

            // Переопределяем URL детальной страницы
            $item['DETAIL_PAGE_URL'] = '/autoparts_detail.php?ID=' . $item['ID'];

            $items[] = $item;
        }

        return $items;
    }

    private function getBrands(): array
    {
        $brands = [];

        $res = CIBlockElement::GetList(
            ['NAME' => 'ASC'],
            ['IBLOCK_ID' => $this->arParams['BRANDS_IBLOCK_ID'], 'ACTIVE' => 'Y'],
            false,
            false,
            ['ID', 'NAME']
        );

        while ($brand = $res->GetNext()) {
            $brands[] = $brand;
        }

        return $brands;
    }
}
