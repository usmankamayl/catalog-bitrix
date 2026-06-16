<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die(); ?>

<?php
$this->addExternalCss($this->GetFolder() . '/style.css');
$this->addExternalJs($this->GetFolder() . '/script.js');

\Bitrix\Main\Page\Asset::getInstance()->addCss(
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css'
);
\Bitrix\Main\Page\Asset::getInstance()->addCss(
    'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css'
);
\Bitrix\Main\Page\Asset::getInstance()->addJs(
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'
);
\Bitrix\Main\Page\Asset::getInstance()->addJs(
    'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js'
);

$item   = $arResult['ITEM'];
$similar = $arResult['SIMILAR'];
$images = $item['GALLERY_IMAGES'];
$stock  = (int)$item['PROPERTY_IN_STOCK_VALUE'];
?>

<div class="ap-detail container py-4">

    <!-- Хлебные крошки -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Главная</a></li>
            <li class="breadcrumb-item"><a href="/autoparts.php">Каталог</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($item['NAME']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">

        <!-- СЛАЙДЕР ФОТОГРАФИЙ -->
        <div class="col-lg-5">
            <?php if (!empty($images)): ?>
                <!-- Основной слайдер -->
                <div class="swiper ap-slider__main mb-2">
                    <div class="swiper-wrapper">
                        <?php foreach ($images as $src): ?>
                            <div class="swiper-slide">
                                <img src="<?= htmlspecialchars($src) ?>"
                                     alt="<?= htmlspecialchars($item['NAME']) ?>"
                                     class="ap-slider__img">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                </div>

                <!-- Миниатюры -->
                <?php if (count($images) > 1): ?>
                    <div class="swiper ap-slider__thumbs">
                        <div class="swiper-wrapper">
                            <?php foreach ($images as $src): ?>
                                <div class="swiper-slide">
                                    <img src="<?= htmlspecialchars($src) ?>"
                                         alt="фото"
                                         class="ap-slider__thumb-img">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="ap-detail__no-img d-flex align-items-center justify-content-center bg-light rounded">
                    <span class="text-muted fs-5">Фото отсутствует</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- ИНФОРМАЦИЯ О ТОВАРЕ -->
        <div class="col-lg-7">

            <?php if (!empty($item['BRAND_NAME'])): ?>
                <span class="badge bg-secondary mb-2"><?= htmlspecialchars($item['BRAND_NAME']) ?></span>
            <?php endif; ?>

            <h1 class="ap-detail__name mb-3"><?= htmlspecialchars($item['NAME']) ?></h1>

            <!-- Артикул -->
            <?php if (!empty($item['PROPERTY_ARTICLE_VALUE'])): ?>
                <p class="text-muted mb-3">
                    Артикул: <strong><?= htmlspecialchars($item['PROPERTY_ARTICLE_VALUE']) ?></strong>
                </p>
            <?php endif; ?>

            <!-- Цена -->
            <?php if (!empty($item['PROPERTY_PRICE_VALUE'])): ?>
                <div class="ap-detail__price mb-3">
                    <?= number_format((float)$item['PROPERTY_PRICE_VALUE'], 0, '.', ' ') ?> ₽
                </div>
            <?php endif; ?>

            <!-- Наличие -->
            <div class="mb-4">
                <?php if ($stock > 0): ?>
                    <span class="badge bg-success fs-6">В наличии: <?= $stock ?> шт.</span>
                <?php else: ?>
                    <span class="badge bg-danger fs-6">Нет в наличии</span>
                <?php endif; ?>
            </div>

            <!-- Кнопка в корзину -->
            <?php if ($stock > 0): ?>
                <button class="btn btn-primary btn-lg mb-4">
                    🛒 Добавить в корзину
                </button>
            <?php endif; ?>

            <!-- Описание -->
            <?php if (!empty($item['DETAIL_TEXT'])): ?>
                <div class="ap-detail__desc">
                    <h5 class="mb-2">Описание</h5>
                    <p class="text-muted"><?= nl2br(htmlspecialchars($item['DETAIL_TEXT'])) ?></p>
                </div>
            <?php endif; ?>

            <!-- Совместимость -->
            <?php if (!empty($item['COMPATIBILITY_NAMES'])): ?>
                <div class="mt-3">
                    <h6>Совместимость:</h6>
                    <?php foreach ($item['COMPATIBILITY_NAMES'] as $name): ?>
                        <span class="badge bg-light text-dark border me-1 mb-1"><?= htmlspecialchars($name) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- ПОХОЖИЕ ТОВАРЫ -->
    <?php if (!empty($similar)): ?>
        <div class="ap-similar mt-5">
            <h3 class="ap-similar__title mb-4">Похожие товары</h3>
            <div class="row g-3">
                <?php foreach ($similar as $s): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="ap-card card h-100">
                            <a href="<?= htmlspecialchars($s['DETAIL_PAGE_URL']) ?>" class="ap-card__img-wrap">
                                <?php if (!empty($s['PREVIEW_PICTURE'])): ?>
                                    <?php $sImg = CFile::GetFileArray($s['PREVIEW_PICTURE']); ?>
                                    <img src="<?= htmlspecialchars($sImg['SRC']) ?>"
                                         alt="<?= htmlspecialchars($s['NAME']) ?>"
                                         class="card-img-top ap-card__img">
                                <?php else: ?>
                                    <div class="ap-card__no-img d-flex align-items-center justify-content-center bg-light">
                                        <span class="text-muted small">Нет фото</span>
                                    </div>
                                <?php endif; ?>
                            </a>
                            <div class="card-body p-2">
                                <p class="ap-card__name small mb-1">
                                    <a href="<?= htmlspecialchars($s['DETAIL_PAGE_URL']) ?>"
                                       class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($s['NAME']) ?>
                                    </a>
                                </p>
                                <?php if (!empty($s['PROPERTY_PRICE_VALUE'])): ?>
                                    <div class="fw-bold text-primary small">
                                        <?= number_format((float)$s['PROPERTY_PRICE_VALUE'], 0, '.', ' ') ?> ₽
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>
