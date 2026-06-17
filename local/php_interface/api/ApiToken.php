<?php

namespace Autoparts\Api;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;

/**
 * Управление Bearer-токенами для REST API.
 * Хранилище — собственная таблица `autoparts_api_token`.
 */
class ApiToken
{
    const TABLE_NAME   = 'autoparts_api_token';
    const TTL_SECONDS   = 86400; // токен живёт 24 часа

    /**
     * Создаёт таблицу токенов, если её ещё нет.
     * Вызывается лениво при первом обращении к модулю.
     */
    public static function ensureTableExists(): void
    {
        $connection = Application::getConnection();

        if ($connection->isTableExists(self::TABLE_NAME)) {
            return;
        }

        $connection->query("
            CREATE TABLE `" . self::TABLE_NAME . "` (
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
        ");
    }

    /**
     * Генерирует новый токен для пользователя и сохраняет его.
     */
    public static function issue(int $userId): array
    {
        self::ensureTableExists();

        $token     = bin2hex(random_bytes(32)); // 64 символа
        $now       = new DateTime();
        $expiresAt = new DateTime();
        $expiresAt->add('+' . self::TTL_SECONDS . ' seconds');

        $connection = Application::getConnection();
        $connection->add(self::TABLE_NAME, [
            'USER_ID'    => $userId,
            'TOKEN'      => $token,
            'CREATED_AT' => $now,
            'EXPIRES_AT' => $expiresAt,
        ]);

        return [
            'token'      => $token,
            'expires_at' => $expiresAt->toString(),
            'ttl'        => self::TTL_SECONDS,
        ];
    }

    /**
     * Проверяет токен и возвращает ID пользователя либо null, если токен
     * недействителен или просрочен.
     */
    public static function validate(string $token): ?int
    {
        self::ensureTableExists();

        if ($token === '') {
            return null;
        }

        $connection = Application::getConnection();
        $escaped = $connection->getSqlHelper()->forSql($token);

        $result = $connection->query("
            SELECT USER_ID, EXPIRES_AT
            FROM `" . self::TABLE_NAME . "`
            WHERE TOKEN = '{$escaped}'
            LIMIT 1
        ")->fetch();

        if (!$result) {
            return null;
        }

        $expiresAt = strtotime($result['EXPIRES_AT']);
        if ($expiresAt < time()) {
            return null; // токен просрочен
        }

        return (int)$result['USER_ID'];
    }

    /**
     * Удаляет просроченные токены. Можно вызывать по агенту/крону.
     */
    public static function cleanupExpired(): int
    {
        self::ensureTableExists();

        $connection = Application::getConnection();
        $now = (new DateTime())->toString();

        $connection->query("
            DELETE FROM `" . self::TABLE_NAME . "`
            WHERE EXPIRES_AT < '{$now}'
        ");

        return $connection->getAffectedRowsCount();
    }
}