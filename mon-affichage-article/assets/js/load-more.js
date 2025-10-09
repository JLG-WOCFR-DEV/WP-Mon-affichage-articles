// Fichier: assets/js/load-more.js
(function ($) {
    'use strict';

    var loadMoreSettings = (typeof myArticlesLoadMore !== 'undefined') ? myArticlesLoadMore : {};
    var DEBUG_STORAGE_KEY = 'myArticlesDebug';
    var sharedRuntime = (function () {
        var globalScope = typeof globalThis !== 'undefined' ? globalThis : (typeof window !== 'undefined' ? window : {});
        var shared = globalScope && globalScope.myArticlesShared ? globalScope.myArticlesShared : null;

        if (typeof module === 'object' && module.exports) {
            try {
                shared = require('./shared-runtime');

                if (shared && typeof shared.default === 'object') {
                    shared = shared.default;
                }
            } catch (error) {
                shared = shared || (globalScope && globalScope.myArticlesShared ? globalScope.myArticlesShared : null);
            }
        }

        return shared || {};
    }());

    var eventEmitter = (sharedRuntime && typeof sharedRuntime.createEventEmitter === 'function')
        ? sharedRuntime.createEventEmitter(function () {
            return loadMoreSettings;
        })
        : null;

    var nonceManager = (sharedRuntime && typeof sharedRuntime.createNonceManager === 'function')
        ? sharedRuntime.createNonceManager($, {
            getSettings: function () {
                return loadMoreSettings;
            },
            mirrors: [
                function () {
                    return (typeof window !== 'undefined' && window.myArticlesLoadMore) ? window.myArticlesLoadMore : null;
                },
                function () {
                    return (typeof window !== 'undefined' && window.myArticlesFilter) ? window.myArticlesFilter : null;
                }
            ]
        })
        : null;

    function getStoredDebugConfig() {
        if (typeof window === 'undefined') {
            return null;
        }

        var globalConfig = window.myArticlesDebug;
        if (typeof globalConfig !== 'undefined') {
            return globalConfig;
        }

        try {
            if (window.localStorage && typeof window.localStorage.getItem === 'function') {
                var storedValue = window.localStorage.getItem(DEBUG_STORAGE_KEY);
                if (!storedValue) {
                    return null;
                }

                try {
                    return JSON.parse(storedValue);
                } catch (parseError) {
                    return storedValue;
                }
            }
        } catch (storageError) {
            // Accessing localStorage can throw in some environments (Safari private mode, etc.).
        }

        return null;
    }

    function isDebugEnabled(featureKey) {
        var config = getStoredDebugConfig();

        if (!config) {
            return false;
        }

        if (config === true) {
            return true;
        }

        if (typeof config === 'string') {
            var normalized = config.toLowerCase();
            if (normalized === 'all' || normalized === featureKey) {
                return true;
            }

            var fragments = normalized.split(',');
            for (var i = 0; i < fragments.length; i++) {
                if (fragments[i].trim() === featureKey) {
                    return true;
                }
            }

            return false;
        }

        if (Array.isArray(config)) {
            for (var index = 0; index < config.length; index++) {
                if (String(config[index]).toLowerCase().trim() === featureKey) {
                    return true;
                }
            }

            return false;
        }

        if (typeof config === 'object') {
            if (config[featureKey]) {
                return true;
            }

            if (config.enabled === true) {
                return true;
            }
        }

        return false;
    }

    function debugLog(featureKey, label, detail) {
        if (!isDebugEnabled(featureKey)) {
            return;
        }

        if (typeof console !== 'undefined') {
            var logArgs = ['[my-articles][debug][' + featureKey + ']'];
            if (label) {
                logArgs.push(label);
            }

            if (typeof detail !== 'undefined') {
                logArgs.push(detail);
            }

            if (typeof console.debug === 'function') {
                console.debug.apply(console, logArgs);
            } else if (typeof console.log === 'function') {
                console.log.apply(console, logArgs);
            }
        }
    }

    function emitLoadMoreInteraction(phase, detail) {
        var payload = $.extend({ phase: phase }, detail || {});
        var eventName = 'my-articles:load-more';

        if (eventEmitter && typeof eventEmitter.emit === 'function') {
            eventEmitter.emit(eventName, payload);
            return;
        }

        if (sharedRuntime && typeof sharedRuntime.dispatchCustomEvent === 'function') {
            sharedRuntime.dispatchCustomEvent(eventName, payload);
        } else if (typeof window !== 'undefined' && typeof window.CustomEvent === 'function' && typeof window.dispatchEvent === 'function') {
            try {
                window.dispatchEvent(new CustomEvent(eventName, { detail: payload }));
            } catch (error) {
                if (typeof console !== 'undefined' && typeof console.error === 'function') {
                    console.error(error);
                }
            }
        }

        if (loadMoreSettings && typeof loadMoreSettings.onEvent === 'function') {
            try {
                loadMoreSettings.onEvent(eventName, payload);
            } catch (error) {
                if (typeof console !== 'undefined' && typeof console.error === 'function') {
                    console.error(error);
                }
            }
        }

        if (typeof console !== 'undefined' && typeof console.log === 'function') {
            console.log('[my-articles]', eventName, payload);
        }
    }

    function getResultsContainer(wrapper) {
        if (!wrapper || !wrapper.length) {
            return $();
        }

        var targetId = wrapper.attr('data-results-target');
        if (targetId && typeof document !== 'undefined' && document && typeof document.getElementById === 'function') {
            try {
                var directNode = document.getElementById(targetId);
                if (directNode) {
                    return $(directNode);
                }
            } catch (error) {
                // Ignore lookup errors and fallback to selector matching.
            }
        }

        var results = wrapper.find('[data-my-articles-role="results"]').first();
        if (results.length) {
            return results;
        }

        return $();
    }

    function setBusyState(wrapper, isBusy) {
        if (!wrapper || !wrapper.length) {
            return;
        }

        var busyValue = isBusy ? 'true' : 'false';
        wrapper.attr('aria-busy', busyValue);

        var results = getResultsContainer(wrapper);
        if (results.length) {
            results.attr('aria-busy', busyValue);

            if (isBusy) {
                results.attr('data-loading', 'true');
            } else {
                results.removeAttr('data-loading');
            }
        }
    }

    function getTimeMarker() {
        if (typeof performance !== 'undefined' && performance && typeof performance.now === 'function') {
            return performance.now();
        }

        return Date.now();
    }

    function createDurationTracker() {
        var start = getTimeMarker();
        return function () {
            var end = getTimeMarker();
            var duration = end - start;

            if (!isFinite(duration) || duration < 0) {
                duration = 0;
            }

            return duration;
        };
    }

    function resolveSearchLabel(key, fallback) {
        if (typeof window !== 'undefined' && window.myArticlesFilter && Object.prototype.hasOwnProperty.call(window.myArticlesFilter, key)) {
            var value = window.myArticlesFilter[key];
            if (typeof value === 'string' && value.length > 0) {
                return value;
            }
        }

        return fallback;
    }

    function buildSearchCountLabel(totalAvailable) {
        var fallbackNone = 'Aucun résultat';
        var fallbackSingle = '%s résultat';
        var fallbackPlural = '%s résultats';
        var fallbackLabel = 'Résultats : %s';

        var resolvedTotal = parseInt(totalAvailable, 10);
        if (isNaN(resolvedTotal) || resolvedTotal < 0) {
            resolvedTotal = 0;
        }

        if (resolvedTotal === 0) {
            return resolveSearchLabel('searchCountNone', fallbackNone) || fallbackNone;
        }

        var template = resolvedTotal === 1
            ? resolveSearchLabel('searchCountSingle', fallbackSingle)
            : resolveSearchLabel('searchCountPlural', fallbackPlural);

        var formatted = template.replace('%s', String(resolvedTotal));

        if (/%\d?\$?s/.test(template)) {
            formatted = template.replace(/%(?:\d+\$)?s/g, String(resolvedTotal));
        }

        var wrapperTemplate = resolveSearchLabel('searchCountLabel', fallbackLabel);
        if (/%\d?\$?s/.test(wrapperTemplate)) {
            return wrapperTemplate.replace(/%(?:\d+\$)?s/g, formatted);
        }

        return formatted;
    }

    function updateSearchCount(wrapper, totalAvailable) {
        if (!wrapper || !wrapper.length) {
            return;
        }

        var output = wrapper.find('.my-articles-search-count').first();
        if (!output.length) {
            return;
        }

        var resolvedTotal = parseInt(totalAvailable, 10);
        if (isNaN(resolvedTotal) || resolvedTotal < 0) {
            resolvedTotal = 0;
        }

        var label = buildSearchCountLabel(resolvedTotal);
        if (label) {
            output.text(label);
        }

        output.attr('data-count', resolvedTotal);
    }

    function refreshRestNonce(settings) {
        if (nonceManager && typeof nonceManager.refreshNonce === 'function') {
            return nonceManager.refreshNonce(settings);
        }

        var deferred = $.Deferred();
        deferred.reject(new Error('Nonce manager unavailable'));
        return deferred.promise();
    }

    function isInvalidNonceResponse(jqXHR, response) {
        if (nonceManager && typeof nonceManager.isInvalidNonceResponse === 'function') {
            return nonceManager.isInvalidNonceResponse(jqXHR, response);
        }

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

    function toTrimmedString(value) {
        if (typeof value !== 'string') {
            return '';
        }

        var trimmed = value.trim();

        return trimmed.length ? trimmed : '';
    }

    function extractFromCandidate(candidate, keys) {
        if (!candidate || 'object' !== typeof candidate) {
            return '';
        }

        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            if (Object.prototype.hasOwnProperty.call(candidate, key)) {
                var value = candidate[key];
                var stringValue = toTrimmedString(value);

                if (stringValue) {
                    return stringValue;
                }
            }
        }

        return '';
    }

    function extractAjaxErrorMessage(jqXHR, response) {
        var messageKeys = ['message', 'error', 'detail'];
        var candidates = [response, response && response.data];

        if (jqXHR && jqXHR.responseJSON) {
            candidates.push(jqXHR.responseJSON, jqXHR.responseJSON.data);
        }

        for (var i = 0; i < candidates.length; i++) {
            var message = extractFromCandidate(candidates[i], messageKeys);

            if (message) {
                return message;
            }
        }

        if (jqXHR && typeof jqXHR.responseText === 'string') {
            try {
                var parsed = JSON.parse(jqXHR.responseText);
                var parsedMessage = extractFromCandidate(parsed, messageKeys) || extractFromCandidate(parsed && parsed.data, messageKeys);

                if (parsedMessage) {
                    return parsedMessage;
                }
            } catch (parseError) {
                // responseText was not JSON; ignore parsing errors.
            }
        }

        if (jqXHR && typeof jqXHR.statusText === 'string' && jqXHR.statusText.length) {
            return jqXHR.statusText;
        }

        return '';
    }

    function extractAjaxErrorStatus(jqXHR, response) {
        var candidates = [response, response && response.data];

        if (jqXHR && jqXHR.responseJSON) {
            candidates.push(jqXHR.responseJSON, jqXHR.responseJSON.data);
        }

        for (var i = 0; i < candidates.length; i++) {
            var candidate = candidates[i];
            if (!candidate || 'object' !== typeof candidate) {
                continue;
            }

            var status = candidate.status;

            if (typeof status === 'number' && status > 0) {
                return status;
            }
        }

        if (jqXHR && typeof jqXHR.status === 'number' && jqXHR.status > 0) {
            return jqXHR.status;
        }

        return 0;
    }

    function extractAjaxErrorCode(jqXHR, response) {
        var candidates = [response, response && response.data];

        if (jqXHR && jqXHR.responseJSON) {
            candidates.push(jqXHR.responseJSON, jqXHR.responseJSON.data);
        }

        for (var i = 0; i < candidates.length; i++) {
            var code = extractFromCandidate(candidates[i], ['code', 'errorCode']);

            if (code) {
                return code;
            }
        }

        return '';
    }

    function composeAjaxErrorMessage(jqXHR, response, fallbackMessage) {
        var message = extractAjaxErrorMessage(jqXHR, response);
        var status = extractAjaxErrorStatus(jqXHR, response);
        var code = extractAjaxErrorCode(jqXHR, response);

        if (!message) {
            message = fallbackMessage;
        }

        var details = [];

        if (code) {
            details.push('code: ' + code);
        }

        if (status) {
            details.push('HTTP ' + status);
        }

        if (details.length) {
            message += ' (' + details.join(', ') + ')';
        }

        return message;
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

    function parseFiltersAttribute(value) {
        if (Array.isArray(value)) {
            return value;
        }

        if (typeof value !== 'string') {
            return [];
        }

        if (!value) {
            return [];
        }

        try {
            var decoded = JSON.parse(value);

            if (Array.isArray(decoded)) {
                return decoded;
            }
        } catch (error) {}

        return [];
    }

    function serializeFilters(filters) {
        var serialized = '[]';

        try {
            serialized = JSON.stringify(Array.isArray(filters) ? filters : []);
        } catch (error) {
            serialized = '[]';
        }

        return serialized;
    }

    function getButtonFilters(button) {
        if (!button || !button.length) {
            return [];
        }

        var dataValue = button.data('filters');

        if (Array.isArray(dataValue)) {
            return dataValue;
        }

        if (typeof dataValue === 'string') {
            return parseFiltersAttribute(dataValue);
        }

        var attrValue = button.attr('data-filters');

        return parseFiltersAttribute(attrValue);
    }

    function setButtonFilters(button, filters) {
        if (!button || !button.length) {
            return;
        }

        var serialized = serializeFilters(filters);
        button.attr('data-filters', serialized);
        button.data('filters', serialized);
    }

    function updateWrapperFilters(wrapper, filters) {
        if (!wrapper || !wrapper.length) {
            return;
        }

        var serialized = serializeFilters(filters);
        wrapper.attr('data-filters', serialized);
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
        var contentArea = getResultsContainer(wrapper);

        if (contentArea.length) {
            var inner = contentArea.find('.my-articles-grid-content, .my-articles-list-content, .swiper-wrapper').first();
            if (inner.length) {
                contentArea = inner;
            }
        }

        if (!contentArea.length) {
            contentArea = wrapper.find('.my-articles-grid-content, .my-articles-list-content, .swiper-wrapper');
        }

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
        var filters = getButtonFilters(button);
        var requestedPage = paged;

        if (!paged || paged <= 0) {
            disableAutoLoad(button, 'completed', true);
            button.hide();
            return;
        }

        var requestUrl = (loadMoreSettings && typeof loadMoreSettings.endpoint === 'string') ? loadMoreSettings.endpoint : '';

        if (!requestUrl && loadMoreSettings && typeof loadMoreSettings.restRoot === 'string') {
            var trimmedRoot = loadMoreSettings.restRoot.replace(/\/+$/, '');
            if (trimmedRoot.length) {
                requestUrl = trimmedRoot + '/my-articles/v1/load-more';
            }
        }

        var fallbackMessage = loadMoreSettings.errorText || 'Une erreur est survenue. Veuillez réessayer plus tard.';

        var instrumentationDetail = {
            instanceId: instanceId,
            requestedPage: requestedPage,
            totalPages: totalPages,
            category: typeof category === 'string' ? category : '',
            search: searchValue,
            sort: sortValue,
            autoTriggered: isAutoTrigger,
            requestUrl: requestUrl || '',
            hadNonceRefresh: false
        };

        if (!requestUrl) {
            if (state) {
                state.isFetching = false;
            }

            if (wrapper && wrapper.length) {
                clearFeedback(wrapper);
                showError(wrapper, fallbackMessage);
            }

            if (isAutoTrigger) {
                disableAutoLoad(button, 'error', true);
            }

            button.prop('disabled', false);

            instrumentationDetail.errorMessage = 'missing-endpoint';
            instrumentationDetail.status = 0;
            instrumentationDetail.displayMessage = fallbackMessage;

            emitLoadMoreInteraction('error', instrumentationDetail);
            debugLog('load-more', 'request:error', instrumentationDetail);

            return;
        }

        var hasRetried = false;

        debugLog('load-more', 'request:init', instrumentationDetail);

        if (state) {
            state.isFetching = true;
        }

        function finalizeRequest() {
            if (state) {
                state.isFetching = false;
            }
        }

        function handleErrorResponse(jqXHR, response, durationMs) {
            finalizeRequest();

            var parsedErrorMessage = extractAjaxErrorMessage(jqXHR, response);
            var errorMessage = composeAjaxErrorMessage(jqXHR, response, fallbackMessage);

            var resetText = loadMoreSettings.loadMoreText || originalButtonText;
            button.text(resetText);
            button.prop('disabled', false);
            showError(wrapper, errorMessage);

            if (isAutoTrigger) {
                disableAutoLoad(button, 'error', true);
            }

            var safeDuration = typeof durationMs === 'number' && isFinite(durationMs) ? Math.max(durationMs, 0) : null;

            instrumentationDetail.errorMessage = parsedErrorMessage || fallbackMessage;
            instrumentationDetail.status = extractAjaxErrorStatus(jqXHR, response);
            instrumentationDetail.errorCode = extractAjaxErrorCode(jqXHR, response) || '';
            instrumentationDetail.hadNonceRefresh = hasRetried;
            instrumentationDetail.response = response && response.data ? response.data : response;
            instrumentationDetail.jqXHR = jqXHR ? { status: jqXHR.status, statusText: jqXHR.statusText } : null;
            instrumentationDetail.durationMs = safeDuration;
            emitLoadMoreInteraction('error', instrumentationDetail);

            debugLog('load-more', 'request:error', instrumentationDetail);

            if (typeof console !== 'undefined' && typeof console.error === 'function') {
                console.error(errorMessage);
            }
        }

        function handleSuccessResponse(response, durationMs) {
            finalizeRequest();

            if (!response || !response.data) {
                handleErrorResponse(null, response, durationMs);
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

            var responseBatchCount = parseInt(responseData.added_count, 10);
            if (!isNaN(responseBatchCount)) {
                addedCount = responseBatchCount;
            }

            var responseDisplayedCount = parseInt(responseData.displayed_count, 10);
            if (!isNaN(responseDisplayedCount)) {
                addedCount = responseDisplayedCount;
            }

            var responseTotalResults = parseInt(responseData.total_results, 10);
            if (isNaN(responseTotalResults)) {
                responseTotalResults = Math.max(totalArticles, previousArticleCount + addedCount);
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

            wrapper.attr('data-total-results', responseTotalResults);

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
            instrumentationDetail.batchDisplayedCount = addedCount;
            instrumentationDetail.totalArticles = totalArticles;
            instrumentationDetail.totalResults = responseTotalResults;
            instrumentationDetail.renderedRegularCount = parseInt(responseData.rendered_regular_count, 10) || 0;
            instrumentationDetail.renderedPinnedCount = parseInt(responseData.rendered_pinned_count, 10) || 0;
            instrumentationDetail.totalRegular = parseInt(responseData.total_regular, 10) || 0;
            instrumentationDetail.totalPinned = parseInt(responseData.total_pinned, 10) || 0;
            instrumentationDetail.pinnedIds = typeof responseData.pinned_ids === 'string' ? responseData.pinned_ids : '';
            if (responseData && typeof responseData.pagination_meta === 'object' && responseData.pagination_meta !== null) {
                instrumentationDetail.pagination = responseData.pagination_meta;
            }
            instrumentationDetail.hadNonceRefresh = hasRetried;
            instrumentationDetail.errorMessage = '';
            instrumentationDetail.status = 200;
            instrumentationDetail.durationMs = typeof durationMs === 'number' && isFinite(durationMs) ? Math.max(durationMs, 0) : null;
            emitLoadMoreInteraction('success', instrumentationDetail);

            debugLog('load-more', 'request:success', instrumentationDetail);

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

            if (typeof responseData.filters !== 'undefined') {
                var updatedFilters = Array.isArray(responseData.filters) ? responseData.filters : [];
                setButtonFilters(button, updatedFilters);
                updateWrapperFilters(wrapper, updatedFilters);
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
            var trackDuration = createDurationTracker();

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
                    sort: sortValue,
                    filters: filters
                },
                beforeSend: function () {
                    var loadingText = loadMoreSettings.loadingText || originalButtonText;
                    button.text(loadingText);
                    button.prop('disabled', true);
                    if (wrapper && wrapper.length) {
                        if (previousArticleCount > 0) {
                            wrapper.addClass('is-loading-more');
                        }
                        setBusyState(wrapper, true);
                        wrapper.addClass('is-loading');
                    }
                    clearFeedback(wrapper);
                    emitLoadMoreInteraction('request', instrumentationDetail);
                    debugLog('load-more', 'request:send', instrumentationDetail);
                },
                success: function (response) {
                    var durationMs = trackDuration();
                    if (response && response.success) {
                        handleSuccessResponse(response, durationMs);
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
                                handleErrorResponse(null, response, durationMs);
                            });

                        return;
                    }

                    handleErrorResponse(null, response, durationMs);
                },
                error: function (jqXHR) {
                    var durationMs = trackDuration();
                    if (!hasRetried && isInvalidNonceResponse(jqXHR)) {
                        hasRetried = true;
                        instrumentationDetail.hadNonceRefresh = true;
                        refreshRestNonce(loadMoreSettings)
                            .done(function () {
                                sendAjaxRequest();
                            })
                            .fail(function () {
                                handleErrorResponse(jqXHR, null, durationMs);
                            });

                        return;
                    }

                    handleErrorResponse(jqXHR, null, durationMs);
                },
                complete: function () {
                    var durationMs = trackDuration();
                    if (wrapper && wrapper.length) {
                        setBusyState(wrapper, false);
                        wrapper.removeClass('is-loading');
                        wrapper.removeClass('is-loading-more');
                    }

                    instrumentationDetail.lastDurationMs = typeof durationMs === 'number' && isFinite(durationMs)
                        ? Math.max(durationMs, 0)
                        : null;
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
