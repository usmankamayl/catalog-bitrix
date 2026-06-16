/**
 * AJAX-фильтр каталога АвтоЗапчасти
 * Обновляет список товаров без перезагрузки страницы
 */
document.addEventListener('DOMContentLoaded', function () {

    const filterBrand   = document.getElementById('filter-brand');
    const filterPriceFrom = document.getElementById('filter-price-from');
    const filterPriceTo = document.getElementById('filter-price-to');
    const filterInStock = document.getElementById('filter-in-stock');
    const btnApply      = document.getElementById('filter-apply');
    const btnReset      = document.getElementById('filter-reset');
    const itemsContainer = document.getElementById('ap-items');
    const loader        = document.getElementById('ap-loader');

    if (!btnApply) return;

    // Применить фильтр
    btnApply.addEventListener('click', function () {
        applyFilter();
    });

    // Сбросить фильтр
    btnReset.addEventListener('click', function () {
        filterBrand.value      = '';
        filterPriceFrom.value  = '';
        filterPriceTo.value    = '';
        filterInStock.checked  = false;
        applyFilter();
    });

    function applyFilter() {
        const params = new URLSearchParams();

        if (filterBrand.value)       params.set('BRAND', filterBrand.value);
        if (filterPriceFrom.value)   params.set('PRICE_FROM', filterPriceFrom.value);
        if (filterPriceTo.value)     params.set('PRICE_TO', filterPriceTo.value);
        if (filterInStock.checked)   params.set('IN_STOCK', '1');

        // Показываем лоадер
        itemsContainer.classList.add('d-none');
        loader.classList.remove('d-none');

        // Обновляем URL без перезагрузки
        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        window.history.pushState({}, '', newUrl);

        // AJAX запрос
        fetch(window.location.pathname + '?ajax=Y&' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) {
            if (!response.ok) throw new Error('Network error');
            return response.text();
        })
        .then(function(html) {
            // Парсим ответ и извлекаем #ap-items
            const parser  = new DOMParser();
            const doc     = parser.parseFromString(html, 'text/html');
            const newItems = doc.getElementById('ap-items');

            if (newItems) {
                itemsContainer.innerHTML = newItems.innerHTML;
            }

            loader.classList.add('d-none');
            itemsContainer.classList.remove('d-none');
        })
        .catch(function(err) {
            console.error('Ошибка фильтра:', err);
            loader.classList.add('d-none');
            itemsContainer.classList.remove('d-none');
        });
    }
});
