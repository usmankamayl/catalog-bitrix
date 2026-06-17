<?php

namespace Autoparts\Api;

/**
 * Единый формат JSON-ответа API.
 *
 * Успех:  {"success": true,  "data": {...}}
 * Ошибка: {"success": false, "error": {"code": "...", "message": "..."}}
 */
class ApiResponse
{
    public static function success($data, int $httpCode = 200): void
    {
        self::send(['success' => true, 'data' => $data], $httpCode);
    }

    public static function error(string $code, string $message, int $httpCode = 400): void
    {
        self::send([
            'success' => false,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
        ], $httpCode);
    }

    private static function send(array $payload, int $httpCode): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
