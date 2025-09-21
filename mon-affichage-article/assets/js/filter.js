// Fichier: assets/js/filter.js
(function ($) {
    'use strict';

    var filterSettings = (typeof myArticlesFilter !== 'undefined') ? myArticlesFilter : {};

    $(document).on('click', '.my-articles-filter-nav a', function (e) {
        e.preventDefault();

        var filterLink = $(this);
        var categorySlug = filterLink.data('category');
        var wrapper = filterLink.closest('.my-articles-wrapper');
        var instanceId = wrapper.data('instance-id');
        var contentArea = wrapper.find('.my-articles-grid-content, .my-articles-list-content, .swiper-wrapper');
        
        if (filterLink.parent().hasClass('active')) {
            return; // Ne rien faire si on clique sur le filtre déjà actif
        }

        filterLink.parent().siblings().removeClass('active');
        filterLink.parent().addClass('active');

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
            },
            success: function (response) {
                if (response.success) {
                    contentArea.html(response.data.html);
                    contentArea.css('opacity', 1);

                    var loadMoreBtn = wrapper.find('.my-articles-load-more-btn');
                    if (loadMoreBtn.length) {
                        var totalPages = (response.data && typeof response.data.total_pages !== 'undefined') ? parseInt(response.data.total_pages, 10) : 0;
                        totalPages = isNaN(totalPages) ? 0 : totalPages;
                        var nextPage = (response.data && typeof response.data.next_page !== 'undefined') ? parseInt(response.data.next_page, 10) : 0;
                        nextPage = isNaN(nextPage) ? 0 : nextPage;
                        var pinnedIds = (response.data && typeof response.data.pinned_ids !== 'undefined') ? response.data.pinned_ids : '';

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

                    if (wrapper.hasClass('my-articles-slideshow')) {
                        if (typeof window.mySwiperInstances !== 'undefined' && window.mySwiperInstances[instanceId]) {
                            window.mySwiperInstances[instanceId].destroy(true, true);
                        }

                        var settingsObjectName = 'myArticlesSwiperSettings_' + instanceId;
                        if (typeof window[settingsObjectName] !== 'undefined') {
                            var settings = window[settingsObjectName];

                            window.mySwiperInstances = window.mySwiperInstances || {};

                            window.mySwiperInstances[instanceId] = new Swiper(settings.container_selector, {
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
                        }
                    }
                } else {
                    contentArea.css('opacity', 1);
                }
            },
            error: function () {
                contentArea.css('opacity', 1);
                var errorMessage = filterSettings.errorText || 'AJAX error.';
                console.error(errorMessage);
            }
        });
    });

})(jQuery);
