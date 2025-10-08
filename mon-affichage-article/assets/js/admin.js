// Fichier: assets/js/admin.js
(function( $ ) {
    'use strict';
    $(function() {
        $('.my-color-picker').wpColorPicker();

        const adminContainer = document.querySelector('.my-articles-admin');
        if (adminContainer) {
            const themeOptions = adminContainer.querySelectorAll('.my-articles-theme-toggle__option input[name="my_articles_options[admin_theme]"]');
            themeOptions.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    if (!radio.checked) {
                        return;
                    }
                    adminContainer.setAttribute('data-theme', radio.value);
                });
            });
        }
    });
})( jQuery );
