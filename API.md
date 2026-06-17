# REST API — АвтоЗапчасти

Собственная реализация REST API для мобильного приложения (без `bitrix:rest`).

Базовый URL: `http://dev-wordpress.kamaylaev.ru/api/v1`

---

## Формат ответа

Все ответы — в формате JSON.

**Успех:**
```json
{
    "success": true,
    "data": { ... }
}
```

**Ошибка:**
```json
{
    "success": false,
    "error": {
        "code": "ERROR_CODE",
        "message": "Текст ошибки"
    }
}
```

### Коды ошибок

| Код | HTTP статус | Когда возникает |
|-----|-------------|------------------|
| `VALIDATION_ERROR` | 422 | Не переданы обязательные поля |
| `INVALID_CREDENTIALS` | 401 | Неверный логин или пароль |
| `UNAUTHORIZED` | 401 | Токен не передан, недействителен или просрочен |
| `NOT_FOUND` | 404 | Товар / эндпоинт не найден |
| `PRODUCT_NOT_FOUND` | 404 | Товар из заказа не найден |
| `OUT_OF_STOCK` | 409 | Недостаточно товара на складе |
| `ORDER_SAVE_ERROR` | 500 | Ошибка модуля sale при сохранении заказа |
| `MODULE_ERROR` | 500 | Не подключён нужный модуль Битрикса |
| `INTERNAL_ERROR` | 500 | Непредвиденная ошибка сервера |

---

## Авторизация

API использует **Bearer-токены**. Токен выдаётся на 24 часа (`ttl: 86400` секунд) и передаётся в заголовке:

```
Authorization: Bearer <токен>
```

Хранилище токенов — собственная таблица `autoparts_api_token` (создаётся автоматически при первом обращении).

---

## POST /api/v1/auth/token

Получение токена по логину и паролю пользователя Битрикса.

**Авторизация:** не требуется

### Запрос

```bash
curl -X POST http://dev-wordpress.kamaylaev.ru/api/v1/auth/token \
  -H "Content-Type: application/json" \
  -d '{
    "login": "admin",
    "password": "ваш_пароль"
  }'
```

### Успешный ответ (200)

```json
{
    "success": true,
    "data": {
        "user_id": 1,
        "token": "e4263068a428e2f0e8050bdcfd350f74d13ab15bec8afba6f50580e5869985d9",
        "token_type": "Bearer",
        "expires_at": "18.06.2026 12:12:45",
        "ttl": 86400
    }
}
```

### Ошибка — неверные данные (401)

```json
{
    "success": false,
    "error": {
        "code": "INVALID_CREDENTIALS",
        "message": "Неверный логин или пароль"
    }
}
```

---

## GET /api/v1/catalog/list

Список товаров с пагинацией и фильтром по бренду.

**Авторизация:** не требуется

### Параметры query-string

| Параметр | Тип | Обязательный | По умолчанию | Описание |
|----------|-----|--------------|--------------|----------|
| `page` | int | нет | 1 | Номер страницы |
| `limit` | int | нет | 20 (макс. 100) | Кол-во товаров на странице |
| `brand` | int | нет | — | ID бренда для фильтра |

### Запрос — список с пагинацией

```bash
curl "http://dev-wordpress.kamaylaev.ru/api/v1/catalog/list?page=1&limit=5"
```

### Запрос — фильтр по бренду

```bash
curl "http://dev-wordpress.kamaylaev.ru/api/v1/catalog/list?brand=317&limit=3"
```

### Успешный ответ (200)

```json
{
    "success": true,
    "data": {
        "items": [
            {
                "id": 322,
                "name": "Свеча зажигания Bosch Super Plus",
                "article": "0242235666",
                "price": 320,
                "in_stock": 42,
                "brand": {
                    "id": 317,
                    "name": "Bosch"
                },
                "image": "/upload/iblock/1f2/ak0f7rmoambu2is2cldwuyry6y9ze5ot.jpg"
            }
        ],
        "pagination": {
            "page": 1,
            "limit": 5,
            "total": 35,
            "total_pages": 7
        }
    }
}
```

---

## GET /api/v1/catalog/{id}

Детальная информация по товару.

**Авторизация:** не требуется

### Запрос

```bash
curl http://dev-wordpress.kamaylaev.ru/api/v1/catalog/322
```

### Успешный ответ (200)

