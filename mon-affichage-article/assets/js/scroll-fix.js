(function ($) {
    'use strict';

    $(document).ready(function() {
        // Crée un objet pour lire les paramètres de l'URL
        var urlParams = new URLSearchParams(window.location.search);
        var pagedParamKey = null;

        // Cherche n'importe quel paramètre qui commence par 'paged_'
        for (var key of urlParams.keys()) {
            if (key.startsWith('paged_')) {
                pagedParamKey = key;
                break;
            }
        }

        // Si on a trouvé un paramètre de pagination dans l'URL
        if (pagedParamKey) {
            // On extrait l'ID de l'instance (ex: de 'paged_8808' on garde '8808')
            var instanceId = pagedParamKey.replace('paged_', '');
            var wrapperSelector = '#my-articles-wrapper-' + instanceId;
            var $wrapper = $(wrapperSelector);

            // Si le module correspondant existe sur la page
            if ($wrapper.length) {
                // On attend un court instant pour s'assurer que tout est bien chargé
                setTimeout(function() {
                    // On fait défiler la page en douceur jusqu'en haut du module
                    // Le -50 crée un petit espace au-dessus, c'est plus joli
                    $('html, body').animate({
                        scrollTop: $wrapper.offset().top - 50 
                    }, 300); // 300 millisecondes pour l'animation
                }, 100);
            }
        }
    });

})(jQuery);
