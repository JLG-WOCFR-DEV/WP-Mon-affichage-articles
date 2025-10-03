// Fichier: assets/js/filter.js
(function ($) {
    'use strict';

    var filterSettings = (typeof myArticlesFilter !== 'undefined') ? myArticlesFilter : {};
    var trackedNavElements = [];
    var evaluationHandle = null;
    var scheduler = (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function')
        ? window.requestAnimationFrame.bind(window)
        : function (callback) {
            return setTimeout(callback, 16);
        };
    var mobileMediaQuery = null;

    if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
        try {
            mobileMediaQuery = window.matchMedia('(max-width: 767px)');
        } catch (matchMediaError) {
            mobileMediaQuery = null;
        }
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
            // Silencieusement ignorer les erreurs (navigateurs plus anciens)
        }
    }

    function formatCountMessage(template, count) {
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

    function resolveFilterLabel(key, fallback) {
        if (filterSettings && Object.prototype.hasOwnProperty.call(filterSettings, key)) {
            var value = filterSettings[key];
            if (typeof value === 'string' && value.length > 0) {
                return value;
            }
        }

        return fallback;
    }

    function buildFilterFeedbackMessage(totalCount, activeLabel) {
        var fallbackSingle = '%s article affiché.';
        var fallbackPlural = '%s articles affichés.';
        var fallbackNone = 'Aucun article à afficher.';

        var singleLabel = resolveFilterLabel('countSingle', fallbackSingle);
        var pluralLabel = resolveFilterLabel('countPlural', fallbackPlural);
        var noneLabel = resolveFilterLabel('countNone', fallbackNone);

        var countMessage = '';
        if (totalCount > 0) {
            if (totalCount === 1) {
                var formattedSingle = formatCountMessage(singleLabel, totalCount) || formatCountMessage(fallbackSingle, totalCount);
                countMessage = formattedSingle || fallbackSingle.replace('%s', String(totalCount));
            }

            var formattedPlural = formatCountMessage(pluralLabel, totalCount) || formatCountMessage(fallbackPlural, totalCount);
            if (formattedPlural) {
                countMessage = formattedPlural;
            }

            if (!countMessage) {
                countMessage = fallbackPlural.replace('%s', String(totalCount));
            }
        } else {
            countMessage = noneLabel || fallbackNone;
        }

        var trimmedLabel = '';
        if (typeof activeLabel === 'string') {
            trimmedLabel = activeLabel.trim();
        }

        if (trimmedLabel) {
            var filterTemplate = resolveFilterLabel('activeFilter', 'Filtre actif : %s.');
            var labelMessage = '';

            if (filterTemplate.indexOf('%s') !== -1) {
                labelMessage = filterTemplate.replace(/%s/g, trimmedLabel);
            } else {
                labelMessage = filterTemplate + ' ' + trimmedLabel;
            }

            if (countMessage) {
                return labelMessage + ' ' + countMessage;
            }

            return labelMessage;
        }

        return countMessage;
    }

    function getActiveFilterLabel(nav) {
        if (!nav || !nav.length) {
            return '';
        }

        var activeControl = nav.find('li.active [data-category]').first();

        if (!activeControl.length) {
            return '';
        }

        var label = activeControl.text();

        if (typeof label === 'string') {
            label = label.trim();
        }

        if (!label && activeControl.attr('aria-label')) {
            label = activeControl.attr('aria-label');
        }

        if (typeof label === 'string') {
            return label.trim();
        }

        return '';
    }

    function ensureMobileSelectOptions(nav) {
        if (!nav || !nav.length) {
            return;
        }

        var select = nav.find('.my-articles-filter-nav__select');
        if (!select.length) {
            return;
        }

        var options = [];
        nav.find('li').each(function () {
            var item = $(this);
            var control = item.find('[data-category]').first();
            if (!control.length) {
                return;
            }

            var slug = control.data('category');
            if (typeof slug === 'undefined' || slug === null) {
                return;
            }

            var label = control.text();
            if (typeof label === 'string') {
                label = label.trim();
            }

            if (!label && control.attr('aria-label')) {
                label = control.attr('aria-label');
            }

            if (typeof label !== 'string') {
                label = '';
            }

            options.push({
                slug: String(slug),
                label: label,
                active: item.hasClass('active'),
            });
        });

        if (!options.length) {
            return;
        }

        var needsRebuild = false;
        var existingValues = select.find('option').map(function () {
            return $(this).val();
        }).get();

        if (existingValues.length !== options.length) {
            needsRebuild = true;
        } else {
            for (var index = 0; index < options.length; index += 1) {
                if (existingValues[index] !== options[index].slug) {
                    needsRebuild = true;
                    break;
                }
            }
        }

        if (needsRebuild) {
            select.empty();
            options.forEach(function (option) {
                var optionElement = $('<option></option>')
                    .attr('value', option.slug)
                    .text(option.label);

                if (option.active) {
                    optionElement.prop('selected', true);
                }

                select.append(optionElement);
            });
        } else {
            options.forEach(function (option, index) {
                var optionElement = select.find('option').eq(index);
                optionElement.text(option.label);
                optionElement.prop('selected', !!option.active);
            });
        }
    }

    function syncMobileSelect(nav, slug) {
        if (!nav || !nav.length) {
            return;
        }

        var select = nav.find('.my-articles-filter-nav__select');
        if (!select.length) {
            return;
        }

        ensureMobileSelectOptions(nav);

        var targetValue = slug;
        if (typeof targetValue === 'undefined' || targetValue === null) {
            var activeControl = nav.find('li.active [data-category]').first();
            if (activeControl.length) {
                targetValue = activeControl.data('category');
            }
        }

        if (typeof targetValue === 'undefined' || targetValue === null) {
            return;
        }

        var normalizedValue = String(targetValue);
        select.each(function () {
            var currentValue = $(this).val();
            if (currentValue !== normalizedValue) {
                $(this).val(normalizedValue);
            }
        });
    }

    function computeDesiredMobileState(nav) {
        if (!nav || !nav.length) {
            return 'list';
        }

        var behavior = nav.data('mobileBehavior');
        if ('select' !== behavior && 'scroll' !== behavior) {
            return 'list';
        }

        var isNarrow = false;
        if (mobileMediaQuery && typeof mobileMediaQuery.matches === 'boolean') {
            isNarrow = mobileMediaQuery.matches;
        } else {
            var viewportWidth = window.innerWidth || document.documentElement.clientWidth || 1024;
            isNarrow = viewportWidth <= 767;
        }

        var scroller = nav.find('.my-articles-filter-nav__scroller').get(0);
        var hasOverflow = false;
        if (scroller) {
            hasOverflow = (scroller.scrollWidth - scroller.clientWidth) > 4;
        }

        var filterCountAttr = parseInt(nav.attr('data-filter-count'), 10);
        if (isNaN(filterCountAttr) || filterCountAttr <= 0) {
            filterCountAttr = nav.find('li').length;
        }

        var threshold = behavior === 'select' ? 4 : 3;
        var hasManyFilters = filterCountAttr > threshold;

        if (isNarrow || hasOverflow || hasManyFilters) {
            return behavior;
        }

        return 'list';
    }

    function updateScrollShadowsForNav(nav) {
        if (!nav || !nav.length) {
            return;
        }

        var scroller = nav.find('.my-articles-filter-nav__scroller').get(0);
        if (!scroller) {
            nav.removeClass('has-left-shadow has-right-shadow');
            return;
        }

        if (!nav.hasClass('mobile-scroll-active')) {
            nav.removeClass('has-left-shadow has-right-shadow');
            return;
        }

        var maxScroll = scroller.scrollWidth - scroller.clientWidth;
        if (maxScroll <= 2) {
            nav.removeClass('has-left-shadow has-right-shadow');
            return;
        }

        if (scroller.scrollLeft <= 1) {
            nav.removeClass('has-left-shadow');
        } else {
            nav.addClass('has-left-shadow');
        }

        if (scroller.scrollLeft >= maxScroll - 1) {
            nav.removeClass('has-right-shadow');
        } else {
            nav.addClass('has-right-shadow');
        }
    }

    function setNavState(nav, state) {
        if (!nav || !nav.length) {
            return;
        }

        var scroller = nav.find('.my-articles-filter-nav__scroller');
        var mobileContainer = nav.find('.my-articles-filter-nav__mobile');

        if ('select' === state) {
            nav.addClass('mobile-select-active').removeClass('mobile-scroll-active has-left-shadow has-right-shadow');
            if (scroller.length) {
                scroller.attr('aria-hidden', 'true');
            }
            if (mobileContainer.length) {
                mobileContainer.attr('aria-hidden', 'false');
                mobileContainer.removeAttr('hidden');
            }
        } else if ('scroll' === state) {
            nav.addClass('mobile-scroll-active').removeClass('mobile-select-active');
            if (scroller.length) {
                scroller.removeAttr('aria-hidden');
            }
            if (mobileContainer.length) {
                mobileContainer.attr('aria-hidden', 'true');
                if (!mobileContainer.attr('hidden')) {
                    mobileContainer.attr('hidden', 'hidden');
                }
            }
            updateScrollShadowsForNav(nav);
        } else {
            nav.removeClass('mobile-select-active mobile-scroll-active has-left-shadow has-right-shadow');
            if (scroller.length) {
                scroller.removeAttr('aria-hidden');
            }
            if (mobileContainer.length) {
                mobileContainer.attr('aria-hidden', 'true');
                if (!mobileContainer.attr('hidden')) {
                    mobileContainer.attr('hidden', 'hidden');
                }
            }
        }

        nav.attr('data-mobile-state', state);
    }

    function applyMobileState(nav) {
        if (!nav || !nav.length) {
            return;
        }

        ensureMobileSelectOptions(nav);
        var desiredState = computeDesiredMobileState(nav);
        var currentState = nav.attr('data-mobile-state') || 'list';

        if (currentState !== desiredState) {
            setNavState(nav, desiredState);
        } else if ('scroll' === desiredState) {
            updateScrollShadowsForNav(nav);
        }

        syncMobileSelect(nav);
    }

    function bindScroller(nav) {
        if (!nav || !nav.length) {
            return;
        }

        var scroller = nav.find('.my-articles-filter-nav__scroller');
        if (!scroller.length) {
            return;
        }

        scroller.off('.myArticlesFilter').on('scroll.myArticlesFilter', function () {
            updateScrollShadowsForNav(nav);
        });
    }

    function pruneTrackedNavs() {
        var next = [];

        for (var index = 0; index < trackedNavElements.length; index += 1) {
            var element = trackedNavElements[index];
            if (!element) {
                continue;
            }

            if (element.isConnected) {
                next.push(element);
                continue;
            }

            if (element.ownerDocument && element.ownerDocument.documentElement && element.ownerDocument.documentElement.contains(element)) {
                next.push(element);
            }
        }

        trackedNavElements = next;
    }

    function evaluateAllNavs() {
        pruneTrackedNavs();

        trackedNavElements.forEach(function (element) {
            var nav = $(element);
            applyMobileState(nav);
        });
    }

    function scheduleEvaluateAllNavs() {
        if (evaluationHandle !== null) {
            return;
        }

        evaluationHandle = scheduler(function () {
            evaluationHandle = null;
            evaluateAllNavs();
        });
    }

    function registerNav(nav) {
        if (!nav || !nav.length) {
            return;
        }

        var element = nav.get(0);
        if (!element) {
            return;
        }

        if (!element.__myArticlesMobileFilterInitialized) {
            element.__myArticlesMobileFilterInitialized = true;
            trackedNavElements.push(element);
        }

        bindScroller(nav);
        applyMobileState(nav);
    }

    function initFilterNavs(target) {
        var scope;
        if (!target) {
            scope = $(document);
        } else if (target.jquery) {
            scope = target;
        } else {
            scope = $(target);
        }

        scope.find('.my-articles-filter-nav').addBack('.my-articles-filter-nav').each(function () {
            registerNav($(this));
        });

        scheduleEvaluateAllNavs();
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

    window.myArticlesInitFilters = initFilterNavs;

    if (typeof window !== 'undefined') {
        $(window).on('resize.myArticlesFilter orientationchange.myArticlesFilter', function () {
            scheduleEvaluateAllNavs();
        });
    }

    if (mobileMediaQuery) {
        var mediaListener = function () {
            scheduleEvaluateAllNavs();
        };

        if (typeof mobileMediaQuery.addEventListener === 'function') {
            mobileMediaQuery.addEventListener('change', mediaListener);
        } else if (typeof mobileMediaQuery.addListener === 'function') {
            mobileMediaQuery.addListener(mediaListener);
        }
    }

    $(function () {
        initFilterNavs(document);
    });

    $(document).on('change', '.my-articles-filter-nav__select', function () {
        var select = $(this);
        var nav = select.closest('.my-articles-filter-nav');

        if (!nav.length) {
            return;
        }

        var selectedValue = select.val();
        if (typeof selectedValue === 'undefined' || selectedValue === null) {
            return;
        }

        var normalizedValue = String(selectedValue);
        var targetControl = nav.find('[data-category]').filter(function () {
            return String($(this).data('category')) === normalizedValue;
        }).first();

        if (!targetControl.length) {
            return;
        }

        var targetItem = targetControl.closest('li');
        if (targetItem.length && targetItem.hasClass('active')) {
            return;
        }

        targetControl.trigger('click');
    });

    $(document).on('click', '.my-articles-filter-nav button, .my-articles-filter-nav a', function (e) {
        e.preventDefault();

        var filterLink = $(this);
        var filterItem = filterLink.closest('li');
        var navList = filterItem.closest('ul');
        var previousActiveItem = navList.find('li.active').first();
        var nav = filterItem.closest('.my-articles-filter-nav');
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
        syncMobileSelect(nav, categorySlug);
        scheduleEvaluateAllNavs();

        var requestUrl = (filterSettings && typeof filterSettings.endpoint === 'string') ? filterSettings.endpoint : '';

        if (!requestUrl && filterSettings && typeof filterSettings.restRoot === 'string') {
            requestUrl = filterSettings.restRoot.replace(/\/+$/, '') + '/my-articles/v1/filter';
        }

        $.ajax({
            url: requestUrl,
            type: 'POST',
            headers: {
                'X-WP-Nonce': filterSettings && filterSettings.restNonce ? filterSettings.restNonce : ''
            },
            data: {
                instance_id: instanceId,
                category: categorySlug,
                current_url: window.location && window.location.href ? window.location.href : ''
            },
            beforeSend: function () {
                if (wrapper && wrapper.length) {
                    wrapper.attr('aria-busy', 'true');
                    wrapper.addClass('is-loading');
                }
                clearFeedback(wrapper);
            },
            success: function (response) {
                if (response.success) {
                    var wrapperElement = (wrapper && wrapper.length) ? wrapper.get(0) : null;
                    contentArea.html(response.data.html);

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

                    if (typeof window.myArticlesInitFilters === 'function') {
                        window.myArticlesInitFilters(wrapperElement);
                    }

                    syncMobileSelect(nav, categorySlug);
                    scheduleEvaluateAllNavs();

                    if (instanceId) {
                        var queryParams = {};
                        queryParams['my_articles_cat_' + instanceId] = categorySlug || null;
                        queryParams['paged_' + instanceId] = '1';
                        updateInstanceQueryParams(instanceId, queryParams);
                    }

                    var totalArticles = contentArea.find('.my-article-item').length;
                    var activeFilterLabel = getActiveFilterLabel(nav);
                    var feedbackMessage = buildFilterFeedbackMessage(totalArticles, activeFilterLabel);
                    var feedbackElement = getFeedbackElement(wrapper);
                    feedbackElement.removeClass('is-error')
                        .removeAttr('role')
                        .attr('aria-live', 'polite')
                        .text(feedbackMessage)
                        .show();

                    var firstArticle = contentArea.find('.my-article-item').first();
                    focusOnFirstArticleOrTitle(wrapper, contentArea, firstArticle);
                } else {
                    filterItem.removeClass('active');
                    filterLink.attr('aria-pressed', 'false');
                    if (previousActiveItem && previousActiveItem.length) {
                        previousActiveItem.addClass('active');
                        previousActiveItem.find('button, a').first().attr('aria-pressed', 'true');
                    }

                    syncMobileSelect(nav);
                    scheduleEvaluateAllNavs();

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

                syncMobileSelect(nav);
                scheduleEvaluateAllNavs();

                var errorMessage = '';

                if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                    errorMessage = jqXHR.responseJSON.data.message;
                }

                if (!errorMessage) {
                    errorMessage = (filterSettings && filterSettings.errorText) ? filterSettings.errorText : 'Une erreur est survenue. Veuillez réessayer plus tard.';
                }

                showError(wrapper, errorMessage);
            },
            complete: function () {
                if (wrapper && wrapper.length) {
                    wrapper.attr('aria-busy', 'false');
                    wrapper.removeClass('is-loading');
                }
            }
        });
    });

})(jQuery);
