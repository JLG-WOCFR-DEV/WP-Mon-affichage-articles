// Fichier: assets/js/responsive-layout.js
(function () {
    'use strict';

    const MIN_CARD_WIDTH_FALLBACK = 220;
    const BREAKPOINTS = [
        { key: 'mobile', minViewport: 0 },
        { key: 'tablet', minViewport: 768 },
        { key: 'desktop', minViewport: 1024 },
        { key: 'ultrawide', minViewport: 1536 },
    ];
    const COLUMN_KEYS = BREAKPOINTS.map(function (breakpoint) {
        return breakpoint.key;
    });
    const SWIPER_BREAKPOINTS = {
        tablet: 768,
        desktop: 1024,
        ultrawide: 1536,
    };

    const managedWrappers = new Set();
    const raf = (typeof window !== 'undefined' && window.requestAnimationFrame)
        ? window.requestAnimationFrame.bind(window)
        : function (callback) {
            return setTimeout(callback, 16);
        };
    let resizeHandle = null;

    function toPositiveInt(value, fallback) {
        const parsed = parseInt(value, 10);
        return parsed > 0 ? parsed : fallback;
    }

    function getConfiguredColumns(wrapper) {
        return {
            mobile: toPositiveInt(wrapper.dataset.colsMobile, 1),
            tablet: toPositiveInt(wrapper.dataset.colsTablet, 1),
            desktop: toPositiveInt(wrapper.dataset.colsDesktop, 1),
            ultrawide: toPositiveInt(wrapper.dataset.colsUltrawide, 1),
        };
    }

    function getBaseMinCardWidth(wrapper) {
        return toPositiveInt(wrapper.dataset.minCardWidth, MIN_CARD_WIDTH_FALLBACK);
    }

    function getActiveBreakpoint(viewportWidth) {
        let active = BREAKPOINTS[0].key;

        BREAKPOINTS.forEach(function (breakpoint) {
            if (viewportWidth >= breakpoint.minViewport) {
                active = breakpoint.key;
            }
        });

        return active;
    }

    function updateSwiperConfiguration(instanceId, effectiveColumns) {
        if (!instanceId) {
            return;
        }

        const settingsName = 'myArticlesSwiperSettings_' + instanceId;
        const settings = window[settingsName];

        if (settings) {
            settings.columns_mobile = effectiveColumns.mobile;
            settings.columns_tablet = effectiveColumns.tablet;
            settings.columns_desktop = effectiveColumns.desktop;
            settings.columns_ultrawide = effectiveColumns.ultrawide;
        }

        if (!window.mySwiperInstances || !window.mySwiperInstances[instanceId]) {
            return;
        }

        const swiper = window.mySwiperInstances[instanceId];
        if (!swiper || !swiper.params) {
            return;
        }

        swiper.params.slidesPerView = effectiveColumns.mobile;
        swiper.params.breakpoints = swiper.params.breakpoints || {};

        Object.keys(SWIPER_BREAKPOINTS).forEach(function (key) {
            const breakpointValue = SWIPER_BREAKPOINTS[key];
            const slides = effectiveColumns[key];

            swiper.params.breakpoints[breakpointValue] = swiper.params.breakpoints[breakpointValue] || {};
            swiper.params.breakpoints[breakpointValue].slidesPerView = slides;
        });

        if (typeof swiper.update === 'function') {
            swiper.update();
        }
    }

    function updateWrapper(wrapper) {
        if (!wrapper) {
            return;
        }

        const width = wrapper.clientWidth;
        if (!width) {
            return;
        }

        const columnsConfig = getConfiguredColumns(wrapper);
        const baseMinWidth = getBaseMinCardWidth(wrapper);
        const baseEffective = Math.max(1, Math.floor(width / baseMinWidth));

        const effectiveColumns = {};
        COLUMN_KEYS.forEach(function (key) {
            const configured = columnsConfig[key];
            const effective = Math.max(1, Math.min(configured, baseEffective));
            effectiveColumns[key] = effective;
            wrapper.style.setProperty('--my-articles-cols-' + key, effective);
        });

        const viewportWidth = window.innerWidth || document.documentElement.clientWidth || width;
        const activeKey = getActiveBreakpoint(viewportWidth);
        const activeColumns = effectiveColumns[activeKey] || effectiveColumns.mobile || 1;
        const derivedMinWidth = Math.max(baseMinWidth, Math.floor(width / activeColumns) || baseMinWidth);

        wrapper.style.setProperty('--my-articles-min-card-width', derivedMinWidth + 'px');
        wrapper.dataset.activeCols = String(activeColumns);

        if (wrapper.classList.contains('my-articles-slideshow')) {
            updateSwiperConfiguration(wrapper.dataset.instanceId, effectiveColumns);
        }
    }

    function scheduleGlobalUpdate() {
        if (resizeHandle !== null) {
            return;
        }

        resizeHandle = raf(function () {
            resizeHandle = null;
            managedWrappers.forEach(updateWrapper);
        });
    }

    function initWrapper(wrapper) {
        if (!wrapper || managedWrappers.has(wrapper)) {
            return;
        }

        managedWrappers.add(wrapper);

        if (typeof ResizeObserver !== 'undefined') {
            const observer = new ResizeObserver(function () {
                updateWrapper(wrapper);
            });
            observer.observe(wrapper);
            wrapper.__myArticlesResizeObserver = observer;
        }

        updateWrapper(wrapper);
    }

    let listenersBound = false;

    function bindGlobalListeners() {
        if (listenersBound) {
            return;
        }

        listenersBound = true;
        window.addEventListener('resize', scheduleGlobalUpdate);
        window.addEventListener('orientationchange', scheduleGlobalUpdate);
    }

    function collectCandidateWrappers(target) {
        const collection = new Set();

        function addFromNode(node) {
            if (!node) {
                return;
            }

            if (node.classList && node.classList.contains('my-articles-wrapper')) {
                collection.add(node);
            }

            if (typeof node.querySelectorAll === 'function') {
                node.querySelectorAll('.my-articles-wrapper').forEach(function (wrapper) {
                    collection.add(wrapper);
                });
            }
        }

        if (!target) {
            document.querySelectorAll('.my-articles-wrapper').forEach(function (wrapper) {
                collection.add(wrapper);
            });
            return Array.from(collection);
        }

        if (target.jquery) {
            target.each(function (index, element) {
                addFromNode(element);
            });
            return Array.from(collection);
        }

        if (typeof target.length === 'number' && typeof target !== 'string') {
            Array.prototype.forEach.call(target, function (item) {
                addFromNode(item);
            });
            return Array.from(collection);
        }

        addFromNode(target);
        return Array.from(collection);
    }

    function initWrappers(target) {
        bindGlobalListeners();

        const wrappers = collectCandidateWrappers(target);
        wrappers.forEach(initWrapper);

        return wrappers.length;
    }

    function handleDomReady() {
        initWrappers();
    }

    window.myArticlesInitWrappers = initWrappers;

    if (document.readyState !== 'loading') {
        handleDomReady();
    } else {
        document.addEventListener('DOMContentLoaded', handleDomReady);
    }
})();
