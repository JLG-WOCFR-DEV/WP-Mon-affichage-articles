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

    function escapeAttribute(value) {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function formatMessage(template, replacements) {
        if (typeof template !== 'string' || !template) {
            return '';
        }

        return template.replace(/{{\s*(\w+)\s*}}/g, function (match, key) {
            if (Object.prototype.hasOwnProperty.call(replacements, key)) {
                return String(replacements[key]);
            }

            return match;
        });
    }

    function getIdFromSelector(selector) {
        if (typeof selector !== 'string') {
            return '';
        }

        const trimmed = selector.trim();
        if (!trimmed) {
            return '';
        }

        if ('#' === trimmed.charAt(0)) {
            return trimmed.slice(1);
        }

        return trimmed;
    }

    function applyInert(slide, shouldInert) {
        if (!slide) {
            return;
        }

        if (shouldInert) {
            slide.setAttribute('inert', '');
        } else {
            slide.removeAttribute('inert');
        }

        if ('inert' in slide) {
            try {
                slide.inert = shouldInert;
            } catch (error) {
                // Ignore failures when the property is read-only.
            }
        }
    }

    function updateSlideAccessibility(swiper, settings) {
        if (!swiper) {
            return;
        }

        const slides = ensureArray(swiper.slides);
        const totalSlides = slides.reduce(function (carry, slide) {
            if (!slide || typeof slide.getAttribute !== 'function') {
                return carry;
            }

            const candidate = Number(slide.getAttribute('data-slide-position'));
            if (!Number.isNaN(candidate) && candidate > carry) {
                return candidate;
            }

            return carry;
        }, 0) || slides.length;
        const labelTemplate = settings && settings.a11y_slide_label_message ? settings.a11y_slide_label_message : '';

        slides.forEach(function (slide, index) {
            if (!slide) {
                return;
            }

            const isActive =
                (slide.classList && slide.classList.contains('swiper-slide-active')) ||
                (slide.classList && slide.classList.contains('swiper-slide-duplicate-active'));
            const positionAttribute = slide.getAttribute && slide.getAttribute('data-slide-position');
            const numericPosition = Number(positionAttribute);
            const logicalIndex = !Number.isNaN(numericPosition) && numericPosition > 0 ? numericPosition : index + 1;
            slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');

            if (isActive) {
                slide.removeAttribute('tabindex');
                slide.removeAttribute('data-my-articles-inert');
            } else {
                slide.setAttribute('tabindex', '-1');
                slide.setAttribute('data-my-articles-inert', 'true');
            }

            applyInert(slide, !isActive);

            if (labelTemplate) {
                const label = formatMessage(labelTemplate, {
                    index: logicalIndex,
                    slidesLength: totalSlides,
                });

                if (label) {
                    slide.setAttribute('aria-label', label);
                }
            }
        });
    }

    function updatePaginationState(swiper) {
        if (!swiper || !swiper.pagination) {
            return;
        }

        const bullets = ensureArray(swiper.pagination.bullets);
        const activeClass = swiper.params && swiper.params.pagination ? swiper.params.pagination.bulletActiveClass : '';
        const activeIndex = typeof swiper.realIndex === 'number' ? swiper.realIndex : swiper.activeIndex || 0;

        bullets.forEach(function (bullet, index) {
            if (!bullet) {
                return;
            }

            const isActive = activeClass
                ? bullet.classList && bullet.classList.contains(activeClass)
                : index === activeIndex;

            bullet.setAttribute('aria-selected', isActive ? 'true' : 'false');

            if (isActive) {
                bullet.setAttribute('aria-current', 'true');
                bullet.removeAttribute('tabindex');
            } else {
                bullet.removeAttribute('aria-current');
                bullet.setAttribute('tabindex', '-1');
            }
        });
    }

    function getLogicalSlides(swiper) {
        const slides = ensureArray(swiper && swiper.slides);
        return slides.filter(function (slide) {
            if (!slide || !slide.classList) {
                return false;
            }

            if (slide.classList.contains('swiper-slide-duplicate')) {
                return false;
            }

            return true;
        });
    }

    function setNavigationButtonState(button, disabled) {
        if (!button || typeof button.setAttribute !== 'function') {
            return;
        }

        const isDisabled = !!disabled;
        button.setAttribute('aria-disabled', isDisabled ? 'true' : 'false');

        if (isDisabled) {
            button.setAttribute('tabindex', '-1');
            button.setAttribute('data-my-articles-nav-disabled', 'true');
            button.setAttribute('disabled', 'disabled');

            try {
                button.disabled = true;
            } catch (error) {
                // Ignore assignment failures (e.g. read-only properties).
            }

            return;
        }

        button.removeAttribute('tabindex');
        button.removeAttribute('data-my-articles-nav-disabled');
        button.removeAttribute('disabled');

        if (typeof button.disabled === 'boolean') {
            button.disabled = false;
        }
    }

    function updateNavigationState(swiper) {
        if (!swiper || !swiper.navigation) {
            return;
        }

        const loopEnabled = !!(swiper.params && swiper.params.loop);
        const logicalSlides = getLogicalSlides(swiper);
        const hasMultipleSlides = logicalSlides.length > 1;

        const activeIndex = typeof swiper.realIndex === 'number'
            ? swiper.realIndex
            : typeof swiper.activeIndex === 'number'
                ? swiper.activeIndex
                : 0;

        const atBeginning = typeof swiper.isBeginning === 'boolean' ? swiper.isBeginning : activeIndex <= 0;
        const atEnd = typeof swiper.isEnd === 'boolean'
            ? swiper.isEnd
            : logicalSlides.length > 0
                ? activeIndex >= logicalSlides.length - 1
                : true;

        const disablePrev = !hasMultipleSlides || (!loopEnabled && atBeginning);
        const disableNext = !hasMultipleSlides || (!loopEnabled && atEnd);

        ensureArray(swiper.navigation.prevEl).forEach(function (button) {
            setNavigationButtonState(button, disablePrev);
        });

        ensureArray(swiper.navigation.nextEl).forEach(function (button) {
            setNavigationButtonState(button, disableNext);
        });
    }

    function handlePaginationKeydown(swiper, event) {
        if (!event || !swiper) {
            return;
        }

        const key = event.key || event.keyCode;
        const bullets = ensureArray(swiper.pagination && swiper.pagination.bullets);
        if (!bullets.length) {
            return;
        }

        const activeIndex = typeof swiper.realIndex === 'number' ? swiper.realIndex : swiper.activeIndex || 0;
        let targetIndex = activeIndex;

        if (key === 'ArrowRight' || key === 'Right' || key === 39) {
            targetIndex = activeIndex + 1;
        } else if (key === 'ArrowLeft' || key === 'Left' || key === 37) {
            targetIndex = activeIndex - 1;
        } else if (key === 'Home' || key === 36) {
            targetIndex = 0;
        } else if (key === 'End' || key === 35) {
            targetIndex = bullets.length - 1;
        } else {
            return;
        }

        if (targetIndex < 0) {
            targetIndex = 0;
        }

        if (targetIndex >= bullets.length) {
            targetIndex = bullets.length - 1;
        }

        event.preventDefault();

        const bullet = bullets[targetIndex];
        if (bullet && typeof bullet.click === 'function') {
            bullet.click();
        } else {
            swiper.slideTo(targetIndex);
        }

        const focusTarget = bullets[targetIndex];
        if (focusTarget && typeof focusTarget.focus === 'function') {
            focusTarget.focus();
        }
    }

    function enhancePagination(swiper, settings) {
        if (!swiper || !swiper.pagination) {
            return;
        }

        const paginationEl = swiper.pagination.el;
        if (!paginationEl) {
            return;
        }

        if (!paginationEl.hasAttribute('role')) {
            paginationEl.setAttribute('role', 'tablist');
        }

        paginationEl.setAttribute('aria-orientation', 'horizontal');

        const bullets = ensureArray(swiper.pagination.bullets);
        const sliderId = getIdFromSelector(settings && settings.controlled_slider_selector ? settings.controlled_slider_selector : '');
        const logicalSlides = getLogicalSlides(swiper);
        const totalSlides = logicalSlides.length || bullets.length || (swiper.slides ? ensureArray(swiper.slides).length : 0);
        const labelTemplate = settings && settings.a11y_pagination_bullet_message ? settings.a11y_pagination_bullet_message : '';
        const fallbackTemplate = settings && settings.a11y_slide_label_message ? settings.a11y_slide_label_message : '';

        bullets.forEach(function (bullet, index) {
            if (!bullet) {
                return;
            }

            bullet.setAttribute('role', 'tab');
            bullet.setAttribute('type', 'button');
            bullet.setAttribute('aria-selected', 'false');
            bullet.setAttribute('tabindex', '-1');
            bullet.setAttribute('data-pagination-index', String(index));

            if (sliderId) {
                bullet.setAttribute('aria-controls', sliderId);
            }

            const currentIndex = index + 1;
            const label = labelTemplate
                ? formatMessage(labelTemplate, { index: currentIndex, slidesLength: totalSlides })
                : fallbackTemplate
                    ? formatMessage(fallbackTemplate, { index: currentIndex, slidesLength: totalSlides })
                    : 'Slide ' + currentIndex;

            if (label) {
                bullet.setAttribute('aria-label', label);
                bullet.setAttribute('title', label);
            }
        });

        if (!paginationEl.hasAttribute('data-my-articles-pagination-listener')) {
            paginationEl.addEventListener('keydown', handlePaginationKeydown.bind(null, swiper));
            paginationEl.setAttribute('data-my-articles-pagination-listener', 'true');
        }

        updatePaginationState(swiper);
    }

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
                  renderBullet: function (index, className) {
                      const template = settings.a11y_pagination_bullet_message || '';
                      const replacements = {
                          index: index + 1,
                      };
                      let label = template ? formatMessage(template, replacements) : '';

                      if (!label) {
                          label = 'Slide ' + String(index + 1);
                      }
                      const sliderId = getIdFromSelector(settings.controlled_slider_selector || '');
                      const controlsAttribute = sliderId
                          ? ' aria-controls="' + escapeAttribute(sliderId) + '"'
                          : '';

                      return (
                          '<button type="button" class="' +
                          escapeAttribute(className) +
                          '" role="tab" data-pagination-index="' +
                          String(index) +
                          '" aria-label="' +
                          escapeAttribute(label || '') +
                          '" aria-selected="false" tabindex="-1"' +
                          controlsAttribute +
                          '></button>'
                      );
                  },
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
                    enhancePagination(this, settings);
                    updateSlideAccessibility(this, settings);
                    updateNavigationState(this);
                },
                slideChange: function () {
                    preloadNeighbouringSlides(this);
                    updatePaginationState(this);
                    updateSlideAccessibility(this, settings);
                    updateNavigationState(this);
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
