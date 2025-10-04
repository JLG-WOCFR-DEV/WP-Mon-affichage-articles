// Fichier: assets/js/load-more.js
(function ($) {
    'use strict';

    var loadMoreSettings = (typeof myArticlesLoadMore !== 'undefined') ? myArticlesLoadMore : {};
    var pendingNonceDeferred = null;

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

    $(document).on('click', '.my-articles-load-more-btn', function (e) {
        e.preventDefault();

        var button = $(this);
        var wrapper = button.closest('.my-articles-wrapper');
        // CORRECTION : On cible le conteneur de la grille OU de la liste
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
        var requestedPage = paged;

        if (!totalPages || (paged && paged > totalPages)) {
            button.hide();
            button.prop('disabled', false);
            return;
        }

        var requestUrl = (loadMoreSettings && typeof loadMoreSettings.endpoint === 'string') ? loadMoreSettings.endpoint : '';

        if (!requestUrl && loadMoreSettings && typeof loadMoreSettings.restRoot === 'string') {
            requestUrl = loadMoreSettings.restRoot.replace(/\/+$/, '') + '/my-articles/v1/load-more';
        }

        var fallbackMessage = loadMoreSettings.errorText || 'Une erreur est survenue. Veuillez réessayer plus tard.';
        var hasRetried = false;

        function handleErrorResponse(jqXHR, response) {
            var errorMessage = '';

            if (response && response.data && response.data.message) {
                errorMessage = response.data.message;
            } else if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                errorMessage = jqXHR.responseJSON.data.message;
            }

            if (!errorMessage) {
                errorMessage = fallbackMessage;
            }

            var resetText = loadMoreSettings.loadMoreText || originalButtonText;
            button.text(resetText);
            button.prop('disabled', false);
            showError(wrapper, errorMessage);

            if (typeof console !== 'undefined' && typeof console.error === 'function') {
                console.error(errorMessage);
            }
        }

        function handleSuccessResponse(response) {
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

            focusOnFirstArticleOrTitle(wrapper, contentArea, focusArticle);

            if (typeof responseData.pinned_ids !== 'undefined') {
                var updatedPinnedIds = responseData.pinned_ids;
                button.data('pinned-ids', updatedPinnedIds);
                button.attr('data-pinned-ids', updatedPinnedIds);
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
                button.prop('disabled', false);
                return;
            }

            button.prop('disabled', false);
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
                    category: category
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
                },
                success: function (response) {
                    if (response && response.success) {
                        handleSuccessResponse(response);
                        return;
                    }

                    if (!hasRetried && isInvalidNonceResponse(null, response)) {
                        hasRetried = true;
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
    });

})(jQuery);
