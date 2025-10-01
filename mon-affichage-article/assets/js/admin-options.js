// Fichier: assets/js/admin-options.js
(function ($) {
    'use strict';

    // Fonction pour les options du badge
    function toggleBadgeOptions() {
        var $badgeCheckbox = $('#pinned_show_badge');
        var $badgeOptions = $('.badge-option');

        if ($badgeCheckbox.is(':checked')) {
            $badgeOptions.show();
        } else {
            $badgeOptions.hide();
        }
    }
    
    // NOUVELLE FONCTION pour les options de l'extrait
    function toggleExcerptOptions() {
        var $excerptCheckbox = $('#show_excerpt');
        var $excerptOptions = $('.excerpt-option');

        if ($excerptCheckbox.is(':checked')) {
            $excerptOptions.show();
        } else {
            $excerptOptions.hide();
        }
    }

    function toggleMetaKeyOption() {
        var $orderbySelect = $('#orderby');
        var $metaKeyOption = $('.meta-key-option');

        if (!$metaKeyOption.length) {
            return;
        }

        if ($orderbySelect.length && $orderbySelect.val() === 'meta_value') {
            $metaKeyOption.show();
        } else {
            $metaKeyOption.hide();
        }
    }

    var columnSelectors = '#columns_mobile, #columns_tablet, #columns_desktop, #columns_ultrawide';
    var columnsConfigDefaults = {
        minColumnWidth: 240,
        warningThreshold: 960,
        warningClass: 'my-articles-columns-warning--active',
        warningMessage: '',
        infoMessage: ''
    };
    var columnsConfig = $.extend({}, columnsConfigDefaults, window.myArticlesAdminOptions || {});

    columnsConfig.minColumnWidth = parseFloat(columnsConfig.minColumnWidth) || columnsConfigDefaults.minColumnWidth;
    columnsConfig.warningThreshold = parseFloat(columnsConfig.warningThreshold) || (columnsConfig.minColumnWidth * 4);

    function formatColumnsMessage(template, columns, estimatedWidth) {
        if (typeof template !== 'string' || !template.length) {
            return '';
        }

        return template
            .replace(/%1\$[sd]/g, columns)
            .replace(/%2\$[sd]/g, estimatedWidth);
    }

    function updateColumnWarning($input) {
        if (!$input || !$input.length) {
            return;
        }

        var columns = parseInt($input.val(), 10);

        if (isNaN(columns) || columns < 0) {
            columns = 0;
        }

        var estimatedWidth = Math.round(columns * columnsConfig.minColumnWidth);
        var $container = $input.closest('.my-articles-columns-warning');

        if (!$container.length) {
            return;
        }

        var $message = $container.find('.my-articles-columns-warning__message');

        if (!$message.length) {
            $message = $('<p>', {
                class: 'my-articles-columns-warning__message',
                'aria-live': 'polite'
            });

            $container.append($message);
        }

        var shouldWarn = estimatedWidth > columnsConfig.warningThreshold && columns > 0;

        if (shouldWarn) {
            if (columnsConfig.warningClass) {
                $container.addClass(columnsConfig.warningClass);
            }

            if (columnsConfig.warningMessage) {
                $message.text(formatColumnsMessage(columnsConfig.warningMessage, columns, estimatedWidth));
            } else {
                $message.text('');
            }
        } else {
            if (columnsConfig.warningClass) {
                $container.removeClass(columnsConfig.warningClass);
            }

            if (columnsConfig.infoMessage && columns > 0) {
                $message.text(formatColumnsMessage(columnsConfig.infoMessage, columns, estimatedWidth));
            } else {
                $message.text('');
            }
        }
    }

    function initColumnsWarnings() {
        if (!$(columnSelectors).length) {
            return;
        }

        $(document).on('input change', columnSelectors, function() {
            updateColumnWarning($(this));
        });

        $(columnSelectors).each(function() {
            updateColumnWarning($(this));
        });
    }

    // Écouteurs d'événements
    $(document).on('change', '#pinned_show_badge', toggleBadgeOptions);
    $(document).on('change', '#show_excerpt', toggleExcerptOptions);
    $(document).on('change', '#orderby', toggleMetaKeyOption);

    // Exécution au chargement de la page pour définir l'état initial
    $(document).ready(function() {
        toggleBadgeOptions();
        toggleExcerptOptions();
        toggleMetaKeyOption();
        initColumnsWarnings();
    });

})(jQuery);
