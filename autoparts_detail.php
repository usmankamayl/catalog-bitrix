<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
?>

<?php $APPLICATION->IncludeComponent(
    'autoparts:catalog.detail',
    '.default',
    [
        'IBLOCK_ID'        => 4,
        'BRANDS_IBLOCK_ID' => 5,
        'ELEMENT_ID'       => (int)($_GET['ID'] ?? 0),
        'CACHE_TIME'       => 3600,
    ]
); ?>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php'; ?>
