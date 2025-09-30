// Fichier: assets/js/swiper-init.js
(function () {
    'use strict';

    function getInstanceId(wrapper) {
        if (!wrapper) {
            return '';
        }

        if (wrapper.dataset && wrapper.dataset.instanceId) {
            return wrapper.dataset.instanceId;
        }

        if (wrapper.id && wrapper.id.indexOf('my-articles-wrapper-') === 0) {
            return wrapper.id.replace('my-articles-wrapper-', '');
        }

        return '';
    }

    function collectWrappers(target) {
        const found = new Set();

        function add(node) {
            if (!node) {
                return;
            }

            if (node.classList && node.classList.contains('my-articles-wrapper') && node.classList.contains('my-articles-slideshow')) {
                found.add(node);
            }

            if (typeof node.querySelectorAll === 'function') {
                node.querySelectorAll('.my-articles-wrapper.my-articles-slideshow').forEach(function (wrapper) {
                    found.add(wrapper);
                });
            }
        }

        if (!target) {
            document.querySelectorAll('.my-articles-wrapper.my-articles-slideshow').forEach(function (wrapper) {
                found.add(wrapper);
            });
            return Array.from(found);
        }

        if (target.jquery) {
            target.each(function (index, element) {
                add(element);
            });
            return Array.from(found);
        }

        if (typeof target.length === 'number' && typeof target !== 'string') {
            Array.prototype.forEach.call(target, function (item) {
                add(item);
            });
            return Array.from(found);
        }

        add(target);
        return Array.from(found);
    }

    function initSwiperForWrapper(wrapper) {
        const instanceId = getInstanceId(wrapper);
        if (!instanceId) {
            return null;
        }

        const settingsObjectName = 'myArticlesSwiperSettings_' + instanceId;
        const settings = window[settingsObjectName];
        if (!settings) {
            return null;
        }

        window.mySwiperInstances = window.mySwiperInstances || {};

        const existingInstance = window.mySwiperInstances[instanceId];
        if (existingInstance && typeof existingInstance.destroy === 'function') {
            existingInstance.destroy(true, true);
        }

        if (wrapper.classList) {
            wrapper.classList.remove('swiper-initialized');
        }

        const instance = new Swiper(settings.container_selector, {
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
                1536: { slidesPerView: settings.columns_ultrawide, spaceBetween: settings.gap_size },
            },
            on: {
                init: function () {
                    const mainWrapper = document.querySelector('#my-articles-wrapper-' + instanceId);
                    if (mainWrapper) {
                        mainWrapper.classList.add('swiper-initialized');
                    }
                },
            },
        });

        window.mySwiperInstances[instanceId] = instance;
        return instance;
    }

    function initSwipers(target) {
        const wrappers = collectWrappers(target);
        return wrappers.map(initSwiperForWrapper);
    }

    function handleDomReady() {
        initSwipers();
    }

    window.myArticlesInitSwipers = initSwipers;

    if (document.readyState !== 'loading') {
        handleDomReady();
    } else {
        document.addEventListener('DOMContentLoaded', handleDomReady);
    }
})();
