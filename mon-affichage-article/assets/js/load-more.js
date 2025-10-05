// Fichier: assets/js/load-more.js
(function ($) {
    'use strict';

    var loadMoreSettings = (typeof myArticlesLoadMore !== 'undefined') ? myArticlesLoadMore : {};
    var pendingNonceDeferred = null;

    var INSTRUMENTATION_DEFAULTS = {
        enabled: false,
        channel: 'console',
        fetchUrl: ''
    };

    function getInstrumentationSettings() {
        var config = loadMoreSettings && typeof loadMoreSettings.instrumentation === 'object'
            ? loadMoreSettings.instrumentation
            : null;

        if (!config) {
            return INSTRUMENTATION_DEFAULTS;
        }

        var channel = typeof config.channel === 'string' ? config.channel : INSTRUMENTATION_DEFAULTS.channel;
        var enabled = !!config.enabled;
        var fetchUrl = typeof config.fetchUrl === 'string' ? config.fetchUrl : '';

        if (!fetchUrl && loadMoreSettings && typeof loadMoreSettings.restRoot === 'string') {
            fetchUrl = loadMoreSettings.restRoot.replace(/\/+$/, '') + '/my-articles/v1/track';
        }

        return {
            enabled: enabled,
            channel: channel,
            fetchUrl: fetchUrl,
            callback: typeof config.callback === 'function' ? config.callback : null
        };
    }

    function dispatchCustomEvent(eventName, detail) {
        if (typeof window === 'undefined') {
            return;
        }

        var eventDetail = detail || {};
        var customEvent;

        try {
            if (typeof window.CustomEvent === 'function') {
                customEvent = new CustomEvent(eventName, { detail: eventDetail });
            } else if (typeof document !== 'undefined' && document && typeof document.createEvent === 'function') {
                customEvent = document.createEvent('CustomEvent');
                customEvent.initCustomEvent(eventName, false, false, eventDetail);
            }

            if (customEvent && typeof window.dispatchEvent === 'function') {
                window.dispatchEvent(customEvent);
            }
        } catch (error) {
            if (typeof console !== 'undefined' && typeof console.error === 'function') {
                console.error(error);
            }
        }
    }

    function runEventCallbacks(eventName, detail) {
        if (loadMoreSettings && typeof loadMoreSettings.onEvent === 'function') {
            try {
                loadMoreSettings.onEvent(eventName, detail);
            } catch (error) {
                if (typeof console !== 'undefined' && typeof console.error === 'function') {
                    console.error(error);
                }
            }
        }

        var instrumentation = getInstrumentationSettings();
        if (instrumentation.callback) {
            try {
                instrumentation.callback(eventName, detail);
            } catch (error) {
                if (typeof console !== 'undefined' && typeof console.error === 'function') {
                    console.error(error);
                }
            }
        }
    }

    function routeInstrumentation(eventName, detail) {
        var instrumentation = getInstrumentationSettings();

        if (!instrumentation.enabled) {
            return;
        }

        var payload = {
            event: eventName,
            detail: detail
        };

        if (instrumentation.channel === 'dataLayer') {
            if (typeof window !== 'undefined') {
                window.dataLayer = window.dataLayer || [];
                try {
                    window.dataLayer.push(payload);
                } catch (error) {
                    if (typeof console !== 'undefined' && typeof console.error === 'function') {
                        console.error(error);
                    }
                }
            }

            return;
        }

        if (instrumentation.channel === 'fetch') {
            if (typeof window !== 'undefined' && typeof window.fetch === 'function' && instrumentation.fetchUrl) {
                var headers = { 'Content-Type': 'application/json' };
                if (loadMoreSettings && typeof loadMoreSettings.restNonce === 'string' && loadMoreSettings.restNonce.length) {
                    headers['X-WP-Nonce'] = loadMoreSettings.restNonce;
                }

                try {
                    window.fetch(instrumentation.fetchUrl, {
                        method: 'POST',
                        headers: headers,
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    }).catch(function () {
                        return null;
                    });
                } catch (error) {
                    if (typeof console !== 'undefined' && typeof console.error === 'function') {
                        console.error(error);
                    }
                }
            }

            return;
        }

        if (typeof console !== 'undefined' && typeof console.log === 'function') {
            console.log('[my-articles]', eventName, detail);
        }
    }

    function emitLoadMoreInteraction(phase, detail) {
        var payload = $.extend({ phase: phase }, detail || {});
        var eventName = 'my-articles:load-more';

        dispatchCustomEvent(eventName, payload);
        runEventCallbacks(eventName, payload);
        routeInstrumentation(eventName, payload);
    }

    function getNonceEndpoint(settings) {
        if (settings && typeof settings.nonceEndpoint === 'string' && settings.nonceEndpoint.length > 0) {
            return settings.nonceEndpoint;
        }

        if (settings && typeof settings.restRoot === 'string' && settings.restRoot.length > 0) {
            return settings.restRoot.replace(/\/+$/, '') + '/my-articles/v1/nonce';
        }

        return '';
    }

    function extractNonceFromResponse(response) {
        if (!response || typeof response !== 'object') {
            return '';
        }

        if (typeof response.nonce === 'string' && response.nonce.length > 0) {
            return response.nonce;
        }

        if (response.data && typeof response.data.nonce === 'string' && response.data.nonce.length > 0) {
            return response.data.nonce;
        }

        return '';
    }

    function applyRefreshedNonce(settings, nonce) {
        if (!nonce) {
            return;
        }

        if (settings && typeof settings === 'object') {
            settings.restNonce = nonce;
        }

        if (typeof window !== 'undefined') {
            var loadMoreSettingsGlobal = window.myArticlesLoadMore;
            if (loadMoreSettingsGlobal && typeof loadMoreSettingsGlobal === 'object') {
                loadMoreSettingsGlobal.restNonce = nonce;
            }

            var filterSettingsGlobal = window.myArticlesFilter;
            if (filterSettingsGlobal && typeof filterSettingsGlobal === 'object') {
                filterSettingsGlobal.restNonce = nonce;
            }
        }
    }

    function refreshRestNonce(settings) {
        if (pendingNonceDeferred) {
            return pendingNonceDeferred.promise();
        }

        var deferred = $.Deferred();
        pendingNonceDeferred = deferred;

        var endpoint = getNonceEndpoint(settings);

        if (!endpoint) {
            deferred.reject(new Error('Missing nonce endpoint'));
            pendingNonceDeferred = null;

            return deferred.promise();
        }

        $.ajax({
            url: endpoint,
            type: 'GET',
            success: function (response) {
                var nonce = extractNonceFromResponse(response);

                if (nonce) {
                    applyRefreshedNonce(settings, nonce);
                    deferred.resolve(nonce);

                    return;
                }

                deferred.reject(new Error('Invalid nonce payload'));
            },
            error: function () {
                deferred.reject(new Error('Nonce request failed'));
            },
            complete: function () {
                pendingNonceDeferred = null;
            }
        });

        return deferred.promise();
    }

    function isInvalidNonceResponse(jqXHR, response) {
        var payload = response || null;

        if (!payload && jqXHR && jqXHR.responseJSON && typeof jqXHR.responseJSON === 'object') {
            payload = jqXHR.responseJSON;
        }

        if (!payload && jqXHR && typeof jqXHR.responseText === 'string') {
            try {
                payload = JSON.parse(jqXHR.responseText);
            } catch (error) {
                payload = null;
            }
        }

        if (!payload || typeof payload !== 'object') {
            return false;
        }

        if (typeof payload.code === 'string' && payload.code === 'my_articles_invalid_nonce') {
            return true;
        }

        if (payload.data && typeof payload.data.code === 'string' && payload.data.code === 'my_articles_invalid_nonce') {
            return true;
        }

        return false;
    }

    function getFeedbackElement(wrapper) {
        var feedback = wrapper.find('.my-articles-feedback');

        if (!feedback.length) {
            feedback = $('<div class="my-articles-feedback" aria-live="polite"></div>').hide();

            var nav = wrapper.find('.my-articles-filter-nav').first();
            if (nav.length) {
                nav.after(feedback);
            } else {
                wrapper.prepend(feedback);
            }
        }

        return feedback;
    }

    function clearFeedback(wrapper) {
        var feedback = wrapper.find('.my-articles-feedback');
        if (feedback.length) {
            feedback.removeClass('is-error')
                .removeAttr('role')
                .attr('aria-live', 'polite')
                .text('')
                .hide();
        }
    }

    function showError(wrapper, message) {
        var feedback = getFeedbackElement(wrapper);
        feedback.text(message)
            .addClass('is-error')
            .attr('role', 'alert')
            .attr('aria-live', 'assertive')
            .show();
    }

    function normalizeSearchValue(value) {
        if (typeof value === 'string') {
            return value;
        }

        if (value === null || typeof value === 'undefined') {
            return '';
        }

        return String(value);
    }

    function sanitizeSearchValue(value) {
        var normalized = normalizeSearchValue(value);

        if (normalized.length === 0) {
            return '';
        }

        return normalized.replace(/\s+/g, ' ').trim();
    }

    function sanitizeSortValue(value) {
        var normalized = '';

        if (typeof value === 'string') {
            normalized = value;
        } else if (value === null || typeof value === 'undefined') {
            normalized = '';
        } else {
            normalized = String(value);
        }

        normalized = normalized.trim();

        if (!normalized) {
            return '';
        }

        return normalized.replace(/[^a-z0-9_\-]+/gi, '').toLowerCase();
    }

    function updateInstanceQueryParams(instanceId, params) {
        if (typeof window === 'undefined' || !window.history) {
            return;
        }

        var historyApi = window.history;
        var historyMethod = null;

        if (typeof historyApi.replaceState === 'function') {
            historyMethod = 'replaceState';
        } else if (typeof historyApi.pushState === 'function') {
            historyMethod = 'pushState';
        }

        if (!historyMethod) {
            return;
        }

        try {
            var currentUrl = window.location && window.location.href ? window.location.href : '';
            if (!currentUrl) {
                return;
            }

            var url = new URL(currentUrl);

            Object.keys(params || {}).forEach(function (key) {
                var value = params[key];
                if (value === null || typeof value === 'undefined' || value === '') {
                    url.searchParams.delete(key);
                } else {
                    url.searchParams.set(key, value);
                }
            });

            historyApi[historyMethod](null, '', url.toString());
        } catch (error) {
            // Silencieusement ignorer les erreurs pour les navigateurs ne supportant pas l'API
        }
    }

    function formatLoadMoreTemplate(template, count) {
        if (typeof template !== 'string' || template.length === 0) {
            return '';
        }

        if (template.indexOf('%d') !== -1) {
            return template.replace(/%d/g, String(count));
        }

        if (template.indexOf('%s') !== -1) {
            return template.replace(/%s/g, String(count));
        }

        return template;
    }

    function resolveLoadMoreLabel(key, fallback) {
        if (loadMoreSettings && Object.prototype.hasOwnProperty.call(loadMoreSettings, key)) {
            var value = loadMoreSettings[key];
            if (typeof value === 'string' && value.length > 0) {
                return value;
            }
        }

        return fallback;
    }

    function buildLoadMoreFeedbackMessage(totalCount, addedCount) {
        var fallbackTotalSingle = '%s article affiché au total.';
        var fallbackTotalPlural = '%s articles affichés au total.';
        var fallbackAddedSingle = '%s article ajouté.';
        var fallbackAddedPlural = '%s articles ajoutés.';
        var fallbackNoAdditional = 'Aucun article supplémentaire.';
        var fallbackNone = 'Aucun article à afficher.';

        var totalSingleLabel = resolveLoadMoreLabel('totalSingle', fallbackTotalSingle);
        var totalPluralLabel = resolveLoadMoreLabel('totalPlural', fallbackTotalPlural);
        var addedSingleLabel = resolveLoadMoreLabel('addedSingle', fallbackAddedSingle);
        var addedPluralLabel = resolveLoadMoreLabel('addedPlural', fallbackAddedPlural);
        var noAdditionalLabel = resolveLoadMoreLabel('noAdditional', fallbackNoAdditional);
        var noneLabel = resolveLoadMoreLabel('none', fallbackNone);

        var totalLabel = '';
        if (totalCount > 0) {
            if (totalCount === 1) {
                totalLabel = formatLoadMoreTemplate(totalSingleLabel, totalCount) || formatLoadMoreTemplate(fallbackTotalSingle, totalCount);
                if (!totalLabel) {
                    totalLabel = fallbackTotalSingle.replace('%s', String(totalCount));
                }
            } else {
                totalLabel = formatLoadMoreTemplate(totalPluralLabel, totalCount) || formatLoadMoreTemplate(fallbackTotalPlural, totalCount);
                if (!totalLabel) {
                    totalLabel = fallbackTotalPlural.replace('%s', String(totalCount));
                }
            }
        }

        if (addedCount > 0) {
            var addedLabel = '';
            if (addedCount === 1) {
                addedLabel = formatLoadMoreTemplate(addedSingleLabel, addedCount) || formatLoadMoreTemplate(fallbackAddedSingle, addedCount);
                if (!addedLabel) {
                    addedLabel = fallbackAddedSingle.replace('%s', String(addedCount));
                }
            } else {
                addedLabel = formatLoadMoreTemplate(addedPluralLabel, addedCount) || formatLoadMoreTemplate(fallbackAddedPlural, addedCount);
                if (!addedLabel) {
                    addedLabel = fallbackAddedPlural.replace('%s', String(addedCount));
                }
            }

            if (totalLabel) {
                return addedLabel + ' ' + totalLabel;
            }

            return addedLabel;
        }

        if (totalCount > 0) {
            if (totalLabel) {
                if (noAdditionalLabel.slice(-1) === ' ') {
                    return noAdditionalLabel + totalLabel;
                }

                return noAdditionalLabel + ' ' + totalLabel;
            }

            return noAdditionalLabel;
        }

        return noneLabel || fallbackNone;
    }

    function focusElement($element) {
        if (!$element || !$element.length) {
            return false;
        }

        var focusable = $element;

        if (!$element.is('a, button, input, select, textarea, [tabindex], [contenteditable="true"]')) {
            focusable = $element.find('a, button, input, select, textarea, [tabindex], [contenteditable="true"]').filter(':visible').first();

            if (!focusable.length) {
                focusable = $element;

                if (!focusable.attr('tabindex')) {
                    focusable.attr('tabindex', '-1');
                }
            }
        }

        if (!focusable.length || !focusable.is(':visible')) {
            return false;
        }

        focusable.trigger('focus');

        return true;
    }

    function findSectionTitle(wrapper) {
        var selectors = '[data-my-articles-role="section-title"], .my-articles-section-title, .my-articles-title';
        var sectionTitle = wrapper.find(selectors).filter(function () {
            return $(this).closest('.my-article-item').length === 0;
        }).first();

        if (sectionTitle.length) {
            return sectionTitle;
        }

        sectionTitle = wrapper.children('h1, h2, h3, h4, h5, h6').filter(function () {
            return $(this).closest('.my-article-item').length === 0;
        }).first();

        return sectionTitle;
    }

    function focusOnFirstArticleOrTitle(wrapper, contentArea, preferredArticle) {
        var targetArticle = null;

        if (preferredArticle && preferredArticle.length) {
            targetArticle = preferredArticle;
        } else {
            targetArticle = contentArea.find('.my-article-item').first();
        }

        if (targetArticle && targetArticle.length && focusElement(targetArticle)) {
            return;
        }

        var sectionTitle = findSectionTitle(wrapper);

        if (sectionTitle && sectionTitle.length && focusElement(sectionTitle)) {
            return;
        }

        focusElement(wrapper);
    }

    var AUTO_STATE_KEY = 'myArticlesAutoState';
    var AUTO_DISABLED_KEY = 'myArticlesAutoDisabled';
    var AUTO_THROTTLE_DELAY = 600;

    function isAutoLoadRequested(button) {
        if (!button || !button.length) {
            return false;
        }

        var attribute = button.attr('data-auto-load');

        if (typeof attribute === 'undefined' || attribute === null) {
            return false;
        }

        if (typeof attribute === 'string') {
            var normalized = attribute.toLowerCase();

            return normalized === '1' || normalized === 'true' || normalized === 'yes';
        }

        return !!attribute;
    }

    function hasMorePages(button) {
        if (!button || !button.length) {
            return false;
        }

        var nextPage = parseInt(button.data('paged'), 10) || 0;

        if (!nextPage || nextPage <= 0) {
            return false;
        }

        var totalPages = parseInt(button.data('total-pages'), 10);

        if (!totalPages || isNaN(totalPages)) {
            return true;
        }

        return nextPage <= totalPages;
    }

    function isButtonReadyForAuto(button, state) {
        if (!button || !button.length) {
            return false;
        }

        var shouldCheckVisibility = true;

        if (state && state.usingScrollFallback === false) {
            shouldCheckVisibility = false;
        }

        if (shouldCheckVisibility && typeof button.is === 'function' && !button.is(':visible')) {
            return false;
        }

        if (button.prop('disabled')) {
            return false;
        }

        return hasMorePages(button);
    }

    function getAutoState(button, createIfMissing) {
        if (!button || !button.length) {
            return null;
        }

        var state = button.data(AUTO_STATE_KEY);

        if (!state && createIfMissing) {
            state = {
                throttleDelay: AUTO_THROTTLE_DELAY,
                lastTrigger: 0,
                isFetching: false,
                eventNamespace: '.myArticlesAutoLoad' + Math.floor(Math.random() * 1000000),
                usingScrollFallback: false,
                observer: null,
                scrollHandler: null,
            };

            button.data(AUTO_STATE_KEY, state);
        }

        return state;
    }

    function detachScrollFallback(state) {
        if (!state || !state.eventNamespace) {
            return;
        }

        if (state.scrollHandler) {
            $(window).off('scroll' + state.eventNamespace, state.scrollHandler);
            $(window).off('resize' + state.eventNamespace, state.scrollHandler);
            state.scrollHandler = null;
        }
    }

    function disableAutoLoad(button, reason, persist) {
        if (!button || !button.length) {
            return;
        }

        var state = getAutoState(button, false);

        if (state) {
            if (state.observer && typeof state.observer.disconnect === 'function') {
                state.observer.disconnect();
            }

            detachScrollFallback(state);

            button.removeData(AUTO_STATE_KEY);
        }

        button.attr('data-auto-active', '0');

        if (persist) {
            button.data(AUTO_DISABLED_KEY, reason || true);
        }
    }

    function triggerAutoLoad(button, state) {
        if (!button || !button.length) {
            return;
        }

        state = state || getAutoState(button, false);

        if (!state || state.isFetching) {
            return;
        }

        if (!isButtonReadyForAuto(button, state)) {
            return;
        }

        var now = Date.now();

        if (state.lastTrigger && now - state.lastTrigger < state.throttleDelay) {
            return;
        }

        state.lastTrigger = now;

        requestLoadMore(button, { auto: true });
    }

    function isElementNearViewport(element, offset) {
        if (!element || typeof element.getBoundingClientRect !== 'function') {
            return false;
        }

        if (typeof window === 'undefined') {
            return false;
        }

        var rect = element.getBoundingClientRect();
        var winHeight = window.innerHeight || (document.documentElement && document.documentElement.clientHeight) || 0;
        var winWidth = window.innerWidth || (document.documentElement && document.documentElement.clientWidth) || 0;
        var margin = typeof offset === 'number' ? offset : 200;

        return (
            rect.bottom >= -margin &&
            rect.top <= winHeight + margin &&
            rect.right >= -margin &&
            rect.left <= winWidth + margin
        );
    }

    function setupIntersectionObserver(button, state) {
        if (typeof window === 'undefined' || typeof window.IntersectionObserver !== 'function') {
            return false;
        }

        var target = button && button.length ? button.get(0) : null;

        if (!target) {
            return false;
        }

        try {
            state.observer = new window.IntersectionObserver(function (entries) {
                for (var i = 0; i < entries.length; i += 1) {
                    if (entries[i] && entries[i].isIntersecting) {
                        triggerAutoLoad(button, state);
                        break;
                    }
                }
            }, { rootMargin: '200px 0px', threshold: 0.05 });

            state.observer.observe(target);
        } catch (error) {
            state.observer = null;
            return false;
        }

        state.usingScrollFallback = false;

        return true;
    }

    function setupScrollFallback(button, state) {
        if (typeof window === 'undefined') {
            return false;
        }

        var target = button && button.length ? button.get(0) : null;

        if (!target) {
            return false;
        }

        state.scrollHandler = function () {
            if (!isButtonReadyForAuto(button, state)) {
                return;
            }

            if (!isElementNearViewport(target, 220)) {
                return;
            }

            triggerAutoLoad(button, state);
        };

        $(window).on('scroll' + state.eventNamespace, state.scrollHandler);
        $(window).on('resize' + state.eventNamespace, state.scrollHandler);

        state.usingScrollFallback = true;

        state.scrollHandler();

        return true;
    }

    function setupAutoLoad(button) {
        if (!button || !button.length) {
            return;
        }

        if (!isAutoLoadRequested(button)) {
            disableAutoLoad(button);
            return;
        }

        if (button.data(AUTO_DISABLED_KEY)) {
            return;
        }

        if (!hasMorePages(button)) {
            disableAutoLoad(button, 'completed', true);
            button.hide();
            return;
        }

        var state = getAutoState(button, false);

        if (state && state.isInitialized) {
            return;
        }

        state = getAutoState(button, true);

        if (setupIntersectionObserver(button, state) || setupScrollFallback(button, state)) {
            state.isInitialized = true;
            button.attr('data-auto-active', '1');
            return;
        }

        disableAutoLoad(button, 'unsupported', true);
    }

    function initializeAutoLoadButtons(context) {
        var $context = context ? $(context) : $(document);

        var $buttons = $context.filter('.my-articles-load-more-btn');
        $buttons = $buttons.add($context.find('.my-articles-load-more-btn'));

        $buttons.each(function () {
            setupAutoLoad($(this));
        });
    }

    function requestLoadMore(button, context) {
        context = context || {};

        if (!button || !button.length) {
            return;
        }

        var isAutoTrigger = !!context.auto;

        if (!hasMorePages(button)) {
            disableAutoLoad(button, 'completed', true);
            button.hide();
            return;
        }

        if (button.prop('disabled')) {
            return;
        }

        var state = getAutoState(button, false);
        if (isAutoTrigger && state && state.isFetching) {
            return;
        }

        var wrapper = button.closest('.my-articles-wrapper');
        var contentArea = wrapper.find('.my-articles-grid-content, .my-articles-list-content, .swiper-wrapper');

        var originalButtonText = button.data('original-text');
        if (!originalButtonText) {
            originalButtonText = button.text();
            button.data('original-text', originalButtonText);
        }

        var previousArticleCount = contentArea.find('.my-article-item').length;

        var instanceId = button.data('instance-id');
        var paged = parseInt(button.data('paged'), 10) || 0;
        var totalPages = parseInt(button.data('total-pages'), 10) || 0;
        var pinnedIds = button.data('pinned-ids');
        var category = button.data('category');
        var searchValue = sanitizeSearchValue(button.data('search'));
        var sortValue = sanitizeSortValue(button.data('sort'));
        var requestedPage = paged;

        if (!paged || paged <= 0) {
            disableAutoLoad(button, 'completed', true);
            button.hide();
            return;
        }

        var requestUrl = (loadMoreSettings && typeof loadMoreSettings.endpoint === 'string') ? loadMoreSettings.endpoint : '';

        if (!requestUrl && loadMoreSettings && typeof loadMoreSettings.restRoot === 'string') {
            requestUrl = loadMoreSettings.restRoot.replace(/\/+$/, '') + '/my-articles/v1/load-more';
        }

        if (!requestUrl) {
            instrumentationDetail.errorMessage = 'missing-endpoint';
            emitLoadMoreInteraction('error', instrumentationDetail);
            return;
        }

        var fallbackMessage = loadMoreSettings.errorText || 'Une erreur est survenue. Veuillez réessayer plus tard.';
        var hasRetried = false;

        var instrumentationDetail = {
            instanceId: instanceId,
            requestedPage: requestedPage,
            totalPages: totalPages,
            category: typeof category === 'string' ? category : '',
            search: searchValue,
            sort: sortValue,
            autoTriggered: isAutoTrigger,
            requestUrl: requestUrl,
            hadNonceRefresh: false
        };

        if (state) {
            state.isFetching = true;
        }

        function finalizeRequest() {
            if (state) {
                state.isFetching = false;
            }
        }

        function extractErrorMessage(jqXHR, response) {
            if (response && response.data && response.data.message) {
                return response.data.message;
            }

            if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                return jqXHR.responseJSON.data.message;
            }

            if (jqXHR && typeof jqXHR.statusText === 'string' && jqXHR.statusText.length) {
                return jqXHR.statusText;
            }

            return '';
        }

        function extractStatus(jqXHR, response) {
            if (jqXHR && typeof jqXHR.status === 'number') {
                return jqXHR.status;
            }

            if (response && response.data && typeof response.data.status === 'number') {
                return response.data.status;
            }

            if (response && typeof response.status === 'number') {
                return response.status;
            }

            return 0;
        }

        function handleErrorResponse(jqXHR, response) {
            finalizeRequest();

            var errorMessage = extractErrorMessage(jqXHR, response) || fallbackMessage;

            var resetText = loadMoreSettings.loadMoreText || originalButtonText;
            button.text(resetText);
            button.prop('disabled', false);
            showError(wrapper, errorMessage);

            if (isAutoTrigger) {
                disableAutoLoad(button, 'error', true);
            }

            instrumentationDetail.errorMessage = errorMessage;
            instrumentationDetail.status = extractStatus(jqXHR, response);
            instrumentationDetail.hadNonceRefresh = hasRetried;
            emitLoadMoreInteraction('error', instrumentationDetail);

            if (typeof console !== 'undefined' && typeof console.error === 'function') {
                console.error(errorMessage);
            }
        }

        function handleSuccessResponse(response) {
            finalizeRequest();

            if (!response || !response.data) {
                handleErrorResponse(null, response);
                return;
            }

            var responseData = response.data;
            var wrapperElement = (wrapper && wrapper.length) ? wrapper.get(0) : null;

            if (typeof responseData.html !== 'undefined') {
                contentArea.append(responseData.html);
            }

            if (typeof window.myArticlesInitWrappers === 'function') {
                window.myArticlesInitWrappers(wrapperElement);
            }

            if (typeof window.myArticlesInitSwipers === 'function') {
                window.myArticlesInitSwipers(wrapperElement);
            }

            initializeAutoLoadButtons(wrapperElement);

            var totalArticles = contentArea.find('.my-article-item').length;
            var addedCount = totalArticles - previousArticleCount;
            if (addedCount < 0) {
                addedCount = 0;
            }

            var focusArticle = null;
            if (addedCount > 0) {
                focusArticle = contentArea.find('.my-article-item').eq(previousArticleCount);
            } else {
                focusArticle = contentArea.find('.my-article-item').first();
            }

            var feedbackMessage = buildLoadMoreFeedbackMessage(totalArticles, addedCount);
            var feedbackElement = getFeedbackElement(wrapper);
            feedbackElement.removeClass('is-error')
                .removeAttr('role')
                .attr('aria-live', 'polite')
                .text(feedbackMessage)
                .show();

            var totalPagesResponse = parseInt(responseData.total_pages, 10);
            if (isNaN(totalPagesResponse)) {
                totalPagesResponse = totalPages;
            }

            var nextPageResponse = parseInt(responseData.next_page, 10);
            if (isNaN(nextPageResponse)) {
                nextPageResponse = 0;
            }

            instrumentationDetail.totalPages = totalPagesResponse;
            instrumentationDetail.nextPage = nextPageResponse;
            instrumentationDetail.addedCount = addedCount;
            instrumentationDetail.totalArticles = totalArticles;
            instrumentationDetail.pinnedIds = typeof responseData.pinned_ids === 'string' ? responseData.pinned_ids : '';
            instrumentationDetail.hadNonceRefresh = hasRetried;
            instrumentationDetail.errorMessage = '';
            instrumentationDetail.status = 0;
            emitLoadMoreInteraction('success', instrumentationDetail);

            if (!isAutoTrigger) {
                focusOnFirstArticleOrTitle(wrapper, contentArea, focusArticle);
            }

            if (typeof responseData.pinned_ids !== 'undefined') {
                var updatedPinnedIds = responseData.pinned_ids;
                button.data('pinned-ids', updatedPinnedIds);
                button.attr('data-pinned-ids', updatedPinnedIds);
            }

            if (typeof responseData.search_query !== 'undefined') {
                var updatedSearchValue = sanitizeSearchValue(responseData.search_query);
                button.data('search', updatedSearchValue);
                button.attr('data-search', updatedSearchValue);
            }

            if (typeof responseData.sort !== 'undefined') {
                var updatedSortValue = sanitizeSortValue(responseData.sort);
                button.data('sort', updatedSortValue);
                button.attr('data-sort', updatedSortValue);

                if (wrapper && wrapper.length) {
                    wrapper.attr('data-sort', updatedSortValue);
                }
            }

            if (typeof responseData.total_pages !== 'undefined') {
                var serverTotalPages = parseInt(responseData.total_pages, 10);
                if (!isNaN(serverTotalPages)) {
                    totalPages = serverTotalPages;
                    button.data('total-pages', totalPages);
                    button.attr('data-total-pages', totalPages);
                }
            }

            var nextPageFromServer = null;
            if (typeof responseData.next_page !== 'undefined') {
                var parsedNext = parseInt(responseData.next_page, 10);
                if (!isNaN(parsedNext)) {
                    nextPageFromServer = parsedNext;
                }
            }

            var loadMoreText = loadMoreSettings.loadMoreText || originalButtonText;
            button.text(loadMoreText);

            if (instanceId && requestedPage > 0) {
                var historyParams = {};
                historyParams['paged_' + instanceId] = String(requestedPage);
                updateInstanceQueryParams(instanceId, historyParams);
            }

            if (nextPageFromServer !== null) {
                paged = nextPageFromServer;
                button.data('paged', paged);
                button.attr('data-paged', paged);

                if (paged <= 0) {
                    button.hide();
                    disableAutoLoad(button, 'completed', true);
                    button.prop('disabled', false);
                    return;
                }
            } else {
                var newPage = paged + 1;
                paged = newPage;
                button.data('paged', newPage);
                button.attr('data-paged', newPage);
            }

            if (!totalPages || paged > totalPages) {
                button.hide();
                disableAutoLoad(button, 'completed', true);
                button.prop('disabled', false);
                return;
            }

            button.prop('disabled', false);

            if (!isAutoTrigger) {
                setupAutoLoad(button);
            }
        }

        function sendAjaxRequest() {
            var nonceHeader = loadMoreSettings && loadMoreSettings.restNonce ? loadMoreSettings.restNonce : '';

            $.ajax({
                url: requestUrl,
                type: 'POST',
                headers: {
                    'X-WP-Nonce': nonceHeader
                },
                data: {
                    instance_id: instanceId,
                    paged: paged,
                    pinned_ids: pinnedIds,
                    category: category,
                    search: searchValue,
                    sort: sortValue
                },
                beforeSend: function () {
                    var loadingText = loadMoreSettings.loadingText || originalButtonText;
                    button.text(loadingText);
                    button.prop('disabled', true);
                    if (wrapper && wrapper.length) {
                        wrapper.attr('aria-busy', 'true');
                        wrapper.addClass('is-loading');
                    }
                    clearFeedback(wrapper);
                    emitLoadMoreInteraction('request', instrumentationDetail);
                },
                success: function (response) {
                    if (response && response.success) {
                        handleSuccessResponse(response);
                        return;
                    }

                    if (!hasRetried && isInvalidNonceResponse(null, response)) {
                        hasRetried = true;
                        instrumentationDetail.hadNonceRefresh = true;
                        refreshRestNonce(loadMoreSettings)
                            .done(function () {
                                sendAjaxRequest();
                            })
                            .fail(function () {
                                handleErrorResponse(null, response);
                            });

                        return;
                    }

                    handleErrorResponse(null, response);
                },
                error: function (jqXHR) {
                    if (!hasRetried && isInvalidNonceResponse(jqXHR)) {
                        hasRetried = true;
                        instrumentationDetail.hadNonceRefresh = true;
                        refreshRestNonce(loadMoreSettings)
                            .done(function () {
                                sendAjaxRequest();
                            })
                            .fail(function () {
                                handleErrorResponse(jqXHR);
                            });

                        return;
                    }

                    handleErrorResponse(jqXHR);
                },
                complete: function () {
                    if (wrapper && wrapper.length) {
                        wrapper.attr('aria-busy', 'false');
                        wrapper.removeClass('is-loading');
                    }
                }
            });
        }

        sendAjaxRequest();
    }

    $(document).on('click', '.my-articles-load-more-btn', function (e) {
        e.preventDefault();

        var button = $(this);
        disableAutoLoad(button, 'manual', true);
        requestLoadMore(button, { userInitiated: true });
    });

    $(function () {
        initializeAutoLoadButtons(document);
    });

    if (typeof window !== 'undefined') {
        window.myArticlesRefreshAutoLoadButtons = initializeAutoLoadButtons;
    }

})(jQuery);
