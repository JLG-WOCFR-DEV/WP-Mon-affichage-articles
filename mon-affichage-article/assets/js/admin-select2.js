// Fichier: assets/js/admin-select2.js
(function ($) {
    'use strict';

    $(function () {
        var $selectField = $('.my-articles-post-selector');
        var select2Settings = (typeof myArticlesSelect2 !== 'undefined') ? myArticlesSelect2 : {};

        function displaySelect2Error(message) {
            var fallbackMessage = select2Settings.errorMessage || select2Settings.genericErrorText || 'Une erreur est survenue.';

            window.alert(message || fallbackMessage);
        }

        if ($selectField.length) {
            $selectField.select2({
                placeholder: select2Settings.placeholder || '',
                minimumInputLength: 3,
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    type: 'GET',
                    data: function (params) {
                        return {
                            action: 'search_posts_for_select2',
                            security: select2Settings.nonce || '',
                            search: params.term,
                            post_type: $('#post_type_selector').val() // On envoie le type de contenu sélectionné
                        };
                    },
                    processResults: function (response) {
                        if (!response || response.success === false) {
                            var message = '';

                            if (response && response.data) {
                                if (typeof response.data === 'string') {
                                    message = response.data;
                                } else if (response.data.message) {
                                    message = response.data.message;
                                }
                            }

                            displaySelect2Error(message);

                            return {
                                results: []
                            };
                        }

                        if (!Array.isArray(response.data)) {
                            return {
                                results: []
                            };
                        }

                        return {
                            results: response.data
                        };
                    },
                    error: function (xhr) {
                        var message = '';

                        if (xhr && xhr.responseJSON && xhr.responseJSON.data) {
                            message = xhr.responseJSON.data.message || xhr.responseJSON.data;
                        }

                        displaySelect2Error(message);
                    },
                    cache: true
                }
            });

            $selectField.on('select2:open', function () {
                var $container = $(this).next('.select2-container');
                $container.attr('aria-hidden', 'false');
            });

            $selectField.on('select2:close', function () {
                var $container = $(this).next('.select2-container');
                var scheduleFocusCheck = window.requestAnimationFrame || function (callback) {
                    window.setTimeout(callback, 0);
                };

                scheduleFocusCheck(function () {
                    if (!$container.find(':focus').length) {
                        $container.attr('aria-hidden', 'true');
                    }
                });
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
