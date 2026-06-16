<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die(); ?>

<?php
// Подключаем CSS и JS шаблона
$this->addExternalCss($this->GetFolder() . '/style.css');
$this->addExternalJs($this->GetFolder() . '/script.js');

// Bootstrap 5 CDN
\Bitrix\Main\Page\Asset::getInstance()->addCss(
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css'
);
\Bitrix\Main\Page\Asset::getInstance()->addJs(
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'
);

$items  = $arResult['ITEMS'];
$brands = $arResult['BRANDS'];
$filter = $arResult['FILTER'];

$currentBrand   = (int)($_GET['BRAND'] ?? 0);
$currentInStock = !empty($_GET['IN_STOCK']);
$priceFrom      = htmlspecialchars($_GET['PRICE_FROM'] ?? '');
$priceTo        = htmlspecialchars($_GET['PRICE_TO'] ?? '');
?>

<div class="ap-catalog container py-4" id="ap-catalog">

    <div class="row g-4">

        <!-- ФИЛЬТР (сайдбар) -->
        <aside class="col-lg-3">
            <div class="ap-filter card p-3" id="ap-filter">
                <h5 class="ap-filter__title mb-3">Фильтр</h5>

                <!-- Бренд -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Бренд</label>
                    <select class="form-select ap-filter__brand" id="filter-brand">
                        <option value="">Все бренды</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= (int)$brand['ID'] ?>"
                                <?= $currentBrand === (int)$brand['ID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($brand['NAME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Диапазон цены -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Цена, ₽</label>
                    <div class="d-flex gap-2">
                        <input type="number" class="form-control" id="filter-price-from"
                               placeholder="от" value="<?= $priceFrom ?>" min="0">
                        <input type="number" class="form-control" id="filter-price-to"
                               placeholder="до" value="<?= $priceTo ?>" min="0">
                    </div>
                </div>

                <!-- Наличие -->
                <div class="mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="filter-in-stock"
                            <?= $currentInStock ? 'checked' : '' ?>>
                        <label class="form-check-label" for="filter-in-stock">
                            Только в наличии
                        </label>
                    </div>
                </div>

                <button class="btn btn-primary w-100" id="filter-apply">Применить</button>
                <button class="btn btn-outline-secondary w-100 mt-2" id="filter-reset">Сбросить</button>
            </div>
        </aside>

        <!-- СПИСОК ТОВАРОВ -->
        <div class="col-lg-9">

            <!-- Спиннер загрузки -->
            <div class="ap-catalog__loader d-none text-center py-5" id="ap-loader">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
            </div>

            <!-- Сетка товаров -->
            <div class="row g-3" id="ap-items">
                <?php if (empty($items)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">Товары не найдены. Попробуйте изменить фильтр.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <div class="col-sm-6 col-xl-4">
                            <div class="ap-card card h-100">

                                <!-- Картинка -->
                                <a href="<?= htmlspecialchars($item['DETAIL_PAGE_URL']) ?>"
                                   class="ap-card__img-wrap">
                                    <?php if (!empty($item['PREVIEW_PICTURE'])): ?>
                                        <?php $img = CFile::GetFileArray($item['PREVIEW_PICTURE']); ?>
                                        <img src="<?= htmlspecialchars($img['SRC']) ?>"
                                             alt="<?= htmlspecialchars($item['NAME']) ?>"
                                             class="card-img-top ap-card__img">
                                    <?php else: ?>
                                        <div class="ap-card__no-img d-flex align-items-center justify-content-center bg-light">
                                            <span class="text-muted">Нет фото</span>
                                        </div>
                                    <?php endif; ?>
                                </a>

                                <div class="card-body d-flex flex-column">

                                    <!-- Бренд -->
                                    <?php if (!empty($item['BRAND_NAME'])): ?>
                                        <span class="ap-card__brand badge bg-secondary mb-1">
                                            <?= htmlspecialchars($item['BRAND_NAME']) ?>
                                        </span>
                                    <?php endif; ?>

                                    <!-- Название -->
                                    <h6 class="ap-card__name card-title">
                                        <a href="<?= htmlspecialchars($item['DETAIL_PAGE_URL']) ?>"
                                           class="text-decoration-none text-dark stretched-link">
                                            <?= htmlspecialchars($item['NAME']) ?>
                                        </a>
                                    </h6>

                                    <!-- Артикул -->
                                    <?php if (!empty($item['PROPERTY_ARTICLE_VALUE'])): ?>
                                        <p class="ap-card__article text-muted small mb-2">
                                            Арт.: <?= htmlspecialchars($item['PROPERTY_ARTICLE_VALUE']) ?>
                                        </p>
                                    <?php endif; ?>

                                    <div class="mt-auto">
                                        <!-- Цена -->
                                        <?php if (!empty($item['PROPERTY_PRICE_VALUE'])): ?>
                                            <div class="ap-card__price fw-bold fs-5 text-primary mb-2">
                                                <?= number_format((float)$item['PROPERTY_PRICE_VALUE'], 0, '.', ' ') ?> ₽
                                            </div>
                                        <?php endif; ?>

                                        <!-- Наличие -->
                                        <?php $stock = (int)$item['PROPERTY_IN_STOCK_VALUE']; ?>
                                        <div class="ap-card__stock mb-3">
                                            <?php if ($stock > 0): ?>
                                                <span class="badge bg-success">В наличии: <?= $stock ?> шт.</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Нет в наличии</span>
                                            <?php endif; ?>
                                        </div>

                                        <a href="<?= htmlspecialchars($item['DETAIL_PAGE_URL']) ?>"
                                           class="btn btn-outline-primary btn-sm w-100 position-relative">
                                            Подробнее
                                        </a>
                                    </div>
                                </div>

                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <!-- /ap-items -->

        </div>
    </div>
</div>
