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

                    // Ajoute les nouveaux articles à la suite des anciens
                    if (typeof responseData.html !== 'undefined') {
                        contentArea.append(responseData.html);

                        if (wrapper.hasClass('my-articles-slideshow')) {
                            var swiperInstance = null;

                            if (typeof window.mySwiperInstances !== 'undefined' && instanceId && window.mySwiperInstances[instanceId]) {
                                swiperInstance = window.mySwiperInstances[instanceId];
                            }

                            if (!swiperInstance || typeof swiperInstance.update !== 'function') {
                                var settingsObjectName = 'myArticlesSwiperSettings_' + instanceId;

                                if (typeof window[settingsObjectName] !== 'undefined') {
                                    var settings = window[settingsObjectName];

                                    window.mySwiperInstances = window.mySwiperInstances || {};
                                    swiperInstance = new Swiper(settings.container_selector, {
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
                                                var mainWrapper = document.querySelector('#my-articles-wrapper-' + instanceId);
                                                if (mainWrapper) {
                                                    mainWrapper.classList.add('swiper-initialized');
                                                }
                                            }
                                        }
                                    });

                                    window.mySwiperInstances[instanceId] = swiperInstance;
                                }
                            } else {
                                swiperInstance.update();
                            }
                        }
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
