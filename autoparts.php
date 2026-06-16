<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
$APPLICATION->SetTitle('Каталог автозапчастей');
?>

<?php $APPLICATION->IncludeComponent(
    'autoparts:catalog',
    '.default',
    [
        'IBLOCK_ID'        => 4,
        'BRANDS_IBLOCK_ID' => 5,
        'CACHE_TIME'       => 3600,
        'PAGE_SIZE'        => 12,
    ]
); ?>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php'; ?>