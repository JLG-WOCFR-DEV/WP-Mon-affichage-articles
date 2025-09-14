// Fichier: assets/js/filter.js
(function ($) {
    'use strict';

    $(document).on('click', '.my-articles-filter-nav a', function (e) {
        e.preventDefault();

        var filterLink = $(this);
        var categorySlug = filterLink.data('category');
        var wrapper = filterLink.closest('.my-articles-wrapper');
        var instanceId = wrapper.data('instance-id');
        var contentArea = wrapper.find('.my-articles-grid-content, .swiper-wrapper');
        
        if (filterLink.parent().hasClass('active')) {
            return; // Ne rien faire si on clique sur le filtre déjà actif
        }

        filterLink.parent().siblings().removeClass('active');
        filterLink.parent().addClass('active');

        $.ajax({
            url: myArticlesFilter.ajax_url,
            type: 'POST',
            data: {
                action: 'filter_articles',
                security: myArticlesFilter.nonce,
                instance_id: instanceId,
                category: categorySlug,
            },
            beforeSend: function () {
                contentArea.css('opacity', 0.5);
            },
            success: function (response) {
                if (response.success) {
                    contentArea.html(response.data.html);
                    contentArea.css('opacity', 1);

                    if (wrapper.hasClass('my-articles-slideshow')) {
                        if (typeof window.mySwiperInstances !== 'undefined' && window.mySwiperInstances[instanceId]) {
                            window.mySwiperInstances[instanceId].update();
                        }
                    }
                } else {
                    contentArea.css('opacity', 1);
                }
            },
            error: function () {
                contentArea.css('opacity', 1);
                console.error('Erreur AJAX.');
            }
        });
    });

})(jQuery);