<?php

namespace Autoparts\Api\Controllers;

use Autoparts\Api\ApiResponse;
use Autoparts\Api\ApiToken;
use CUser;

/**
 * POST /api/v1/auth/token
 * Тело запроса: {"login": "...", "password": "..."}
 */
class AuthController
{
    public function getToken(array $body): void
    {
        $login    = trim($body['login'] ?? '');
        $password = trim($body['password'] ?? '');

        if ($login === '' || $password === '') {
            ApiResponse::error('VALIDATION_ERROR', 'Не указан login или password', 422);
            return;
        }

        global $USER;
        if (!isset($USER) || !($USER instanceof CUser)) {
            $USER = new CUser();
        }

        // Логиним без выставления постоянной сессионной cookie конечного пользователя —
        // CUser::Login используется только для проверки пары логин/пароль.
        $result = $USER->Login($login, $password, 'N');

        if ($result !== true) {
            ApiResponse::error('INVALID_CREDENTIALS', 'Неверный логин или пароль', 401);
            return;
        }

        $userId = (int)$USER->GetID();

        $tokenData = ApiToken::issue($userId);

        ApiResponse::success([
            'user_id'    => $userId,
            'token'      => $tokenData['token'],
            'token_type' => 'Bearer',
            'expires_at' => $tokenData['expires_at'],
            'ttl'        => $tokenData['ttl'],
        ]);
    }
}
