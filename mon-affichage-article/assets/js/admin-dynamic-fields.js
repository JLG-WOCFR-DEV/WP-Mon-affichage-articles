(function ($) {
    'use strict';

    function updateTaxonomySelector() {
        var $postTypeSelector = $('#post_type_selector');
        var $taxonomyWrapper = $('#taxonomy_selector_wrapper');
        var $termWrapper = $('#term_selector_wrapper');
        var selectedPostType = $postTypeSelector.val();

        if (!selectedPostType) {
            $taxonomyWrapper.hide();
            $termWrapper.hide();
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_post_type_taxonomies',
                security: myArticlesAdmin.nonce,
                post_type: selectedPostType
            },
            beforeSend: function() {
                $taxonomyWrapper.find('select').prop('disabled', true);
                $termWrapper.hide();
            },
            success: function (response) {
                if (response.success && response.data.length > 0) {
                    var $taxonomySelect = $taxonomyWrapper.find('select');
                    var currentTax = $taxonomySelect.data('current');
                    $taxonomySelect.empty();
                    
                    $.each(response.data, function (index, tax) {
                        $taxonomySelect.append($('<option>', {
                            value: tax.name,
                            text: tax.label,
                            selected: (tax.name === currentTax)
                        }));
                    });

                    $taxonomyWrapper.show();
                    $taxonomySelect.prop('disabled', false).trigger('change');
                } else {
                    $taxonomyWrapper.hide();
                    $termWrapper.hide();
                }
            }
        });
    }

    function updateTermSelector() {
        var $taxonomySelector = $('#taxonomy_selector');
        var $termWrapper = $('#term_selector_wrapper');
        var selectedTaxonomy = $taxonomySelector.val();

        if (!selectedTaxonomy) {
            $termWrapper.hide();
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_taxonomy_terms',
                security: myArticlesAdmin.nonce,
                taxonomy: selectedTaxonomy
            },
            beforeSend: function() {
                $termWrapper.find('select').prop('disabled', true);
            },
            success: function (response) {
                if (response.success && response.data.length > 0) {
                    var $termSelect = $termWrapper.find('select');
                    var currentTerm = $termSelect.data('current');
                    $termSelect.empty();
                    
                    $termSelect.append($('<option>', { value: '', text: 'Toutes les catégories' }));

                    $.each(response.data, function (index, term) {
                        $termSelect.append($('<option>', {
                            value: term.slug,
                            text: term.name,
                            selected: (term.slug === currentTerm)
                        }));
                    });

                    $termWrapper.show();
                    $termSelect.prop('disabled', false);
                } else {
                    $termWrapper.hide();
                }
            }
        });
    }

    $(document).ready(function() {
        $('#post_type_selector').on('change', updateTaxonomySelector);
        $('#taxonomy_selector').on('change', updateTermSelector);

        // Déclenche au chargement pour initialiser les champs
        updateTaxonomySelector();
    });

})(jQuery);