<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;

class AutopartsCatalogDetailComponent extends CBitrixComponent
{
    const CATALOG_IBLOCK_ID = 4;
    const BRANDS_IBLOCK_ID  = 5;

    public function onPrepareComponentParams($params): array
    {
        $params['IBLOCK_ID']        = (int)($params['IBLOCK_ID'] ?? self::CATALOG_IBLOCK_ID);
        $params['BRANDS_IBLOCK_ID'] = (int)($params['BRANDS_IBLOCK_ID'] ?? self::BRANDS_IBLOCK_ID);
        $params['ELEMENT_ID']       = (int)($params['ELEMENT_ID'] ?? $_GET['ID'] ?? 0);
        $params['CACHE_TIME']       = (int)($params['CACHE_TIME'] ?? 3600);
        return $params;
    }

    public function executeComponent(): void
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль iblock не установлен');
            return;
        }

        if (!$this->arParams['ELEMENT_ID']) {
            ShowError('Элемент не найден');
            return;
        }

        $cacheId = 'detail_' . $this->arParams['ELEMENT_ID'];

        if ($this->startResultCache($this->arParams['CACHE_TIME'], $cacheId)) {
            $item = $this->getItem($this->arParams['ELEMENT_ID']);

            if (!$item) {
                $this->abortResultCache();
                ShowError('Товар не найден');
                return;
            }

            $this->arResult['ITEM']    = $item;
            $this->arResult['SIMILAR'] = $this->getSimilar($item);

            $this->SetResultCacheKeys(['ITEM', 'SIMILAR']);
            $this->includeComponentTemplate();
            $this->endResultCache();
        }
    }

    private function getItem(int $id): ?array
    {
        $res = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $this->arParams['IBLOCK_ID'], 'ID' => $id, 'ACTIVE' => 'Y'],
            false,
            ['nTopCount' => 1],
            ['ID', 'NAME', 'CODE', 'DETAIL_TEXT', 'PREVIEW_PICTURE', 'DETAIL_PICTURE',
             'PROPERTY_BRAND', 'PROPERTY_ARTICLE', 'PROPERTY_PRICE',
             'PROPERTY_IN_STOCK', 'PROPERTY_GALLERY', 'PROPERTY_COMPATIBILITY']
        );

        $item = $res->GetNext();
        if (!$item) return null;

        // Название бренда
        $item['BRAND_NAME'] = '';
        $item['BRAND_ID']   = 0;
        if (!empty($item['PROPERTY_BRAND_VALUE'])) {
            $brandEl = CIBlockElement::GetByID($item['PROPERTY_BRAND_VALUE'])->GetNext();
            if ($brandEl) {
                $item['BRAND_NAME'] = $brandEl['NAME'];
                $item['BRAND_ID']   = (int)$brandEl['ID'];
            }
        }

        // Фотогалерея
        $item['GALLERY_IMAGES'] = [];

        // Добавляем основное фото первым
        if (!empty($item['PREVIEW_PICTURE'])) {
            $img = CFile::GetFileArray($item['PREVIEW_PICTURE']);
            if ($img) $item['GALLERY_IMAGES'][] = $img['SRC'];
        }
        if (!empty($item['DETAIL_PICTURE']) && $item['DETAIL_PICTURE'] != $item['PREVIEW_PICTURE']) {
            $img = CFile::GetFileArray($item['DETAIL_PICTURE']);
            if ($img) $item['GALLERY_IMAGES'][] = $img['SRC'];
        }

        // Фото из галереи
        if (!empty($item['PROPERTY_GALLERY_VALUE'])) {
            $gallery = is_array($item['PROPERTY_GALLERY_VALUE'])
                ? $item['PROPERTY_GALLERY_VALUE']
                : [$item['PROPERTY_GALLERY_VALUE']];

            foreach ($gallery as $fileId) {
                if (!$fileId) continue;
                $img = CFile::GetFileArray($fileId);
                if ($img) $item['GALLERY_IMAGES'][] = $img['SRC'];
            }
        }

        // Совместимость — получаем названия разделов
        $item['COMPATIBILITY_NAMES'] = [];
        if (!empty($item['PROPERTY_COMPATIBILITY_VALUE'])) {
            $compat = is_array($item['PROPERTY_COMPATIBILITY_VALUE'])
                ? $item['PROPERTY_COMPATIBILITY_VALUE']
                : [$item['PROPERTY_COMPATIBILITY_VALUE']];

            foreach ($compat as $sectionId) {
                if (!$sectionId) continue;
                $sec = CIBlockSection::GetByID($sectionId)->GetNext();
                if ($sec) {
                    $item['COMPATIBILITY_NAMES'][] = $sec['NAME'];
                }
            }
        }

        return $item;
    }

    private function getSimilar(array $item): array
    {
        $similar = [];

        if (!$item['BRAND_ID']) return $similar;

        $res = CIBlockElement::GetList(
            ['RAND' => 'ASC'],
            [
                'IBLOCK_ID'      => $this->arParams['IBLOCK_ID'],
                'ACTIVE'         => 'Y',
                'PROPERTY_BRAND' => $item['BRAND_ID'],
                '!ID'            => $item['ID'],
            ],
            false,
            ['nTopCount' => 6],
            ['ID', 'NAME', 'CODE', 'PREVIEW_PICTURE', 'DETAIL_PAGE_URL',
             'PROPERTY_PRICE', 'PROPERTY_IN_STOCK']
        );

        while ($el = $res->GetNext()) {
            $similar[] = $el;
        }

        return $similar;
    }
}
