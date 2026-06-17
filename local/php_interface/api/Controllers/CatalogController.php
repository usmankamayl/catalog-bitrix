<?php

namespace Autoparts\Api\Controllers;

use Autoparts\Api\ApiResponse;
use CIBlockElement;
use CFile;

/**
 * GET /api/v1/catalog/list?page=1&limit=20&brand=317
 * GET /api/v1/catalog/{id}
 */
class CatalogController
{
    const CATALOG_IBLOCK_ID = 4;
    const BRANDS_IBLOCK_ID  = 5;
    const DEFAULT_LIMIT     = 20;
    const MAX_LIMIT         = 100;

    public function list(array $query): void
    {
        $page  = max(1, (int)($query['page'] ?? 1));
        $limit = (int)($query['limit'] ?? self::DEFAULT_LIMIT);
        $limit = $limit > 0 ? min($limit, self::MAX_LIMIT) : self::DEFAULT_LIMIT;

        $filter = [
            'IBLOCK_ID' => self::CATALOG_IBLOCK_ID,
            'ACTIVE'    => 'Y',
        ];

        if (!empty($query['brand']) && is_numeric($query['brand'])) {
            $filter['PROPERTY_BRAND'] = (int)$query['brand'];
        }

        $navParams = [
            'nPageSize' => $limit,
            'iNumPage'  => $page,
        ];

        $res = CIBlockElement::GetList(
            ['SORT' => 'ASC'],
            $filter,
            false,
            $navParams,
            ['ID', 'NAME', 'CODE', 'PREVIEW_PICTURE', 'PROPERTY_BRAND',
             'PROPERTY_ARTICLE', 'PROPERTY_PRICE', 'PROPERTY_IN_STOCK']
        );

        $items = [];
        while ($item = $res->GetNext()) {
            $items[] = $this->formatListItem($item);
        }

        // Общее количество для пагинации (отдельный запрос с тем же фильтром, без выборки)
        $totalRes = CIBlockElement::GetList(
            [],
            $filter,
            false,
            false,
            ['ID']
        );
        $total = $totalRes->SelectedRowsCount();

        ApiResponse::success([
            'items' => $items,
            'pagination' => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => (int)ceil($total / $limit),
            ],
        ]);
    }

    public function detail(int $id): void
    {
        $res = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => self::CATALOG_IBLOCK_ID, 'ID' => $id, 'ACTIVE' => 'Y'],
            false,
            ['nTopCount' => 1],
            ['ID', 'NAME', 'CODE', 'DETAIL_TEXT', 'PREVIEW_PICTURE', 'DETAIL_PICTURE',
             'PROPERTY_BRAND', 'PROPERTY_ARTICLE', 'PROPERTY_PRICE',
             'PROPERTY_IN_STOCK', 'PROPERTY_GALLERY', 'PROPERTY_COMPATIBILITY']
        );

        $item = $res->GetNext();

        if (!$item) {
            ApiResponse::error('NOT_FOUND', 'Товар не найден', 404);
            return;
        }

        ApiResponse::success($this->formatDetailItem($item));
    }

    private function formatListItem(array $item): array
    {
        return [
            'id'       => (int)$item['ID'],
            'name'     => $item['NAME'],
            'article'  => $item['PROPERTY_ARTICLE_VALUE'],
            'price'    => (float)$item['PROPERTY_PRICE_VALUE'],
            'in_stock' => (int)$item['PROPERTY_IN_STOCK_VALUE'],
            'brand'    => $this->getBrandInfo($item['PROPERTY_BRAND_VALUE'] ?? null),
            'image'    => $this->getImageUrl($item['PREVIEW_PICTURE'] ?? null),
        ];
    }

    private function formatDetailItem(array $item): array
    {
        $gallery = [];
        $galleryValues = $item['PROPERTY_GALLERY_VALUE'] ?? [];
        $galleryValues = is_array($galleryValues) ? $galleryValues : [$galleryValues];

        foreach ($galleryValues as $fileId) {
            $url = $this->getImageUrl($fileId);
            if ($url) $gallery[] = $url;
        }

        $compatibility = [];
        $compatValues = $item['PROPERTY_COMPATIBILITY_VALUE'] ?? [];
        $compatValues = is_array($compatValues) ? $compatValues : [$compatValues];

        foreach ($compatValues as $sectionId) {
            if (!$sectionId) continue;
            $section = \CIBlockSection::GetByID($sectionId)->GetNext();
            if ($section) {
                $compatibility[] = [
                    'id'   => (int)$section['ID'],
                    'name' => $section['NAME'],
                ];
            }
        }

        return [
            'id'            => (int)$item['ID'],
            'name'          => $item['NAME'],
            'article'       => $item['PROPERTY_ARTICLE_VALUE'],
            'description'   => $item['DETAIL_TEXT'],
            'price'         => (float)$item['PROPERTY_PRICE_VALUE'],
            'in_stock'      => (int)$item['PROPERTY_IN_STOCK_VALUE'],
            'brand'         => $this->getBrandInfo($item['PROPERTY_BRAND_VALUE'] ?? null),
            'gallery'       => $gallery,
            'compatibility' => $compatibility,
        ];
    }

    private function getBrandInfo($brandId): ?array
    {
        if (!$brandId) return null;

        $brand = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => self::BRANDS_IBLOCK_ID, 'ID' => $brandId],
            false,
            ['nTopCount' => 1],
            ['ID', 'NAME']
        )->GetNext();

        if (!$brand) return null;

        return [
            'id'   => (int)$brand['ID'],
            'name' => $brand['NAME'],
        ];
    }

    private function getImageUrl($fileId): ?string
    {
        if (!$fileId) return null;
        $file = CFile::GetFileArray($fileId);
        return $file ? $file['SRC'] : null;
    }
}
