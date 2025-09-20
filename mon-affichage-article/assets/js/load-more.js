// Fichier: assets/js/load-more.js
(function ($) {
    'use strict';

    var loadMoreSettings = (typeof myArticlesLoadMore !== 'undefined') ? myArticlesLoadMore : {};

    $(document).on('click', '.my-articles-load-more-btn', function (e) {
        e.preventDefault();

        var button = $(this);
        var wrapper = button.closest('.my-articles-wrapper');
        // CORRECTION : On cible le conteneur de la grille OU de la liste
        var contentArea = wrapper.find('.my-articles-grid-content, .my-articles-list-content');
        
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
                button.text(loadMoreSettings.loadingText || button.text());
                button.prop('disabled', true);
            },
            success: function (response) {
                if (response.success) {
                    // Ajoute les nouveaux articles à la suite des anciens
                    contentArea.append(response.data.html);

                    if (response.data && typeof response.data.pinned_ids !== 'undefined') {
                        var updatedPinnedIds = response.data.pinned_ids;
                        button.data('pinned-ids', updatedPinnedIds);
                        button.attr('data-pinned-ids', updatedPinnedIds);
                    }

                    var newPage = paged + 1;
                    button.data('paged', newPage);
                    button.attr('data-paged', newPage);

                    button.text(loadMoreSettings.loadMoreText || button.text());

                    if (!totalPages || newPage > totalPages) {
                        // S'il n'y a plus de page, on cache le bouton
                        button.hide();
                        button.prop('disabled', false);
                        return;
                    }

                    button.prop('disabled', false);
                } else {
                    // En cas d'erreur, on cache le bouton pour éviter de boucler
                    button.hide();
                    button.prop('disabled', false);
                }
            },
            error: function () {
                button.hide();
                button.prop('disabled', false);
                console.error(loadMoreSettings.errorText || 'AJAX error.');
            }
        });
    });

})(jQuery);
