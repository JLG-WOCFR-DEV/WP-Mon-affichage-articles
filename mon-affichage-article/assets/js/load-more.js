// Fichier: assets/js/load-more.js
(function ($) {
    'use strict';

    var loadMoreSettings = (typeof myArticlesLoadMore !== 'undefined') ? myArticlesLoadMore : {};

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
            feedback.removeClass('is-error').text('').hide();
        }
    }

    function showError(wrapper, message) {
        var feedback = getFeedbackElement(wrapper);
        feedback.text(message).addClass('is-error').show();
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

        $.ajax({
            url: loadMoreSettings.ajax_url,
            type: 'POST',
            data: {
                action: 'load_more_articles',
                security: loadMoreSettings.nonce || '',
                instance_id: instanceId,
                paged: paged,
                pinned_ids: pinnedIds,
                category: category
            },
            beforeSend: function () {
                var loadingText = loadMoreSettings.loadingText || originalButtonText;
                button.text(loadingText);
                button.prop('disabled', true);
                clearFeedback(wrapper);
            },
            success: function (response) {
                if (response.success) {
                    var responseData = response.data || {};
                    var wrapperElement = (wrapper && wrapper.length) ? wrapper.get(0) : null;

                    // Ajoute les nouveaux articles à la suite des anciens
                    if (typeof responseData.html !== 'undefined') {
                        contentArea.append(responseData.html);
                    }

                    if (typeof window.myArticlesInitWrappers === 'function') {
                        window.myArticlesInitWrappers(wrapperElement);
                    }

                    if (typeof window.myArticlesInitSwipers === 'function') {
                        window.myArticlesInitSwipers(wrapperElement);
                    }

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
                        // S'il n'y a plus de page, on cache le bouton
                        button.hide();
                        button.prop('disabled', false);
                        return;
                    }

                    button.prop('disabled', false);
                    clearFeedback(wrapper);
                } else {
                    var fallbackMessage = loadMoreSettings.errorText || 'Une erreur est survenue. Veuillez réessayer plus tard.';
                    var responseMessage = (response.data && response.data.message) ? response.data.message : '';
                    var message = responseMessage || fallbackMessage;

                    var resetText = loadMoreSettings.loadMoreText || originalButtonText;
                    button.text(resetText);
                    button.prop('disabled', false);
                    showError(wrapper, message);
                }
            },
            error: function (jqXHR) {
                var errorMessage = '';

                if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                    errorMessage = jqXHR.responseJSON.data.message;
                }

                if (!errorMessage) {
                    errorMessage = loadMoreSettings.errorText || 'Une erreur est survenue. Veuillez réessayer plus tard.';
                }

                var resetText = loadMoreSettings.loadMoreText || originalButtonText;
                button.text(resetText);
                button.prop('disabled', false);
                showError(wrapper, errorMessage);
                console.error(errorMessage);
            }
        });
    });

})(jQuery);
