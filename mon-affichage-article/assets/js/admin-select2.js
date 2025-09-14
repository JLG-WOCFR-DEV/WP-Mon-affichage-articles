// Fichier: assets/js/admin-select2.js
(function ($) {
    'use strict';

    $(function () {
        var $selectField = $('.my-articles-post-selector');

        if ($selectField.length) {
            $selectField.select2({
                placeholder: 'Rechercher un contenu par son titre...',
                minimumInputLength: 3,
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    type: 'GET',
                    data: function (params) {
                        return {
                            action: 'search_posts_for_select2',
                            security: myArticlesSelect2.nonce,
                            search: params.term,
                            post_type: $('#post_type_selector').val() // On envoie le type de contenu sélectionné
                        };
                    },
                    processResults: function (response) {
                        return {
                            results: response.data
                        };
                    },
                    cache: true
                }
            });

            var $selection = $selectField.next('.select2-container').find('.select2-selection__rendered');

            $selection.sortable({
                placeholder: 'ui-sortable-placeholder',
                update: function() {
                    $selection.find('.select2-selection__choice').each(function() {
                        var postID = $(this).data('data').id;
                        var $option = $selectField.find('option[value="' + postID + '"]');
                        $selectField.append($option);
                    });
                }
            });
        }
    });

})(jQuery);