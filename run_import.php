<?php
// Защита: только для авторизованных администраторов Битрикса
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);
define('BX_CRONTAB', true);

require __DIR__ . '/bitrix/modules/main/include/prolog_before.php';

// Только администратор может запустить импорт
if (!$USER->IsAdmin()) {
    die('Доступ запрещён. Войдите как администратор.');
}

require __DIR__ . '/local/php_interface/cron/import_1c.php';