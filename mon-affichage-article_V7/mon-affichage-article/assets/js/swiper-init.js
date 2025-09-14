// Fichier: assets/js/swiper-init.js
document.addEventListener('DOMContentLoaded', function () {
    window.mySwiperInstances = window.mySwiperInstances || {};

    const wrappers = document.querySelectorAll('.my-articles-wrapper.my-articles-slideshow');

    wrappers.forEach(wrapper => {
        const instanceId = wrapper.id.replace('my-articles-wrapper-', '');
        const settingsObjectName = 'myArticlesSwiperSettings_' + instanceId;

        if (typeof window[settingsObjectName] !== 'undefined') {
            const settings = window[settingsObjectName];
            
            window.mySwiperInstances[instanceId] = new Swiper(settings.container_selector, {
                slidesPerView: settings.columns_mobile,
                spaceBetween: settings.gap_size,
                loop: true,
                pagination: {
                    el: settings.container_selector + ' .swiper-pagination',
                    clickable: true,
                },
                navigation: {
                    nextEl: settings.container_selector + ' .swiper-button-next',
                    prevEl: settings.container_selector + ' .swiper-button-prev',
                },
                breakpoints: {
                    768: { slidesPerView: settings.columns_tablet, spaceBetween: settings.gap_size },
                    1024: { slidesPerView: settings.columns_desktop, spaceBetween: settings.gap_size },
                    1536: { slidesPerView: settings.columns_ultrawide, spaceBetween: settings.gap_size }
                },
                on: {
                    init: function () {
                        const mainWrapper = document.querySelector('#my-articles-wrapper-' + instanceId);
                        if (mainWrapper) {
                            mainWrapper.classList.add('swiper-initialized');
                        }
                    }
                }
            });
        }
    });
});