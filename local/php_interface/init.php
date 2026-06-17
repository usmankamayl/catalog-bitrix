<?php
/**
 * local/php_interface/init.php
 * Стандартный файл инициализации Битрикса.
 * Если у вас уже есть init.php — добавьте содержимое ниже в конец вашего файла.
 */

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('main', [
    'Autoparts\\Api\\ApiResponse'                     => '/local/php_interface/api/ApiResponse.php',
    'Autoparts\\Api\\ApiToken'                        => '/local/php_interface/api/ApiToken.php',
    'Autoparts\\Api\\Controllers\\AuthController'     => '/local/php_interface/api/Controllers/AuthController.php',
    'Autoparts\\Api\\Controllers\\CatalogController'  => '/local/php_interface/api/Controllers/CatalogController.php',
    'Autoparts\\Api\\Controllers\\OrderController'    => '/local/php_interface/api/Controllers/OrderController.php',
]);