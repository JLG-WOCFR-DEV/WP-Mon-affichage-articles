// Fichier: assets/js/filter.js

function updateInstanceQueryParams(instanceId, params, targetWindow) {
    var win = targetWindow || (typeof window !== 'undefined' ? window : null);

    if (!win || !win.history) {
        return;
    }

    var historyApi = win.history;
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
        var currentUrl = win.location && win.location.href ? win.location.href : '';
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

function buildFilterFeedbackMessage(totalCount) {
    if (totalCount > 0) {
        return totalCount === 1 ? '1 article affiché.' : totalCount + ' articles affichés.';
    }

    return 'Aucun article à afficher.';
}

(function ($) {
    'use strict';

    var filterSettings = (typeof myArticlesFilter !== 'undefined') ? myArticlesFilter : {};

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

    $(document).on('click', '.my-articles-filter-nav button, .my-articles-filter-nav a', function (e) {
        e.preventDefault();

        var filterLink = $(this);
        var filterItem = filterLink.closest('li');
        var navList = filterItem.closest('ul');
        var previousActiveItem = navList.find('li.active').first();
        var categorySlug = filterLink.data('category');
        var wrapper = filterLink.closest('.my-articles-wrapper');
        var instanceId = wrapper.data('instance-id');
        var contentArea = wrapper.find('.my-articles-grid-content, .my-articles-list-content, .swiper-wrapper');

        if (filterItem.hasClass('active')) {
            return; // Ne rien faire si on clique sur le filtre déjà actif
        }

        navList.find('li').removeClass('active');
        navList.find('button, a').attr('aria-pressed', 'false');
        filterItem.addClass('active');
        filterLink.attr('aria-pressed', 'true');

        $.ajax({
            url: filterSettings.ajax_url,
            type: 'POST',
            data: {
                action: 'filter_articles',
                security: filterSettings.nonce || '',
                instance_id: instanceId,
                category: categorySlug,
                current_url: window.location && window.location.href ? window.location.href : ''
            },
            beforeSend: function () {
                contentArea.css('opacity', 0.5);
                contentArea.attr('aria-busy', 'true');
                wrapper.attr('aria-busy', 'true');
                clearFeedback(wrapper);
            },
            success: function (response) {
                if (response.success) {
                    var wrapperElement = (wrapper && wrapper.length) ? wrapper.get(0) : null;
                    contentArea.html(response.data.html);
                    contentArea.css('opacity', 1);
                    contentArea.attr('aria-busy', 'false');
                    wrapper.attr('aria-busy', 'false');

                    var totalPages = (response.data && typeof response.data.total_pages !== 'undefined') ? parseInt(response.data.total_pages, 10) : 0;
                    totalPages = isNaN(totalPages) ? 0 : totalPages;
                    var nextPage = (response.data && typeof response.data.next_page !== 'undefined') ? parseInt(response.data.next_page, 10) : 0;
                    nextPage = isNaN(nextPage) ? 0 : nextPage;
                    var pinnedIds = (response.data && typeof response.data.pinned_ids !== 'undefined') ? response.data.pinned_ids : '';

                    if (totalPages <= 1) {
                        var existingLoadMoreContainer = wrapper.find('.my-articles-load-more-container');
                        if (existingLoadMoreContainer.length) {
                            existingLoadMoreContainer.remove();
                        }
                    }

                    var loadMoreBtn = wrapper.find('.my-articles-load-more-btn');

                    if (!loadMoreBtn.length && totalPages > 1) {
                        var loadMoreText = (typeof myArticlesLoadMore !== 'undefined' && myArticlesLoadMore.loadMoreText) ? myArticlesLoadMore.loadMoreText : 'Charger plus';
                        var loadMoreContainer = $('<div class="my-articles-load-more-container"></div>');
                        var initialNextPage = nextPage > 0 ? nextPage : 2;
                        var newLoadMoreBtn = $('<button class="my-articles-load-more-btn"></button>')
                            .attr('data-instance-id', instanceId)
                            .attr('data-paged', initialNextPage)
                            .attr('data-total-pages', totalPages)
                            .attr('data-pinned-ids', pinnedIds)
                            .attr('data-category', categorySlug)
                            .text(loadMoreText);

                        loadMoreContainer.append(newLoadMoreBtn);

                        if (contentArea.length) {
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

                    if (response.data && typeof response.data.pagination_html !== 'undefined') {
                        var paginationHtml = response.data.pagination_html;
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
                        queryParams['paged_' + instanceId] = '1';
                        updateInstanceQueryParams(instanceId, queryParams);
                    }

                    var totalArticles = contentArea.find('.my-article-item').length;
                    var feedbackMessage = buildFilterFeedbackMessage(totalArticles);
                    var feedbackElement = getFeedbackElement(wrapper);
                    feedbackElement.removeClass('is-error').text(feedbackMessage).show();

                    var firstArticle = contentArea.find('.my-article-item').first();
                    focusOnFirstArticleOrTitle(wrapper, contentArea, firstArticle);
                } else {
                    filterItem.removeClass('active');
                    filterLink.attr('aria-pressed', 'false');
                    if (previousActiveItem && previousActiveItem.length) {
                        previousActiveItem.addClass('active');
                        previousActiveItem.find('button, a').first().attr('aria-pressed', 'true');
                    }

                    contentArea.css('opacity', 1);
                    contentArea.attr('aria-busy', 'false');
                    wrapper.attr('aria-busy', 'false');

                    var fallbackMessage = (filterSettings && filterSettings.errorText) ? filterSettings.errorText : 'Une erreur est survenue. Veuillez réessayer plus tard.';
                    var responseMessage = (response.data && response.data.message) ? response.data.message : '';
                    var message = responseMessage || fallbackMessage;
                    showError(wrapper, message);
                }
            },
            error: function (jqXHR) {
                filterItem.removeClass('active');
                filterLink.attr('aria-pressed', 'false');
                if (previousActiveItem && previousActiveItem.length) {
                    previousActiveItem.addClass('active');
                    previousActiveItem.find('button, a').first().attr('aria-pressed', 'true');
                }

                contentArea.css('opacity', 1);
                contentArea.attr('aria-busy', 'false');
                wrapper.attr('aria-busy', 'false');
                var errorMessage = '';

                if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                    errorMessage = jqXHR.responseJSON.data.message;
                }

                if (!errorMessage) {
                    errorMessage = (filterSettings && filterSettings.errorText) ? filterSettings.errorText : 'Une erreur est survenue. Veuillez réessayer plus tard.';
                }

                showError(wrapper, errorMessage);
            }
        });
    });

})(jQuery);

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        buildFilterFeedbackMessage: buildFilterFeedbackMessage,
        updateInstanceQueryParams: updateInstanceQueryParams
    };
}
