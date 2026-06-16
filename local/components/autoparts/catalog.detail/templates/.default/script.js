/**
 * АвтоЗапчасти — Детальная страница
 * Инициализация слайдера Swiper
 */
document.addEventListener('DOMContentLoaded', function () {

    // Проверяем наличие слайдера на странице
    if (!document.querySelector('.ap-slider__main')) return;

    // Слайдер миниатюр
    var thumbsSwiper = null;
    if (document.querySelector('.ap-slider__thumbs')) {
        thumbsSwiper = new Swiper('.ap-slider__thumbs', {
            spaceBetween: 8,
            slidesPerView: 5,
            freeMode: true,
            watchSlidesProgress: true,
        });
    }

    // Основной слайдер
    new Swiper('.ap-slider__main', {
        spaceBetween: 10,
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
        thumbs: thumbsSwiper ? { swiper: thumbsSwiper } : undefined,
    });
});
