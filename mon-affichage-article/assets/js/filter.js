// Fichier: assets/js/filter.js
(function ($) {
    'use strict';

    var filterSettings = (typeof myArticlesFilter !== 'undefined') ? myArticlesFilter : {};
    var SEARCH_DEBOUNCE_DELAY = 400;

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
            return filterSettings;
        })
        : null;

    var nonceManager = (sharedRuntime && typeof sharedRuntime.createNonceManager === 'function')
        ? sharedRuntime.createNonceManager($, {
            getSettings: function () {
                return filterSettings;
            },
            mirrors: [
                function () {
                    return (typeof window !== 'undefined' && window.myArticlesFilter) ? window.myArticlesFilter : null;
                },
                function () {
                    return (typeof window !== 'undefined' && window.myArticlesLoadMore) ? window.myArticlesLoadMore : null;
                }
            ]
        })
        : null;

    var activeFilterRequest = null;
    var activeFilterRequestToken = 0;
    var activeFilterRequestDetail = null;
    var filterRequestSequence = 0;

    function cancelActiveFilterRequest(reason) {
        if (!activeFilterRequest || typeof activeFilterRequest.abort !== 'function') {
            return false;
        }

        var detail = activeFilterRequestDetail ? $.extend({}, activeFilterRequestDetail) : {};
        detail.reason = reason || 'superseded';

        try {
            activeFilterRequest.abort();
        } catch (error) {}

        emitFilterInteraction('cancelled', detail);

        activeFilterRequest = null;
        activeFilterRequestToken = 0;
        activeFilterRequestDetail = null;

        return true;
    }

    function emitFilterInteraction(phase, detail) {
        var payload = $.extend({ phase: phase }, detail || {});
        var eventName = 'my-articles:filter';

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

        if (filterSettings && typeof filterSettings.onEvent === 'function') {
            try {
                filterSettings.onEvent(eventName, payload);
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

    function getFilterEndpoint(settings) {
        if (settings && typeof settings.endpoint === 'string' && settings.endpoint.length > 0) {
            return settings.endpoint;
        }

        if (settings && typeof settings.restRoot === 'string' && settings.restRoot.length > 0) {
            return settings.restRoot.replace(/\/+$/, '') + '/my-articles/v1/filter';
        }

        return '';
    }

    function getContentArea(wrapper) {
        if (!wrapper || !wrapper.length) {
            return $();
        }

        return wrapper.find('.my-articles-grid-content, .my-articles-list-content, .swiper-wrapper');
    }

    function getSearchForm(wrapper) {
        if (!wrapper || !wrapper.length) {
            return $();
        }

        return wrapper.find('.my-articles-search-form').first();
    }

    function getActiveCategorySlug(wrapper) {
        if (!wrapper || !wrapper.length) {
            return '';
        }

        var activeButton = wrapper.find('.my-articles-filter-nav li.active [data-category]').first();

        if (!activeButton.length) {
            return '';
        }

        var category = activeButton.data('category');

        if (typeof category === 'undefined' || category === null) {
            return '';
        }

        return String(category);
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

    function normalizeSortValue(value) {
        if (typeof value === 'string') {
            return value;
        }

        if (value === null || typeof value === 'undefined') {
            return '';
        }

        return String(value);
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

    function getWrapperFilters(wrapper) {
        if (!wrapper || !wrapper.length) {
            return [];
        }

        var attr = wrapper.attr('data-filters');

        if (typeof attr === 'undefined') {
            return [];
        }

        return parseFiltersAttribute(attr);
    }

    function setWrapperFilters(wrapper, filters) {
        if (!wrapper || !wrapper.length) {
            return;
        }

        var serialized = '[]';

        try {
            serialized = JSON.stringify(Array.isArray(filters) ? filters : []);
        } catch (error) {
            serialized = '[]';
        }

        wrapper.attr('data-filters', serialized);
    }

    function syncLoadMoreFilters(wrapper, filters) {
        if (!wrapper || !wrapper.length) {
            return;
        }

        var loadMoreBtn = wrapper.find('.my-articles-load-more-btn').first();

        if (!loadMoreBtn.length) {
            return;
        }

        var serialized = '[]';

        try {
            serialized = JSON.stringify(Array.isArray(filters) ? filters : []);
        } catch (error) {
            serialized = '[]';
        }

        loadMoreBtn.data('filters', serialized);
        loadMoreBtn.attr('data-filters', serialized);
    }

    function prepareSearchValue(value) {
        var normalized = normalizeSearchValue(value);

        if (normalized.length === 0) {
            return '';
        }

        return normalized.replace(/\s+/g, ' ').trim();
    }

    function sanitizeSortValue(value) {
        var normalized = normalizeSortValue(value).trim();

        if (!normalized) {
            return '';
        }

        return normalized.replace(/[^a-z0-9_\-]+/gi, '').toLowerCase();
    }

    function updateSearchFormState(form, value) {
        if (!form || !form.length) {
            return;
        }

        var normalizedValue = normalizeSearchValue(value);
        form.attr('data-current-search', normalizedValue);

        if (normalizedValue.length > 0) {
            form.addClass('has-value');
        } else {
            form.removeClass('has-value');
        }

        var input = form.find('.my-articles-search-input').first();
        if (input.length && input.val() !== normalizedValue) {
            input.val(normalizedValue);
        }
    }

    function syncWrapperSearchState(wrapper, value) {
        if (!wrapper || !wrapper.length) {
            return;
        }

        var normalizedValue = normalizeSearchValue(value);
        wrapper.attr('data-search-query', normalizedValue);
    }

    function buildFilterRequestData(wrapper, instanceId, categorySlug, searchQuery) {
        var currentUrl = '';
        if (typeof window !== 'undefined' && window.location && typeof window.location.href === 'string') {
            currentUrl = window.location.href;
        }

        var requestData = {
            instance_id: instanceId,
            category: categorySlug,
            current_url: currentUrl,
        };

        var currentSort = '';
        if (wrapper && wrapper.length) {
            currentSort = sanitizeSortValue(wrapper.attr('data-sort'));
        }

        requestData.sort = currentSort;

        var activeFilters = getWrapperFilters(wrapper);

        if (Array.isArray(activeFilters) && activeFilters.length > 0) {
            requestData.filters = activeFilters;
        }

        if (typeof searchQuery === 'string') {
            requestData.search = searchQuery;
        }

        return requestData;
    }

    function getSearchQueryParamKey(wrapper, instanceId, form) {
        var key = '';

        if (form && form.length) {
            key = form.attr('data-search-param') || '';
            if (key) {
                return String(key);
            }
        }

        if (wrapper && wrapper.length) {
            key = wrapper.attr('data-search-param') || '';
            if (key) {
                return String(key);
            }
        }

        if (instanceId) {
            return 'my_articles_search_' + instanceId;
        }

        return '';
    }

    function getSortQueryParamKey(wrapper, instanceId) {
        var key = '';

        if (wrapper && wrapper.length) {
            key = wrapper.attr('data-sort-param') || '';
            if (key) {
                return String(key);
            }
        }

        if (instanceId) {
            return 'my_articles_sort_' + instanceId;
        }

        return '';
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
                // Ignored on purpose: responseText is not valid JSON.
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
            // Silencieusement ignorer les erreurs (navigateurs plus anciens)
        }
    }

    function formatCountMessage(template, count, total) {
        if (typeof template !== 'string' || template.length === 0) {
            return '';
        }

        var hasTotal = typeof total !== 'undefined';
        var sequentialIndex = 0;

        return template.replace(/%(?:(\d+)\$)?[sd]/g, function (match, position) {
            if (position) {
                var positionIndex = parseInt(position, 10);

                if (positionIndex === 1) {
                    return String(count);
                }

                if (positionIndex === 2 && hasTotal) {
                    return String(total);
                }

                return match;
            }

            if (sequentialIndex === 0) {
                sequentialIndex += 1;
                return String(count);
            }

            if (hasTotal && sequentialIndex === 1) {
                sequentialIndex += 1;
                return String(total);
            }

            sequentialIndex += 1;
            return String(count);
        });
    }

    function resolveFilterLabel(key, fallback) {
        if (filterSettings && Object.prototype.hasOwnProperty.call(filterSettings, key)) {
            var value = filterSettings[key];
            if (typeof value === 'string' && value.length > 0) {
                return value;
            }
        }

        return fallback;
    }

    function buildFilterFeedbackMessage(displayedCount, totalAvailable) {
        var fallbackSingle = '%s article affiché.';
        var fallbackPlural = '%s articles affichés.';
        var fallbackNone = 'Aucun article à afficher.';
        var fallbackPartialSingle = 'Affichage de %1$s article sur %2$s.';
        var fallbackPartialPlural = 'Affichage de %1$s articles sur %2$s.';

        var resolvedDisplayed = parseInt(displayedCount, 10);
        if (isNaN(resolvedDisplayed) || resolvedDisplayed < 0) {
            resolvedDisplayed = 0;
        }

        var resolvedTotal = parseInt(totalAvailable, 10);
        if (isNaN(resolvedTotal) || resolvedTotal < 0) {
            resolvedTotal = resolvedDisplayed;
        }

        if (resolvedTotal === 0) {
            var noneLabel = resolveFilterLabel('countNone', fallbackNone) || fallbackNone;
            return noneLabel;
        }

        if (resolvedTotal > resolvedDisplayed) {
            var partialTemplate = resolvedDisplayed === 1
                ? resolveFilterLabel('countPartialSingle', fallbackPartialSingle)
                : resolveFilterLabel('countPartialPlural', fallbackPartialPlural);

            var formattedPartial = formatCountMessage(partialTemplate, resolvedDisplayed, resolvedTotal);

            if (!formattedPartial) {
                var fallbackTemplate = resolvedDisplayed === 1 ? fallbackPartialSingle : fallbackPartialPlural;
                formattedPartial = formatCountMessage(fallbackTemplate, resolvedDisplayed, resolvedTotal);
            }

            if (formattedPartial) {
                return formattedPartial;
            }
        }

        if (resolvedDisplayed === 1) {
            var singleLabel = resolveFilterLabel('countSingle', fallbackSingle);
            var formattedSingle = formatCountMessage(singleLabel, resolvedDisplayed) || formatCountMessage(fallbackSingle, resolvedDisplayed);
            if (formattedSingle) {
                return formattedSingle;
            }

            return fallbackSingle.replace('%s', String(resolvedDisplayed));
        }

        if (resolvedDisplayed > 1) {
            var pluralLabel = resolveFilterLabel('countPlural', fallbackPlural);
            var formattedPlural = formatCountMessage(pluralLabel, resolvedDisplayed) || formatCountMessage(fallbackPlural, resolvedDisplayed);

            if (formattedPlural) {
                return formattedPlural;
            }

            return fallbackPlural.replace('%s', String(resolvedDisplayed));
        }

        var fallback = resolveFilterLabel('countNone', fallbackNone) || fallbackNone;
        return fallback;
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

    function handleFilterSuccess(wrapper, contentArea, instanceId, categorySlug, searchQuery, responseData) {
        if (!responseData || typeof responseData !== 'object') {
            return false;
        }

        var wrapperElement = (wrapper && wrapper.length) ? wrapper.get(0) : null;
        var sanitizedSearch = '';
        var sanitizedSort = '';

        if (typeof responseData.search_query === 'string') {
            sanitizedSearch = responseData.search_query;
        }

        syncWrapperSearchState(wrapper, sanitizedSearch);

        if (typeof responseData.sort === 'string') {
            sanitizedSort = sanitizeSortValue(responseData.sort);
        }

        if (!sanitizedSort && wrapper && wrapper.length) {
            sanitizedSort = sanitizeSortValue(wrapper.attr('data-sort'));
        }

        if (wrapper && wrapper.length) {
            wrapper.attr('data-sort', sanitizedSort);
        }

        var searchForm = getSearchForm(wrapper);
        if (searchForm.length) {
            updateSearchFormState(searchForm, sanitizedSearch);
            searchForm.data('last-value', sanitizedSearch);
            searchForm.data('pending-search', null);
        }

        if (contentArea && contentArea.length) {
            contentArea.html(typeof responseData.html === 'string' ? responseData.html : '');
        }

        var totalPages = parseInt(responseData.total_pages, 10);
        if (isNaN(totalPages)) {
            totalPages = 0;
        }

        var nextPage = parseInt(responseData.next_page, 10);
        if (isNaN(nextPage)) {
            nextPage = 0;
        }

        var pinnedIds = '';
        if (typeof responseData.pinned_ids !== 'undefined') {
            pinnedIds = responseData.pinned_ids;
        }

        var responseFilters = Array.isArray(responseData.filters) ? responseData.filters : [];

        setWrapperFilters(wrapper, responseFilters);

        var loadMoreBtn = wrapper.find('.my-articles-load-more-btn').first();

        if (!loadMoreBtn.length && totalPages > 1) {
            var loadMoreText = (typeof window.myArticlesLoadMore !== 'undefined' && window.myArticlesLoadMore.loadMoreText)
                ? window.myArticlesLoadMore.loadMoreText
                : 'Charger plus';
            var loadMoreContainer = $('<div class="my-articles-load-more-container"></div>');
            var initialNextPage = nextPage > 0 ? nextPage : 2;
            var serializedFilters = '[]';

            try {
                serializedFilters = JSON.stringify(responseFilters);
            } catch (error) {
                serializedFilters = '[]';
            }

            var newLoadMoreBtn = $('<button class="my-articles-load-more-btn"></button>')
                .attr('data-instance-id', instanceId)
                .attr('data-paged', initialNextPage)
                .attr('data-total-pages', totalPages)
                .attr('data-pinned-ids', pinnedIds)
                .attr('data-category', categorySlug)
                .attr('data-search', sanitizedSearch)
                .attr('data-sort', sanitizedSort)
                .attr('data-filters', serializedFilters)
                .data('filters', serializedFilters)
                .data('sort', sanitizedSort)
                .text(loadMoreText);

            loadMoreContainer.append(newLoadMoreBtn);

            if (contentArea && contentArea.length) {
                contentArea.last().after(loadMoreContainer);
            } else {
                wrapper.append(loadMoreContainer);
            }

            loadMoreBtn = newLoadMoreBtn;
        }

        if (loadMoreBtn.length) {
            loadMoreBtn.data('category', categorySlug);
            loadMoreBtn.attr('data-category', categorySlug);

            loadMoreBtn.data('total-pages', totalPages);
            loadMoreBtn.attr('data-total-pages', totalPages);

            loadMoreBtn.data('pinned-ids', pinnedIds);
            loadMoreBtn.attr('data-pinned-ids', pinnedIds);

            loadMoreBtn.data('search', sanitizedSearch);
            loadMoreBtn.attr('data-search', sanitizedSearch);

            loadMoreBtn.data('sort', sanitizedSort);
            loadMoreBtn.attr('data-sort', sanitizedSort);

            syncLoadMoreFilters(wrapper, responseFilters);

            if (totalPages > 1) {
                if (nextPage < 2) {
                    nextPage = 2;
                }
                loadMoreBtn.data('paged', nextPage);
                loadMoreBtn.attr('data-paged', nextPage);
                loadMoreBtn.show();
                loadMoreBtn.prop('disabled', false);
            } else {
                loadMoreBtn.data('paged', nextPage);
                loadMoreBtn.attr('data-paged', nextPage);
                loadMoreBtn.hide();
                loadMoreBtn.prop('disabled', false);

                var orphanContainer = loadMoreBtn.closest('.my-articles-load-more-container');
                if (orphanContainer.length) {
                    orphanContainer.remove();
                }
            }
        }

        if (typeof responseData.pagination_html !== 'undefined') {
            var paginationHtml = responseData.pagination_html;
            var paginationElement = wrapper.find('.my-articles-pagination').first();

            if (typeof paginationHtml === 'string' && paginationHtml.trim().length > 0) {
                if (paginationElement.length) {
                    paginationElement.replaceWith(paginationHtml);
                } else if (contentArea.length) {
                    contentArea.after(paginationHtml);
                }
            } else if (paginationElement.length) {
                paginationElement.remove();
            }
        }

        if (typeof window.myArticlesInitWrappers === 'function') {
            window.myArticlesInitWrappers(wrapperElement);
        }

        if (typeof window.myArticlesInitSwipers === 'function') {
            window.myArticlesInitSwipers(wrapperElement);
        }

        if (instanceId) {
            var queryParams = {};
            queryParams['my_articles_cat_' + instanceId] = categorySlug || null;
            var searchParamKey = getSearchQueryParamKey(wrapper, instanceId, searchForm);
            if (searchParamKey) {
                queryParams[searchParamKey] = sanitizedSearch || null;
            }
            var sortParamKey = getSortQueryParamKey(wrapper, instanceId);
            if (sortParamKey) {
                queryParams[sortParamKey] = sanitizedSort || null;
            }
            queryParams['paged_' + instanceId] = '1';
            updateInstanceQueryParams(instanceId, queryParams);
        }

        var totalArticles = contentArea.find('.my-article-item').length;
        var responseDisplayedCount = parseInt(responseData.displayed_count, 10);
        if (isNaN(responseDisplayedCount)) {
            responseDisplayedCount = totalArticles;
        }

        var responseTotalResults = parseInt(responseData.total_results, 10);
        if (isNaN(responseTotalResults)) {
            responseTotalResults = Math.max(responseDisplayedCount, totalArticles);
        }

        var feedbackMessage = buildFilterFeedbackMessage(responseDisplayedCount, responseTotalResults);
        var feedbackElement = getFeedbackElement(wrapper);
        feedbackElement.removeClass('is-error')
            .removeAttr('role')
            .attr('aria-live', 'polite')
            .text(feedbackMessage)
            .show();

        wrapper.attr('data-total-results', responseTotalResults);

        var firstArticle = contentArea.find('.my-article-item').first();
        focusOnFirstArticleOrTitle(wrapper, contentArea, firstArticle);

        return true;
    }

    function sendFilterRequest(wrapper, requestData, callbacks) {
        callbacks = callbacks || {};

        var cancelReason = typeof callbacks.cancelReason === 'string' ? callbacks.cancelReason : 'superseded';
        cancelActiveFilterRequest(cancelReason);

        var requestUrl = getFilterEndpoint(filterSettings);
        var hasRetried = false;

        var instanceId = typeof requestData.instance_id !== 'undefined' ? parseInt(requestData.instance_id, 10) : null;
        if (isNaN(instanceId)) {
            instanceId = null;
        }

        var requestDetail = {
            instanceId: instanceId,
            category: typeof requestData.category === 'string' ? requestData.category : '',
            search: typeof requestData.search === 'string' ? requestData.search : '',
            sort: typeof requestData.sort === 'string' ? requestData.sort : '',
            requestUrl: requestUrl
        };

        if (!requestUrl) {
            emitFilterInteraction('error', $.extend({}, requestDetail, {
                errorMessage: 'missing-endpoint'
            }));

            if (typeof callbacks.onError === 'function') {
                callbacks.onError(null, null);
            }
            return;
        }

        var defaultErrorMessage = (filterSettings && typeof filterSettings.errorText === 'string')
            ? filterSettings.errorText
            : 'Une erreur est survenue.';

        function emitError(jqXHR, response) {
            emitFilterInteraction('error', $.extend({}, requestDetail, {
                errorMessage: extractAjaxErrorMessage(jqXHR, response) || defaultErrorMessage,
                status: extractAjaxErrorStatus(jqXHR, response),
                errorCode: extractAjaxErrorCode(jqXHR, response) || '',
                hadNonceRefresh: hasRetried
            }));
        }

        function emitSuccess(responseData) {
            var totalPages = parseInt(responseData.total_pages, 10);
            if (isNaN(totalPages)) {
                totalPages = 0;
            }

            var nextPage = parseInt(responseData.next_page, 10);
            if (isNaN(nextPage)) {
                nextPage = 0;
            }

            var displayedCount = parseInt(responseData.displayed_count, 10);
            if (isNaN(displayedCount)) {
                displayedCount = 0;
            }

            var totalResults = parseInt(responseData.total_results, 10);
            if (isNaN(totalResults)) {
                totalResults = displayedCount;
            }

            var renderedRegular = parseInt(responseData.rendered_regular_count, 10);
            if (isNaN(renderedRegular)) {
                renderedRegular = 0;
            }

            var renderedPinned = parseInt(responseData.rendered_pinned_count, 10);
            if (isNaN(renderedPinned)) {
                renderedPinned = 0;
            }

            emitFilterInteraction('success', $.extend({}, requestDetail, {
                totalPages: totalPages,
                nextPage: nextPage,
                pinnedIds: typeof responseData.pinned_ids === 'string' ? responseData.pinned_ids : '',
                searchQuery: typeof responseData.search_query === 'string' ? responseData.search_query : requestDetail.search,
                sort: typeof responseData.sort === 'string' ? responseData.sort : requestDetail.sort,
                hadNonceRefresh: hasRetried,
                displayedCount: displayedCount,
                totalResults: totalResults,
                renderedRegularCount: renderedRegular,
                renderedPinnedCount: renderedPinned,
                totalRegular: parseInt(responseData.total_regular, 10) || 0,
                totalPinned: parseInt(responseData.total_pinned, 10) || 0
            }));
        }

        function performRequest() {
            var requestToken = ++filterRequestSequence;
            var wasAborted = false;
            var nonceHeader = filterSettings && filterSettings.restNonce ? filterSettings.restNonce : '';

            $.ajax({
                url: requestUrl,
                type: 'POST',
                headers: {
                    'X-WP-Nonce': nonceHeader
                },
                data: requestData,
                beforeSend: function (currentJqXHR) {
                    activeFilterRequest = currentJqXHR;
                    activeFilterRequestToken = requestToken;
                    activeFilterRequestDetail = $.extend({}, requestDetail);
                    emitFilterInteraction('request', requestDetail);

                    if (typeof callbacks.beforeSend === 'function') {
                        callbacks.beforeSend();
                    }
                },
                success: function (response) {
                    if (wasAborted) {
                        return;
                    }

                    if (response && response.success) {
                        var responseData = response.data || {};
                        emitSuccess(responseData);

                        if (typeof callbacks.onSuccess === 'function') {
                            callbacks.onSuccess(responseData, response);
                        }

                        return;
                    }

                    if (!hasRetried && isInvalidNonceResponse(null, response)) {
                        hasRetried = true;
                        refreshRestNonce(filterSettings)
                            .done(function () {
                                performRequest();
                            })
                            .fail(function () {
                                emitError(null, response);

                                if (typeof callbacks.onError === 'function') {
                                    callbacks.onError(null, response);
                                }
                            });

                        return;
                    }

                    emitError(null, response);

                    if (typeof callbacks.onError === 'function') {
                        callbacks.onError(null, response);
                    }
                },
                error: function (jqXHR, textStatus) {
                    if (textStatus === 'abort') {
                        wasAborted = true;
                        return;
                    }

                    if (!hasRetried && isInvalidNonceResponse(jqXHR)) {
                        hasRetried = true;
                        refreshRestNonce(filterSettings)
                            .done(function () {
                                performRequest();
                            })
                            .fail(function () {
                                emitError(jqXHR);

                                if (typeof callbacks.onError === 'function') {
                                    callbacks.onError(jqXHR);
                                }
                            });

                        return;
                    }

                    emitError(jqXHR);

                    if (typeof callbacks.onError === 'function') {
                        callbacks.onError(jqXHR);
                    }
                },
                complete: function (completedJqXHR, textStatus) {
                    if (activeFilterRequestToken === requestToken && activeFilterRequest === completedJqXHR) {
                        activeFilterRequest = null;
                        activeFilterRequestToken = 0;
                        activeFilterRequestDetail = null;
                    }

                    if (wasAborted || textStatus === 'abort') {
                        return;
                    }

                    if (typeof callbacks.onComplete === 'function') {
                        callbacks.onComplete();
                    }
                }
            });

        }

        performRequest();
    }

    function triggerSearchRequest(wrapper, form, rawValue) {
        if (!wrapper || !wrapper.length) {
            return;
        }

        var instanceId = wrapper.data('instance-id');

        if (!instanceId) {
            return;
        }

        var categorySlug = getActiveCategorySlug(wrapper);
        var searchValue = prepareSearchValue(rawValue);
        var contentArea = getContentArea(wrapper);
        var fallbackMessage = (filterSettings && filterSettings.errorText) ? filterSettings.errorText : 'Une erreur est survenue. Veuillez réessayer plus tard.';

        form.data('pending-search', searchValue);

        var requestData = buildFilterRequestData(wrapper, instanceId, categorySlug, searchValue);

        sendFilterRequest(wrapper, requestData, {
            cancelReason: 'search-update',
            beforeSend: function () {
                wrapper.attr('aria-busy', 'true');
                wrapper.addClass('is-loading');
                clearFeedback(wrapper);

                form.addClass('is-loading');
                var input = form.find('.my-articles-search-input').first();
                if (input.length) {
                    input.attr('aria-busy', 'true');
                }
                var submitButton = form.find('.my-articles-search-submit').first();
                if (submitButton.length) {
                    submitButton.prop('disabled', true);
                    submitButton.attr('aria-busy', 'true');
                }
            },
            onSuccess: function (responseData) {
                var success = handleFilterSuccess(wrapper, contentArea, instanceId, categorySlug, searchValue, responseData);

                if (!success) {
                    showError(wrapper, fallbackMessage);
                }
            },
            onError: function (jqXHR, response) {
                var errorMessage = composeAjaxErrorMessage(jqXHR, response, fallbackMessage);
                showError(wrapper, errorMessage);
            },
            onComplete: function () {
                wrapper.attr('aria-busy', 'false');
                wrapper.removeClass('is-loading');

                form.removeClass('is-loading');
                var input = form.find('.my-articles-search-input').first();
                if (input.length) {
                    input.attr('aria-busy', 'false');
                }

                var submitButton = form.find('.my-articles-search-submit').first();
                if (submitButton.length) {
                    submitButton.prop('disabled', false);
                    submitButton.attr('aria-busy', 'false');
                }

                form.data('pending-search', null);
            }
        });
    }

    function queueSearchRequest(form, options) {
        if (!form || !form.length) {
            return;
        }

        var wrapper = form.closest('.my-articles-wrapper');
        if (!wrapper || !wrapper.length) {
            return;
        }

        var input = form.find('.my-articles-search-input').first();
        var rawValue = input.length ? input.val() : '';
        var searchValue = prepareSearchValue(rawValue);
        var forceRequest = options && options.force;
        var immediate = options && options.immediate;

        if (!forceRequest) {
            var lastValue = form.data('last-value');
            if (typeof lastValue === 'string' && prepareSearchValue(lastValue) === searchValue) {
                return;
            }

            var pendingValue = form.data('pending-search');
            if (typeof pendingValue === 'string' && prepareSearchValue(pendingValue) === searchValue) {
                return;
            }
        }

        var debounceDelay = immediate ? 0 : SEARCH_DEBOUNCE_DELAY;
        var existingTimer = form.data('search-timer');

        if (existingTimer) {
            clearTimeout(existingTimer);
        }

        if (debounceDelay > 0) {
            var timerId = setTimeout(function () {
                form.data('search-timer', null);
                triggerSearchRequest(wrapper, form, searchValue);
            }, debounceDelay);

            form.data('search-timer', timerId);
        } else {
            triggerSearchRequest(wrapper, form, searchValue);
        }
    }

    $(document).on('click', '.my-articles-filter-nav button, .my-articles-filter-nav a', function (e) {
        e.preventDefault();

        var filterLink = $(this);
        var filterItem = filterLink.closest('li');
        var navList = filterItem.closest('ul');
        var previousActiveItem = navList.find('li.active').first();
        var wrapper = filterLink.closest('.my-articles-wrapper');
        var instanceId = wrapper.data('instance-id');

        if (filterItem.hasClass('active')) {
            return;
        }

        if (!instanceId) {
            return;
        }

        var searchForm = getSearchForm(wrapper);
        if (searchForm.length) {
            var pendingTimer = searchForm.data('search-timer');
            if (pendingTimer) {
                clearTimeout(pendingTimer);
                searchForm.data('search-timer', null);
            }
        }

        navList.find('li').removeClass('active');
        navList.find('button, a').attr('aria-pressed', 'false');
        filterItem.addClass('active');
        filterLink.attr('aria-pressed', 'true');

        var categoryData = filterLink.data('category');
        var categorySlug = '';
        if (typeof categoryData !== 'undefined' && categoryData !== null) {
            categorySlug = normalizeSearchValue(categoryData).trim();
        }

        var searchValue = '';
        if (searchForm.length) {
            var inputVal = searchForm.find('.my-articles-search-input').val();
            searchValue = prepareSearchValue(inputVal);
        } else if (wrapper && wrapper.length) {
            searchValue = prepareSearchValue(wrapper.attr('data-search-query'));
        }

        var contentArea = getContentArea(wrapper);
        var fallbackMessage = (filterSettings && filterSettings.errorText) ? filterSettings.errorText : 'Une erreur est survenue. Veuillez réessayer plus tard.';

        function restorePreviousFilterState() {
            filterItem.removeClass('active');
            filterLink.attr('aria-pressed', 'false');
            if (previousActiveItem && previousActiveItem.length) {
                previousActiveItem.addClass('active');
                previousActiveItem.find('button, a').first().attr('aria-pressed', 'true');
            }
        }

        var requestData = buildFilterRequestData(wrapper, instanceId, categorySlug, searchValue);

        sendFilterRequest(wrapper, requestData, {
            cancelReason: 'category-change',
            beforeSend: function () {
                wrapper.attr('aria-busy', 'true');
                wrapper.addClass('is-loading');
                clearFeedback(wrapper);
            },
            onSuccess: function (responseData) {
                var success = handleFilterSuccess(wrapper, contentArea, instanceId, categorySlug, searchValue, responseData);

                if (!success) {
                    restorePreviousFilterState();
                    showError(wrapper, fallbackMessage);
                }
            },
            onError: function (jqXHR, response) {
                restorePreviousFilterState();
                var errorMessage = composeAjaxErrorMessage(jqXHR, response, fallbackMessage);
                showError(wrapper, errorMessage);
            },
            onComplete: function () {
                wrapper.attr('aria-busy', 'false');
                wrapper.removeClass('is-loading');
            }
        });
    });

    $(document).on('submit', '.my-articles-search-form', function (e) {
        e.preventDefault();

        var form = $(this);
        queueSearchRequest(form, { immediate: true, force: true });
    });

    $(document).on('input', '.my-articles-search-input', function () {
        var input = $(this);
        var form = input.closest('.my-articles-search-form');

        updateSearchFormState(form, input.val());
        queueSearchRequest(form, { immediate: false });
    });

    $(document).on('click', '.my-articles-search-clear', function (e) {
        e.preventDefault();

        var button = $(this);
        var form = button.closest('.my-articles-search-form');
        var input = form.find('.my-articles-search-input').first();

        input.val('');
        updateSearchFormState(form, '');
        queueSearchRequest(form, { immediate: true, force: true });
        input.trigger('focus');
    });

    $(document).on('click', '.my-articles-search-suggestion', function (e) {
        e.preventDefault();

        var button = $(this);
        var suggestion = button.data('suggestion');
        if (typeof suggestion !== 'string') {
            suggestion = button.text();
        }

        if (typeof suggestion !== 'string') {
            return;
        }

        suggestion = suggestion.trim();

        var form = button.closest('.my-articles-search-form');
        if (!form.length) {
            return;
        }

        var input = form.find('.my-articles-search-input').first();
        if (!input.length) {
            return;
        }

        input.val(suggestion);
        updateSearchFormState(form, suggestion);
        queueSearchRequest(form, { immediate: true, force: true });
        input.trigger('focus');
    });

})(jQuery);
