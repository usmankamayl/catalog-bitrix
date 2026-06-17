<?php
/**
 * REST API роутер для мобильного приложения «АвтоЗапчасти».
 *
 * Все запросы к /api/v1/... обрабатываются здесь (см. .htaccess в этой папке).
 * Собственная реализация — без bitrix:rest.
 *
 * Эндпоинты:
 *   GET  /api/v1/catalog/list      — список товаров (пагинация, фильтр по бренду)
 *   GET  /api/v1/catalog/{id}      — детальная информация по товару
 *   POST /api/v1/auth/token        — получение Bearer-токена по логину/паролю
 *   POST /api/v1/order/create      — создание заказа (требует токен)
 */

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);
define('STATISTIC_SKIP_ACCESS_LOG', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Autoparts\Api\ApiResponse;
use Autoparts\Api\ApiToken;
use Autoparts\Api\Controllers\CatalogController;
use Autoparts\Api\Controllers\AuthController;
use Autoparts\Api\Controllers\OrderController;

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/api/ApiResponse.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/api/ApiToken.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/api/Controllers/CatalogController.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/api/Controllers/AuthController.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/api/Controllers/OrderController.php';

if (!Loader::includeModule('iblock')) {
    ApiResponse::error('MODULE_ERROR', 'Модуль iblock не подключён', 500);
}

// =============================================
// Глобальный перехват ошибок — API никогда не должен
// отдавать HTML с ошибкой PHP вместо JSON.
// =============================================
set_exception_handler(function (Throwable $e) {
    ApiResponse::error('INTERNAL_ERROR', 'Внутренняя ошибка сервера', 500);
});

set_error_handler(function ($severity, $message, $file, $line) {
    // Deprecated/Notice не должны прерывать выполнение —
    // это особенность совместимости модулей Битрикса с PHP 8.1+
    if ($severity === E_DEPRECATED || $severity === E_NOTICE || $severity === E_WARNING) {
        return true; // подавляем, но не прерываем
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// =============================================
// Разбор URL
// Пример: /api/v1/catalog/list -> ['catalog', 'list']
//         /api/v1/catalog/123  -> ['catalog', '123']
// =============================================
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path       = parse_url($requestUri, PHP_URL_PATH);
$path       = preg_replace('#^.*?/api/v1/?#', '', $path);
$path       = trim($path, '/');
$segments   = $path === '' ? [] : explode('/', $path);

$resource = $segments[0] ?? '';
$action   = $segments[1] ?? '';
$method   = $_SERVER['REQUEST_METHOD'];

// Тело запроса (для POST с JSON)
$rawBody = file_get_contents('php://input');
$body    = json_decode($rawBody, true) ?: [];

// =============================================
// Извлечение Bearer-токена из заголовка Authorization
// =============================================
function getBearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    // Некоторые конфигурации Apache/PHP-FPM не прокидывают заголовок
    // Authorization в $_SERVER напрямую — проверяем альтернативные источники.
    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header  = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if ($header === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
        return $matches[1];
    }
    return null;
}

function requireAuth(): int
{
    $token  = getBearerToken();
    $userId = $token ? ApiToken::validate($token) : null;

    if (!$userId) {
        ApiResponse::error('UNAUTHORIZED', 'Недействительный или просроченный токен', 401);
    }

    return $userId;
}

// =============================================
// МАРШРУТИЗАЦИЯ
// =============================================
try {
    switch (true) {

        // GET /api/v1/catalog/list
        case $resource === 'catalog' && $action === 'list' && $method === 'GET':
            (new CatalogController())->list($_GET);
            break;

        // GET /api/v1/catalog/{id}
        case $resource === 'catalog' && $action !== '' && is_numeric($action) && $method === 'GET':
            (new CatalogController())->detail((int)$action);
            break;

        // POST /api/v1/auth/token
        case $resource === 'auth' && $action === 'token' && $method === 'POST':
            (new AuthController())->getToken($body);
            break;

        // POST /api/v1/order/create
        case $resource === 'order' && $action === 'create' && $method === 'POST':
            $userId = requireAuth();
            (new OrderController())->create($userId, $body);
            break;

        default:
            ApiResponse::error('NOT_FOUND', 'Эндпоинт не найден', 404);
    }
} catch (Throwable $e) {
    ApiResponse::error('INTERNAL_ERROR', 'Внутренняя ошибка сервера: ' . $e->getMessage(), 500);
}