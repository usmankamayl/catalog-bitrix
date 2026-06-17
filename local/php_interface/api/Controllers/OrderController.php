<?php

namespace Autoparts\Api\Controllers;

use Autoparts\Api\ApiResponse;
use Bitrix\Main\Loader;
use Bitrix\Sale\Order;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Fuser;
use Bitrix\Main\Context;
use CIBlockElement;

/**
 * POST /api/v1/order/create
 * Требует авторизации по Bearer-токену.
 *
 * Тело запроса:
 * {
 *   "items": [ {"product_id": 320, "quantity": 2}, ... ],
 *   "person_type_id": 1,
 *   "comment": "..."
 * }
 */
class OrderController
{
    const CATALOG_IBLOCK_ID = 4;
    const SITE_ID            = 's1';

    public function create(int $userId, array $body): void
    {
        if (!Loader::includeModule('sale')) {
            ApiResponse::error('MODULE_ERROR', 'Модуль sale не подключён', 500);
            return;
        }

        $items = $body['items'] ?? [];

        if (empty($items) || !is_array($items)) {
            ApiResponse::error('VALIDATION_ERROR', 'Не передан список товаров (items)', 422);
            return;
        }

        try {
            // Корзина для текущего пользователя (fUserId)
            $fUserId = Fuser::getId();
            $basket  = Basket::create(self::SITE_ID);

            $addedItems = [];

            foreach ($items as $row) {
                $productId = (int)($row['product_id'] ?? 0);
                $quantity  = (float)($row['quantity'] ?? 1);

                if ($productId <= 0 || $quantity <= 0) {
                    ApiResponse::error(
                        'VALIDATION_ERROR',
                        'Некорректный товар в списке: product_id и quantity обязательны',
                        422
                    );
                    return;
                }

                $product = $this->getProduct($productId);

                if (!$product) {
                    ApiResponse::error(
                        'PRODUCT_NOT_FOUND',
                        "Товар с ID {$productId} не найден или неактивен",
                        404
                    );
                    return;
                }

                if ($product['IN_STOCK'] < $quantity) {
                    ApiResponse::error(
                        'OUT_OF_STOCK',
                        "Недостаточно товара \"{$product['NAME']}\" на складе (в наличии: {$product['IN_STOCK']})",
                        409
                    );
                    return;
                }

                $basketItem = $basket->createItem('catalog', $productId);
                $basketItem->setFields([
                    'QUANTITY'   => $quantity,
                    'CURRENCY'   => 'RUB',
                    'PRICE'      => $product['PRICE'],
                    'BASE_PRICE' => $product['PRICE'],
                    'NAME'       => $product['NAME'],
                ]);

                $addedItems[] = [
                    'product_id' => $productId,
                    'name'       => $product['NAME'],
                    'quantity'   => $quantity,
                    'price'      => $product['PRICE'],
                ];
            }

            // Создаём заказ
            $order = Order::create(self::SITE_ID, $userId);
            $order->setBasket($basket);

            $personTypeId = (int)($body['person_type_id'] ?? 1);
            $order->setPersonTypeId($personTypeId);

            $order->setField('USER_ID', $userId);

            if (!empty($body['comment'])) {
                $order->setField('USER_DESCRIPTION', trim($body['comment']));
            }

            $saveResult = $order->save();

            if (!$saveResult->isSuccess()) {
                ApiResponse::error(
                    'ORDER_SAVE_ERROR',
                    implode('; ', $saveResult->getErrorMessages()),
                    500
                );
                return;
            }

            ApiResponse::success([
                'order_id' => $order->getId(),
                'price'    => (float)$order->getPrice(),
                'currency' => $order->getCurrency(),
                'items'    => $addedItems,
                'status'   => 'created',
            ], 201);

        } catch (\Throwable $e) {
            ApiResponse::error('ORDER_CREATE_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * Достаём товар из инфоблока для проверки наличия и цены.
     */
    private function getProduct(int $id): ?array
    {
        $res = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => self::CATALOG_IBLOCK_ID, 'ID' => $id, 'ACTIVE' => 'Y'],
            false,
            ['nTopCount' => 1],
            ['ID', 'NAME', 'PROPERTY_PRICE', 'PROPERTY_IN_STOCK']
        );

        $item = $res->GetNext();
        if (!$item) return null;

        return [
            'ID'       => (int)$item['ID'],
            'NAME'     => $item['NAME'],
            'PRICE'    => (float)$item['PROPERTY_PRICE_VALUE'],
            'IN_STOCK' => (int)$item['PROPERTY_IN_STOCK_VALUE'],
        ];
    }
}