```json
{
    "success": true,
    "data": {
        "id": 322,
        "name": "Свеча зажигания Bosch Super Plus",
        "article": "0242235666",
        "description": "Свеча зажигания с никелевым электродом. Стандартный ресурс 30 000 км.",
        "price": 320,
        "in_stock": 42,
        "brand": {
            "id": 317,
            "name": "Bosch"
        },
        "gallery": [
            "/upload/iblock/1f2/ak0f7rmoambu2is2cldwuyry6y9ze5ot.jpg"
        ],
        "compatibility": [
            {"id": 17, "name": "BMW E39"}
        ]
    }
}
```

### Ошибка — товар не найден (404)

```json
{
    "success": false,
    "error": {
        "code": "NOT_FOUND",
        "message": "Товар не найден"
    }
}
```

---

## POST /api/v1/order/create

Создание заказа. Использует `Bitrix\Sale\Order` и `Bitrix\Sale\Basket`.

**Авторизация:** требуется (`Authorization: Bearer <токен>`)

### Тело запроса

| Поле | Тип | Обязательный | Описание |
|------|-----|--------------|----------|
| `items` | array | да | Список товаров заказа |
| `items[].product_id` | int | да | ID товара из каталога |
| `items[].quantity` | float | да | Количество |
| `person_type_id` | int | нет | Тип плательщика (по умолчанию 1) |
| `comment` | string | нет | Комментарий к заказу |

### Запрос

```bash
curl -X POST http://dev-wordpress.kamaylaev.ru/api/v1/order/create \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer e4263068a428e2f0e8050bdcfd350f74d13ab15bec8afba6f50580e5869985d9" \
  -d '{
    "items": [
        {"product_id": 322, "quantity": 2}
    ],
    "comment": "Тестовый заказ через API"
  }'
```

### Успешный ответ (201)

```json
{
    "success": true,
    "data": {
        "order_id": 6,
        "price": 640,
        "currency": "RUB",
        "items": [
            {
                "product_id": 322,
                "name": "Свеча зажигания Bosch Super Plus",
                "quantity": 2,
                "price": 320
            }
        ],
        "status": "created"
    }
}
```

### Ошибка — нет авторизации (401)

```json
{
    "success": false,
    "error": {
        "code": "UNAUTHORIZED",
        "message": "Недействительный или просроченный токен"
    }
}
```

### Ошибка — недостаточно товара на складе (409)

```json
{
    "success": false,
    "error": {
        "code": "OUT_OF_STOCK",
        "message": "Недостаточно товара \"Генератор Bosch AL0733X\" на складе (в наличии: 2)"
    }
}
```

---

## Архитектура

```
api/v1/
├── index.php          # роутер — разбирает URL, маршрутизирует запросы
└── .htaccess           # перенаправляет все запросы /api/v1/* на index.php

local/php_interface/
├── init.php             # регистрация автозагрузки классов API
└── api/
    ├── ApiResponse.php             # единый формат JSON-ответа
    ├── ApiToken.php                # генерация/проверка Bearer-токенов
    └── Controllers/
        ├── AuthController.php      # POST /auth/token
        ├── CatalogController.php   # GET /catalog/list, GET /catalog/{id}
        └── OrderController.php     # POST /order/create
```

Реализация полностью самостоятельная — без использования штатного модуля `bitrix:rest`. Для работы с данными используются стандартные классы Битрикса (`CIBlockElement`, `CUser`, `Bitrix\Sale\Order`, `Bitrix\Sale\Basket`).

### Хранение токенов

Таблица `autoparts_api_token` создаётся автоматически при первом обращении к API (метод `ApiToken::ensureTableExists()`):

```sql
CREATE TABLE `autoparts_api_token` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `USER_ID` int(11) NOT NULL,
    `TOKEN` varchar(64) NOT NULL,
    `CREATED_AT` datetime NOT NULL,
    `EXPIRES_AT` datetime NOT NULL,
    PRIMARY KEY (`ID`),
    UNIQUE KEY `IX_TOKEN` (`TOKEN`),
    KEY `IX_USER_ID` (`USER_ID`),
    KEY `IX_EXPIRES_AT` (`EXPIRES_AT`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
```

Просроченные токены можно очищать методом `ApiToken::cleanupExpired()` — рекомендуется вызывать по агенту Битрикса раз в сутки.

---

## Тестирование в Postman

1. Импортируй коллекцию вручную, создав 4 запроса по примерам выше
2. Для `auth/token` используй логин/пароль реального пользователя Битрикса
3. Скопируй полученный `token` из ответа
4. Вставь его в заголовок `Authorization: Bearer <token>` для запроса `order/create`

Токен живёт 24 часа — после этого нужно получить новый через `auth/token`.
