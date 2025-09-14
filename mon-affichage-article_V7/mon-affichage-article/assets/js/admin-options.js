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

    // Écouteurs d'événements
    $(document).on('change', '#pinned_show_badge', toggleBadgeOptions);
    $(document).on('change', '#show_excerpt', toggleExcerptOptions);

    // Exécution au chargement de la page pour définir l'état initial
    $(document).ready(function() {
        toggleBadgeOptions();
        toggleExcerptOptions();
    });

})(jQuery);