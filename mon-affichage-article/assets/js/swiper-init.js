// Fichier: assets/js/swiper-init.js
(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory(root || (typeof globalThis !== 'undefined' ? globalThis : {}));
    } else {
        factory(root);
    }
})(typeof window !== 'undefined' ? window : this, function (root) {
    'use strict';

    const doc = root && root.document;

    function ensureArray(value) {
        if (!value) {
            return [];
        }

        if (Array.isArray(value)) {
            return value;
        }

        if (typeof value.length === 'number' && typeof value !== 'string') {
            return Array.prototype.slice.call(value);
        }

        return [value];
    }

    function shallowClone(object) {
        if (!object || typeof object !== 'object') {
            return object;
        }

        var clone = {};
        for (var key in object) {
            if (Object.prototype.hasOwnProperty.call(object, key)) {
                clone[key] = object[key];
            }
        }

        return clone;
    }

    function preloadSlideMedia(slide) {
        if (!slide || typeof slide.querySelectorAll !== 'function') {
            return;
        }

        const candidates = slide.querySelectorAll('[data-src], [data-srcset], [data-background-image]');

        candidates.forEach(function (element) {
            const dataset = element.dataset || {};

            if (dataset.src && !element.getAttribute('src')) {
                element.setAttribute('src', dataset.src);
            }

            if (dataset.srcset && !element.getAttribute('srcset')) {
                element.setAttribute('srcset', dataset.srcset);
            }

            if (dataset.backgroundImage && element.style) {
                element.style.backgroundImage = 'url(' + dataset.backgroundImage + ')';
            }
        });
    }

    function preloadNeighbouringSlides(swiper, explicitIndex) {
        if (!swiper) {
            return;
        }

        const slides = ensureArray(swiper.slides);
        const index = typeof explicitIndex === 'number' ? explicitIndex : swiper.activeIndex || 0;

        [index - 1, index + 1].forEach(function (candidateIndex) {
            const slide = slides[candidateIndex];
            if (!slide) {
                return;
            }

            preloadSlideMedia(slide);
        });
    }

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

        if (!target && doc) {
            doc.querySelectorAll('.my-articles-wrapper.my-articles-slideshow').forEach(function (wrapper) {
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
        const settings = root ? root[settingsObjectName] : undefined;
        if (!settings) {
            return null;
        }

        if (root) {
            root.mySwiperInstances = root.mySwiperInstances || {};
        }

        const instanceStore = root ? root.mySwiperInstances : {};

        const existingInstance = instanceStore[instanceId];
        if (existingInstance && typeof existingInstance.destroy === 'function') {
            existingInstance.destroy(true, true);
            delete instanceStore[instanceId];
        }

        if (wrapper.classList) {
            wrapper.classList.remove('swiper-initialized');
            wrapper.classList.add('swiper-is-loading');
        }

        var autoplaySettings = settings.autoplay;
        var respectReducedMotion = !!settings.respect_reduced_motion;
        var reducedMotionMediaQuery = null;
        var prefersReducedMotion = false;

        if (respectReducedMotion && root && typeof root.matchMedia === 'function') {
            try {
                reducedMotionMediaQuery = root.matchMedia('(prefers-reduced-motion: reduce)');
                if (reducedMotionMediaQuery && typeof reducedMotionMediaQuery.matches === 'boolean') {
                    prefersReducedMotion = reducedMotionMediaQuery.matches;
                }
            } catch (error) {
                reducedMotionMediaQuery = null;
            }
        }

        if (!autoplaySettings || typeof autoplaySettings !== 'object' || Array.isArray(autoplaySettings)) {
            var fallbackEnabled = !!settings.autoplay;
            autoplaySettings = {
                enabled: fallbackEnabled,
                delay: typeof settings.autoplay_delay === 'number' ? settings.autoplay_delay : 5000,
                pause_on_interaction:
                    settings.pause_on_interaction === undefined
                        ? true
                        : !!settings.pause_on_interaction,
                pause_on_mouse_enter:
                    settings.pause_on_mouse_enter === undefined
                        ? true
                        : !!settings.pause_on_mouse_enter,
            };
        }

        const normalizeNumber = function (value, fallback) {
            if (typeof value === 'number' && !Number.isNaN(value)) {
                return value;
            }

            const parsed = Number(value);
            if (Number.isFinite(parsed)) {
                return parsed;
            }

            return typeof fallback === 'number' && !Number.isNaN(fallback) ? fallback : 0;
        };

        const gapSize = normalizeNumber(settings.gap_size, 0);

        let autoplayConfig = autoplaySettings.enabled
            ? {
                  delay: typeof autoplaySettings.delay === 'number' ? autoplaySettings.delay : 5000,
                  disableOnInteraction:
                      autoplaySettings.pause_on_interaction === undefined
                          ? true
                          : !!autoplaySettings.pause_on_interaction,
                  pauseOnMouseEnter:
                      autoplaySettings.pause_on_mouse_enter === undefined
                          ? true
                          : !!autoplaySettings.pause_on_mouse_enter,
              }
            : false;
        const originalAutoplayConfig =
            autoplayConfig && typeof autoplayConfig === 'object'
                ? shallowClone(autoplayConfig)
                : autoplayConfig;

        if (prefersReducedMotion) {
            autoplayConfig = false;
        }

        const paginationConfig = settings.show_pagination
            ? {
                  el: settings.container_selector + ' .swiper-pagination',
                  clickable: true,
              }
            : false;

        const navigationConfig = settings.show_navigation
            ? {
                  nextEl: settings.container_selector + ' .swiper-button-next',
                  prevEl: settings.container_selector + ' .swiper-button-prev',
              }
            : false;

        const instance = new Swiper(settings.container_selector, {
            slidesPerView: settings.columns_mobile,
            spaceBetween: gapSize,
            loop: !!settings.loop,
            watchOverflow: true,
            keyboard: {
                enabled: true,
                onlyInViewport: true,
            },
            pagination: paginationConfig,
            navigation: navigationConfig,
            autoplay: autoplayConfig,
            a11y: {
                enabled: true,
                prevSlideMessage: settings.a11y_prev_slide_message,
                nextSlideMessage: settings.a11y_next_slide_message,
                firstSlideMessage: settings.a11y_first_slide_message,
                lastSlideMessage: settings.a11y_last_slide_message,
                paginationBulletMessage: settings.a11y_pagination_bullet_message,
                slideLabelMessage: settings.a11y_slide_label_message,
                containerMessage: settings.a11y_container_message,
                containerRoleDescriptionMessage: settings.a11y_container_role_description,
                itemRoleDescriptionMessage: settings.a11y_item_role_description,
            },
            breakpoints: {
                768: { slidesPerView: settings.columns_tablet, spaceBetween: gapSize },
                1024: { slidesPerView: settings.columns_desktop, spaceBetween: gapSize },
                1536: { slidesPerView: settings.columns_ultrawide, spaceBetween: gapSize },
            },
            on: {
                init: function () {
                    if (!doc) {
                        return;
                    }

                    const mainWrapper = doc.querySelector('#my-articles-wrapper-' + instanceId);
                    if (mainWrapper) {
                        mainWrapper.classList.remove('swiper-is-loading');
                        mainWrapper.classList.add('swiper-initialized');
                    }

                    preloadNeighbouringSlides(this);
                },
                slideChange: function () {
                    preloadNeighbouringSlides(this);
                },
            },
        });

        if (respectReducedMotion && instance && instance.params && typeof instance.params === 'object') {
            instance.params.autoplay = originalAutoplayConfig && typeof originalAutoplayConfig === 'object'
                ? shallowClone(originalAutoplayConfig)
                : originalAutoplayConfig;
        }

        if (
            respectReducedMotion &&
            reducedMotionMediaQuery &&
            instance &&
            instance.autoplay &&
            typeof instance.autoplay === 'object'
        ) {
            const handlePreferenceChange = function (event) {
                const matches = event && typeof event.matches === 'boolean' ? event.matches : false;

                if (matches) {
                    if (instance.autoplay && typeof instance.autoplay.stop === 'function') {
                        instance.autoplay.stop();
                    }
                } else if (originalAutoplayConfig && typeof originalAutoplayConfig === 'object') {
                    instance.params.autoplay = shallowClone(originalAutoplayConfig);
                    if (instance.autoplay && typeof instance.autoplay.start === 'function') {
                        instance.autoplay.start();
                    }
                }
            };

            if (prefersReducedMotion && typeof instance.autoplay.stop === 'function') {
                instance.autoplay.stop();
            }

            if (typeof reducedMotionMediaQuery.addEventListener === 'function') {
                reducedMotionMediaQuery.addEventListener('change', handlePreferenceChange);
            } else if (typeof reducedMotionMediaQuery.addListener === 'function') {
                reducedMotionMediaQuery.addListener(handlePreferenceChange);
            }

            instance.on('destroy', function () {
                if (typeof reducedMotionMediaQuery.removeEventListener === 'function') {
                    reducedMotionMediaQuery.removeEventListener('change', handlePreferenceChange);
                } else if (typeof reducedMotionMediaQuery.removeListener === 'function') {
                    reducedMotionMediaQuery.removeListener(handlePreferenceChange);
                }
            });
        }

        instanceStore[instanceId] = instance;
        return instance;
    }

    function initSwipers(target) {
        const wrappers = collectWrappers(target);
        return wrappers.map(initSwiperForWrapper);
    }

    function handleDomReady() {
        initSwipers();
    }

    if (root) {
        root.myArticlesInitSwipers = initSwipers;
    }

    if (doc) {
        if (doc.readyState !== 'loading') {
            handleDomReady();
        } else {
            doc.addEventListener('DOMContentLoaded', handleDomReady);
        }
    }

    return {
        preloadNeighbouringSlides: preloadNeighbouringSlides,
        initSwipers: initSwipers,
        initSwiperForWrapper: initSwiperForWrapper,
        collectWrappers: collectWrappers,
        getInstanceId: getInstanceId,
    };
});
