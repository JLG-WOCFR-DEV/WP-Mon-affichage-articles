// Fichier: assets/js/load-more.js
(function ($) {
    'use strict';

    $(document).on('click', '.my-articles-load-more-btn', function (e) {
        e.preventDefault();

        var button = $(this);
        var wrapper = button.closest('.my-articles-wrapper');
        // CORRECTION : On cible le conteneur de la grille OU de la liste
        var contentArea = wrapper.find('.my-articles-grid-content, .my-articles-list-content');
        
        var instanceId = button.data('instance-id');
        var paged = button.data('paged');
        var totalPages = button.data('total-pages');
        var pinnedIds = button.data('pinned-ids');
        var category = button.data('category');

        $.ajax({
            url: myArticlesLoadMore.ajax_url,
            type: 'POST',
            data: {
                action: 'load_more_articles',
                security: myArticlesLoadMore.nonce,
                instance_id: instanceId,
                paged: paged,
                pinned_ids: pinnedIds,
                category: category
            },
            beforeSend: function () {
                button.text('Chargement...');
                button.prop('disabled', true);
            },
            success: function (response) {
                if (response.success) {
                    // Ajoute les nouveaux articles à la suite des anciens
                    contentArea.append(response.data.html);
                    
                    var newPage = paged + 1;
                    button.data('paged', newPage);

                    if (newPage > totalPages) {
                        // S'il n'y a plus de page, on cache le bouton
                        button.hide();
                    } else {
                        button.text('Charger plus');
                        button.prop('disabled', false);
                    }
                } else {
                    // En cas d'erreur, on cache le bouton pour éviter de boucler
                    button.hide();
                }
            },
            error: function () {
                button.hide();
                console.error('Erreur AJAX.');
            }
        });
    });

})(jQuery);