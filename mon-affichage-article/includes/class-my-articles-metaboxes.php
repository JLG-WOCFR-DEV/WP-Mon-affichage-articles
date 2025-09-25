<?php
// Fichier: includes/class-my-articles-metaboxes.php

if ( ! defined( 'WPINC' ) ) {
    die;
}

class My_Articles_Metaboxes {

    private static $instance;
    private $option_key = '_my_articles_settings';

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_mon_affichage', array( $this, 'save_meta_data' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'admin_head', array( $this, 'add_admin_head_styles' ) );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( ('post.php' === $hook || 'post-new.php' === $hook) && 'mon_affichage' === get_post_type() ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_style( 'select2-css', MY_ARTICLES_PLUGIN_URL . 'assets/vendor/select2/select2.min.css', [], '4.1.0-rc.0' );
            
            wp_enqueue_script( 'my-articles-admin-script', MY_ARTICLES_PLUGIN_URL . 'assets/js/admin.js', array( 'wp-color-picker' ), MY_ARTICLES_VERSION, true );
            wp_enqueue_script( 'select2-js', MY_ARTICLES_PLUGIN_URL . 'assets/vendor/select2/select2.min.js', array('jquery'), '4.1.0-rc.0', true );
            wp_enqueue_script( 'my-articles-admin-select2', MY_ARTICLES_PLUGIN_URL . 'assets/js/admin-select2.js', array('select2-js', 'jquery-ui-sortable'), MY_ARTICLES_VERSION, true );
            wp_enqueue_script( 'my-articles-admin-options', MY_ARTICLES_PLUGIN_URL . 'assets/js/admin-options.js', array('jquery'), MY_ARTICLES_VERSION, true );
            wp_enqueue_script( 'my-articles-dynamic-fields', MY_ARTICLES_PLUGIN_URL . 'assets/js/admin-dynamic-fields.js', array('jquery'), MY_ARTICLES_VERSION, true );

            wp_localize_script(
                'my-articles-admin-select2',
                'myArticlesSelect2',
                [
                    'nonce'       => wp_create_nonce('my_articles_select2_nonce'),
                    'placeholder' => __( 'Rechercher un contenu par son titre...', 'mon-articles' ),
                ]
            );
            wp_localize_script(
                'my-articles-dynamic-fields',
                'myArticlesAdmin',
                [
                    'nonce'             => wp_create_nonce('my_articles_admin_nonce'),
                    'allCategoriesText' => __( 'Toutes les catégories', 'mon-articles' ),
                ]
            );
            wp_localize_script(
                'my-articles-admin-options',
                'myArticlesAdminOptions',
                [
                    'minColumnWidth'  => 240,
                    'warningThreshold' => 960,
                    'warningClass'    => 'my-articles-columns-warning--active',
                    /* translators: 1: number of columns, 2: estimated width in pixels. */
                    'warningMessage'  => __( 'Attention : %1$d colonnes nécessitent environ %2$spx de largeur. Réduisez le nombre de colonnes ou agrandissez la zone de contenu.', 'mon-articles' ),
                    /* translators: 1: number of columns, 2: estimated width in pixels. */
                    'infoMessage'     => __( 'Largeur estimée : %2$spx pour %1$d colonnes.', 'mon-articles' ),
                ]
            );
        }
    }

    public function add_admin_head_styles() {
        if ( 'mon_affichage' === get_post_type() ) {
            echo '<style>
                .select2-container--default .select2-selection--multiple .select2-selection__choice { cursor: move; }
                .ui-sortable-placeholder { border: 1px dashed #ccc; background-color: #f7f7f7; height: 31px; margin: 5px 0 3px 6px; }
            </style>';
        }
    }

    public function add_meta_boxes() {
        add_meta_box('my_articles_settings_metabox', __('Réglages de l\'Affichage', 'mon-articles'), array( $this, 'render_main_metabox' ), 'mon_affichage', 'normal', 'high');
        add_meta_box('my_articles_shortcode_metabox', __('Shortcode à utiliser', 'mon-articles'), array( $this, 'render_shortcode_metabox' ), 'mon_affichage', 'side', 'high');
    }

    public function render_shortcode_metabox( $post ) {
        if ( $post->ID && 'auto-draft' !== $post->post_status ) {
            echo '<p>' . __( 'Copiez ce shortcode dans vos pages ou articles :', 'mon-articles' ) . '</p>';
            echo '<input type="text" value="[mon_affichage_articles id=&quot;' . esc_attr( $post->ID ) . '&quot;]" readonly style="width: 100%; padding: 8px; text-align: center;">';
        } else {
            echo '<p>' . __( 'Enregistrez cet affichage pour générer le shortcode.', 'mon-articles' ) . '</p>';
        }
    }

    public function render_main_metabox( $post ) {
        wp_nonce_field( 'my_articles_save_meta_box_data', 'my_articles_meta_box_nonce' );
        $opts = (array) get_post_meta( $post->ID, $this->option_key, true );

        echo '<p style="font-size: 14px; background-color: #f0f6fc; border-left: 4px solid #72aee6; padding: 10px;"><strong>Tutoriel :</strong> copier / coller le shortcode dans une balise HTML de Wordpress.</p>';

        echo '<h3>' . __('Contenu & Filtres', 'mon-articles') . '</h3>';
        $this->render_field('post_type', __('Source du contenu', 'mon-articles'), 'post_type_select', $opts);
        $this->render_field('taxonomy', __('Filtrer par taxonomie', 'mon-articles'), 'taxonomy_select', $opts);
        $this->render_field('term', __('Filtrer par catégorie/terme', 'mon-articles'), 'term_select', $opts);
        $this->render_field('counting_behavior', __('Comportement du comptage', 'mon-articles'), 'select', $opts, [ 'default' => 'exact', 'options' => [ 'exact' => 'Nombre exact', 'auto_fill' => 'Remplissage automatique (Grille complète)' ] ]);
        $this->render_field(
            'posts_per_page',
            __('Nombre d\'articles souhaité', 'mon-articles'),
            'number',
            $opts,
            [
                'default'     => 10,
                'min'         => 0,
                'max'         => 50,
                'description' => __( 'Le nombre exact ou approximatif selon le comportement choisi. Utilisez 0 pour un affichage illimité.', 'mon-articles' ),
            ]
        );
        $this->render_field('pagination_mode', __('Type de pagination', 'mon-articles'), 'select', $opts, [ 'default' => 'none', 'options' => [ 'none' => 'Aucune', 'load_more' => 'Bouton "Charger plus"', 'numbered' => 'Liens numérotés' ], 'description' => 'Ne s\'applique pas au mode Diaporama.' ]);
        $this->render_field('show_category_filter', __('Afficher le filtre de catégories', 'mon-articles'), 'checkbox', $opts, ['default' => 0]);
        $this->render_field('filter_alignment', __('Alignement du filtre', 'mon-articles'), 'select', $opts, [ 'default' => 'right', 'options' => ['left' => 'Gauche', 'center' => 'Centre', 'right' => 'Droite'] ]);
        $this->render_field(
            'filter_categories',
            __('Catégories à inclure dans le filtre', 'mon-articles'),
            'category_checklist',
            $opts,
            [ 'taxonomy' => $opts['taxonomy'] ?? '' ]
        );
        
        echo '<hr><h3>' . __('Articles Épinglés', 'mon-articles') . '</h3>';
        $this->render_field('pinned_posts', __('Choisir les articles à épingler', 'mon-articles'), 'select2_ajax', $opts);
        $this->render_field('pinned_posts_ignore_filter', __('Toujours afficher les articles épinglés', 'mon-articles'), 'checkbox', $opts, ['default' => 0, 'description' => 'Si coché, les articles épinglés s\'afficheront toujours, même si une autre catégorie est sélectionnée dans le filtre.']);
        $this->render_field('pinned_border_color', __('Couleur de bordure (épinglés)', 'mon-articles'), 'color', $opts, ['default' => '#eab308']);
        $this->render_field('pinned_show_badge', __('Afficher un badge sur les épinglés', 'mon-articles'), 'checkbox', $opts, ['default' => 0]);
        $this->render_field('pinned_badge_text', __('Texte du badge', 'mon-articles'), 'text', $opts, ['default' => 'Épinglé', 'wrapper_class' => 'badge-option']);
        $this->render_field('pinned_badge_bg_color', __('Couleur de fond du badge', 'mon-articles'), 'color', $opts, ['default' => '#eab308', 'wrapper_class' => 'badge-option']);
        $this->render_field('pinned_badge_text_color', __('Couleur du texte du badge', 'mon-articles'), 'color', $opts, ['default' => '#ffffff', 'wrapper_class' => 'badge-option']);

        echo '<hr><h3>' . __('Exclusions & Comportements Avancés', 'mon-articles') . '</h3>';
        $this->render_field('exclude_posts', __('ID des articles à exclure', 'mon-articles'), 'text', $opts, ['description' => 'Séparez les ID par des virgules (ex: 21, 56). Ces articles n\'apparaîtront jamais.']);
        $this->render_field('ignore_native_sticky', __('Ignorer les articles "Épinglés" de WordPress', 'mon-articles'), 'checkbox', $opts, ['default' => 1, 'description' => 'Cochez pour ignorer les articles marqués comme "épinglés" dans l\'éditeur WordPress.']);

        echo '<hr><h3>' . __('Mise en Page', 'mon-articles') . '</h3>';
        $this->render_field('display_mode', __('Mode d\'affichage', 'mon-articles'), 'select', $opts, [ 'default' => 'grid', 'options' => [ 'grid' => 'Grille', 'slideshow' => 'Diaporama', 'list' => 'Liste' ] ]);

        echo '<div class="my-articles-columns-warning" data-field="columns_mobile">';
        $this->render_field('columns_mobile', __('Colonnes (Mobile < 768px)', 'mon-articles'), 'number', $opts, ['default' => 1, 'min' => 1, 'max' => 3, 'description' => 'Pour Grille et Diaporama']);
        echo '<p class="my-articles-columns-warning__message" aria-live="polite"></p>';
        echo '</div>';

        echo '<div class="my-articles-columns-warning" data-field="columns_tablet">';
        $this->render_field('columns_tablet', __('Colonnes (Tablette ≥ 768px)', 'mon-articles'), 'number', $opts, ['default' => 2, 'min' => 1, 'max' => 4, 'description' => 'Pour Grille et Diaporama']);
        echo '<p class="my-articles-columns-warning__message" aria-live="polite"></p>';
        echo '</div>';

        echo '<div class="my-articles-columns-warning" data-field="columns_desktop">';
        $this->render_field('columns_desktop', __('Colonnes (Desktop ≥ 1024px)', 'mon-articles'), 'number', $opts, ['default' => 3, 'min' => 1, 'max' => 6, 'description' => 'Pour Grille et Diaporama']);
        echo '<p class="my-articles-columns-warning__message" aria-live="polite"></p>';
        echo '</div>';

        echo '<div class="my-articles-columns-warning" data-field="columns_ultrawide">';
        $this->render_field('columns_ultrawide', __('Colonnes (Ultra-Wide ≥ 1536px)', 'mon-articles'), 'number', $opts, ['default' => 4, 'min' => 1, 'max' => 8, 'description' => 'Pour Grille et Diaporama']);
        echo '<p class="my-articles-columns-warning__message" aria-live="polite"></p>';
        echo '</div>';

        echo '<hr><h3>' . __('Apparence & Performances', 'mon-articles') . '</h3>';
        $this->render_field('module_padding_left', __('Marge intérieure gauche (px)', 'mon-articles'), 'number', $opts, ['default' => 0, 'min' => 0, 'max' => 200]);
        $this->render_field('module_padding_right', __('Marge intérieure droite (px)', 'mon-articles'), 'number', $opts, ['default' => 0, 'min' => 0, 'max' => 200]);
        $this->render_field('gap_size', __('Espacement des vignettes (Grille)', 'mon-articles'), 'number', $opts, ['default' => 25, 'min' => 0, 'max' => 50]);
        $this->render_field('list_item_gap', __('Espacement vertical (Liste)', 'mon-articles'), 'number', $opts, ['default' => 25, 'min' => 0, 'max' => 50]);
        $this->render_field('border_radius', __('Arrondi des bordures (px)', 'mon-articles'), 'number', $opts, ['default' => 12, 'min' => 0, 'max' => 50]);
        $this->render_field('enable_lazy_load', __('Activer le chargement paresseux des images (Lazy Load)', 'mon-articles'), 'checkbox', $opts, ['default' => 1, 'description' => 'Améliore considérablement la vitesse de chargement de la page.']);
        
        echo '<h4>' . __('Marge intérieur du contenu (Mode liste)', 'mon-articles') . '</h4>';
        $this->render_field('list_content_padding_top', __('Haut (px)', 'mon-articles'), 'number', $opts, ['default' => 0, 'min' => 0, 'max' => 100]);
        $this->render_field('list_content_padding_right', __('Droite (px)', 'mon-articles'), 'number', $opts, ['default' => 0, 'min' => 0, 'max' => 100]);
        $this->render_field('list_content_padding_bottom', __('Bas (px)', 'mon-articles'), 'number', $opts, ['default' => 0, 'min' => 0, 'max' => 100]);
        $this->render_field('list_content_padding_left', __('Gauche (px)', 'mon-articles'), 'number', $opts, ['default' => 0, 'min' => 0, 'max' => 100]);
        
        echo '<hr><h3>' . __('Couleurs & Ombres', 'mon-articles') . '</h3>';
        $this->render_field('module_bg_color', __('Fond du module', 'mon-articles'), 'color', $opts, ['default' => 'rgba(255,255,255,0)', 'alpha' => true]);
        $this->render_field('vignette_bg_color', __('Fond de la vignette', 'mon-articles'), 'color', $opts, ['default' => '#ffffff']);
        $this->render_field('title_wrapper_bg_color', __('Fond du bloc titre', 'mon-articles'), 'color', $opts, ['default' => '#ffffff']);
        $this->render_field('title_color', __('Couleur du titre', 'mon-articles'), 'color', $opts, ['default' => '#333333']);
        $this->render_field('meta_color', __('Couleur du texte (méta)', 'mon-articles'), 'color', $opts, ['default' => '#6b7280']);
        $this->render_field('meta_color_hover', __('Couleur (méta, survol)', 'mon-articles'), 'color', $opts, ['default' => '#000000']);
        $this->render_field('pagination_color', __('Couleur de la pagination (Diaporama)', 'mon-articles'), 'color', $opts, ['default' => '#333333']);
        $this->render_field('shadow_color', __('Couleur de l\'ombre', 'mon-articles'), 'color', $opts, ['default' => 'rgba(0,0,0,0.07)', 'alpha' => true]);
        $this->render_field('shadow_color_hover', __('Couleur de l\'ombre (survol)', 'mon-articles'), 'color', $opts, ['default' => 'rgba(0,0,0,0.12)', 'alpha' => true]);

        echo '<hr><h3>' . __('Polices & Méta-données', 'mon-articles') . '</h3>';
        $this->render_field('title_font_size', __('Taille de police du titre (px)', 'mon-articles'), 'number', $opts, ['default' => 16, 'min' => 10, 'max' => 40]);
        $this->render_field('meta_font_size', __('Taille de police (méta)', 'mon-articles'), 'number', $opts, ['default' => 12, 'min' => 8, 'max' => 20]);
        $this->render_field('show_category', __('Afficher la catégorie', 'mon-articles'), 'checkbox', $opts, ['default' => 1]);
        $this->render_field('show_author', __('Afficher l\'auteur', 'mon-articles'), 'checkbox', $opts, ['default' => 1]);
        $this->render_field('show_date', __('Afficher la date', 'mon-articles'), 'checkbox', $opts, ['default' => 1]);

        echo '<hr><h3>' . __('Extrait', 'mon-articles') . '</h3>';
        $this->render_field('show_excerpt', __('Afficher l\'extrait', 'mon-articles'), 'checkbox', $opts, ['default' => 0]);
        $this->render_field('excerpt_length', __('Longueur de l\'extrait (mots)', 'mon-articles'), 'number', $opts, ['default' => 25, 'wrapper_class' => 'excerpt-option']);
        $this->render_field('excerpt_more_text', __('Texte "Lire la suite"', 'mon-articles'), 'text', $opts, ['default' => 'Lire la suite', 'wrapper_class' => 'excerpt-option']);
        $this->render_field('excerpt_font_size', __('Taille de police (px)', 'mon-articles'), 'number', $opts, ['default' => 14, 'wrapper_class' => 'excerpt-option']);
        $this->render_field('excerpt_color', __('Couleur du texte', 'mon-articles'), 'color', $opts, ['default' => '#4b5563', 'wrapper_class' => 'excerpt-option']);
        
        echo '<hr><h3 style="color: red;">' . __('Débogage', 'mon-articles') . '</h3>';
        $this->render_field('enable_debug_mode', __('Activer le mode de débogage', 'mon-articles'), 'checkbox', $opts, ['default' => 0, 'description' => 'Affiche des informations techniques sous le module sur le site public. À n\'utiliser que pour résoudre un problème.']);
    }

    private function render_field($id, $label, $type, $opts, $args = []) {
        $name = esc_attr($this->option_key . '[' . $id . ']');
        $value = $opts[$id] ?? ($args['default'] ?? '');
        $wrapper_class = isset($args['wrapper_class']) ? ' class="' . esc_attr($args['wrapper_class']) . '"' : '';
        echo '<table' . $wrapper_class . '><tr style="vertical-align: top;"><th style="width:250px; text-align: left; padding-left: 0;"><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label></th><td>';

        switch ($type) {
            case 'text':
                printf('<input type="text" id="%s" name="%s" value="%s" class="regular-text" />', esc_attr($id), $name, esc_attr($value));
                if (isset($args['description'])) {
                    echo '<p class="description">' . esc_html($args['description']) . '</p>';
                }
                break;
            case 'select2_ajax':
                $saved_ids = is_array($value) ? $value : array();
                echo '<select name="' . $name . '[]" class="my-articles-post-selector" multiple="multiple" style="width: 100%;">';
                if (!empty($saved_ids)) {
                    foreach ($saved_ids as $post_id) {
                        echo '<option value="' . esc_attr($post_id) . '" selected="selected">' . esc_html(get_the_title($post_id)) . '</option>';
                    }
                }
                echo '</select>';
                break;
            case 'number':
                printf('<input type="number" id="%s" name="%s" value="%s" min="%d" max="%d" />', esc_attr($id), $name, esc_attr($value), $args['min'] ?? 0, $args['max'] ?? 100);
                 if (isset($args['description'])) {
                    echo '<p class="description" style="margin-left: 0; font-style: italic;">' . esc_html($args['description']) . '</p>';
                }
                break;
            case 'color':
                $alpha = ! empty( $args['alpha'] ) ? 'data-alpha-enabled="true"' : '';
                printf('<input type="text" id="%s" name="%s" value="%s" class="my-color-picker" %s />', esc_attr($id), $name, esc_attr($value), $alpha);
                break;
            case 'select':
                echo '<select id="' . esc_attr($id) . '" name="' . $name . '">';
                foreach ($args['options'] as $val => $text) {
                    echo '<option value="' . esc_attr($val) . '" ' . selected($value, $val, false) . '>' . esc_html($text) . '</option>';
                }
                echo '</select>';
                 if (isset($args['description'])) {
                    echo '<p class="description" style="font-style: italic;">' . esc_html($args['description']) . '</p>';
                }
                break;
            case 'checkbox':
                printf('<input type="checkbox" id="%s" name="%s" value="1" %s />', esc_attr($id), $name, checked($value, 1, false));
                if (isset($args['description'])) {
                    echo '<p class="description" style="margin-left: 0; font-style: italic;">' . esc_html($args['description']) . '</p>';
                }
                break;
            case 'category_checklist':
                $saved_cats = is_array($value) ? array_map('absint', $value) : array();
                $selected_taxonomy = isset($args['taxonomy']) ? sanitize_key($args['taxonomy']) : '';
                if (empty($selected_taxonomy) || !taxonomy_exists($selected_taxonomy)) {
                    $selected_taxonomy = taxonomy_exists('category') ? 'category' : '';
                }

                if (!empty($selected_taxonomy)) {
                    $terms = get_terms([
                        'taxonomy'   => $selected_taxonomy,
                        'hide_empty' => false,
                    ]);

                    if (!is_wp_error($terms) && !empty($terms)) {
                        echo '<div style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">';
                        foreach ($terms as $term) {
                            $checked = in_array($term->term_id, $saved_cats, true) ? 'checked' : '';
                            echo '<label style="display: block;"><input type="checkbox" name="' . $name . '[]" value="' . esc_attr($term->term_id) . '" ' . $checked . '> ' . esc_html($term->name) . '</label>';
                        }
                        echo '</div>';
                    } else {
                        echo '<p class="description">' . esc_html__('Aucun terme disponible pour cette taxonomie.', 'mon-articles') . '</p>';
                    }
                } else {
                    echo '<p class="description">' . esc_html__('Aucune taxonomie valide disponible.', 'mon-articles') . '</p>';
                }

                echo '<p class="description">' . __('Si aucune catégorie n\'est cochée, toutes seront affichées.', 'mon-articles') . '</p>';
                break;
            case 'post_type_select':
                $post_types = my_articles_get_selectable_post_types();

                if ( empty( $post_types ) ) {
                    $fallback_post_type = get_post_type_object( 'post' );
                    if ( $fallback_post_type ) {
                        $post_types = array( 'post' => $fallback_post_type );
                    }
                }

                $value = my_articles_normalize_post_type( $value );

                echo '<select id="post_type_selector" name="' . $name . '">';
                foreach ( $post_types as $post_type ) {
                    echo '<option value="' . esc_attr( $post_type->name ) . '" ' . selected( $value, $post_type->name, false ) . '>' . esc_html( $post_type->labels->singular_name ) . '</option>';
                }
                echo '</select>';
                break;
            case 'taxonomy_select':
                echo '<div id="taxonomy_selector_wrapper" style="display:none;"><select id="taxonomy_selector" name="' . $name . '" data-current="' . esc_attr($value) . '"></select></div>';
                break;
            case 'term_select':
                echo '<div id="term_selector_wrapper" style="display:none;"><select id="term_selector" name="' . $name . '" data-current="' . esc_attr($value) . '"></select></div>';
                break;
        }
        echo '</td></tr></table>';
    }

    public function save_meta_data( $post_id ) {
        if ( !isset($_POST['my_articles_meta_box_nonce']) || !wp_verify_nonce( wp_unslash( $_POST['my_articles_meta_box_nonce'] ), 'my_articles_save_meta_box_data') || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id) ) {
            return;
        }

        $input = isset( $_POST[$this->option_key] ) ? wp_unslash( $_POST[$this->option_key] ) : [];
        $sanitized = [];
        
        $sanitized['post_type'] = my_articles_normalize_post_type( $input['post_type'] ?? '' );
        $sanitized['taxonomy'] = isset($input['taxonomy']) ? sanitize_key($input['taxonomy']) : '';
        $sanitized['term'] = isset($input['term']) ? sanitize_text_field( $input['term'] ) : '';
        $sanitized['counting_behavior'] = isset($input['counting_behavior']) && in_array($input['counting_behavior'], ['exact', 'auto_fill']) ? $input['counting_behavior'] : 'exact';
        $sanitized['posts_per_page'] = isset( $input['posts_per_page'] ) ? max( 0, absint( $input['posts_per_page'] ) ) : 10;
        $sanitized['pagination_mode'] = isset($input['pagination_mode']) && in_array($input['pagination_mode'], ['none', 'load_more', 'numbered']) ? $input['pagination_mode'] : 'none';
        $sanitized['show_category_filter'] = isset( $input['show_category_filter'] ) ? 1 : 0;
        $sanitized['filter_alignment'] = isset($input['filter_alignment']) && in_array($input['filter_alignment'], ['left', 'center', 'right']) ? $input['filter_alignment'] : 'right';
        
        $sanitized['filter_categories'] = array();
        if ( isset($input['filter_categories']) && is_array($input['filter_categories']) ) {
            $sanitized['filter_categories'] = array_map('absint', $input['filter_categories']);
        }

        $sanitized['pinned_posts'] = array();
        if ( isset($input['pinned_posts']) && is_array($input['pinned_posts']) ) {
            $sanitized['pinned_posts'] = array_map('absint', $input['pinned_posts']);
        }
        $sanitized['pinned_posts_ignore_filter'] = isset( $input['pinned_posts_ignore_filter'] ) ? 1 : 0;
        $sanitized['pinned_border_color'] = my_articles_sanitize_color($input['pinned_border_color'] ?? '', '#eab308');
        $sanitized['pinned_show_badge'] = isset( $input['pinned_show_badge'] ) ? 1 : 0;
        $sanitized['pinned_badge_text'] = isset( $input['pinned_badge_text'] ) ? sanitize_text_field( wp_unslash( $input['pinned_badge_text'] ) ) : 'Épinglé';
        $sanitized['pinned_badge_bg_color'] = my_articles_sanitize_color($input['pinned_badge_bg_color'] ?? '', '#eab308');
        $sanitized['pinned_badge_text_color'] = my_articles_sanitize_color($input['pinned_badge_text_color'] ?? '', '#ffffff');
        
        if (isset($input['exclude_posts'])) {
            $cleaned_ids = preg_replace('/[^0-9,]/', '', wp_unslash( $input['exclude_posts'] ) );
            $id_array = array_filter(array_map('absint', explode(',', $cleaned_ids)));
            $sanitized['exclude_posts'] = implode(',', $id_array);
        } else {
            $sanitized['exclude_posts'] = '';
        }
        $sanitized['ignore_native_sticky'] = isset( $input['ignore_native_sticky'] ) ? 1 : 0;
        $sanitized['enable_lazy_load'] = isset( $input['enable_lazy_load'] ) ? 1 : 0;
        $sanitized['enable_debug_mode'] = isset( $input['enable_debug_mode'] ) ? 1 : 0;

        $sanitized['display_mode'] = in_array($input['display_mode'] ?? 'grid', ['grid', 'slideshow', 'list']) ? $input['display_mode'] : 'grid';
        $sanitized['columns_mobile'] = isset( $input['columns_mobile'] ) ? max( 1, absint( $input['columns_mobile'] ) ) : 1;
        $sanitized['columns_tablet'] = isset( $input['columns_tablet'] ) ? max( 1, absint( $input['columns_tablet'] ) ) : 2;
        $sanitized['columns_desktop'] = isset( $input['columns_desktop'] ) ? max( 1, absint( $input['columns_desktop'] ) ) : 3;
        $sanitized['columns_ultrawide'] = isset( $input['columns_ultrawide'] ) ? max( 1, absint( $input['columns_ultrawide'] ) ) : 4;
        $sanitized['module_padding_left'] = isset( $input['module_padding_left'] ) ? absint( $input['module_padding_left'] ) : 0;
        $sanitized['module_padding_right'] = isset( $input['module_padding_right'] ) ? absint( $input['module_padding_right'] ) : 0;
        $sanitized['gap_size'] = isset( $input['gap_size'] ) ? absint( $input['gap_size'] ) : 25;
        $sanitized['list_item_gap'] = isset( $input['list_item_gap'] ) ? absint( $input['list_item_gap'] ) : 25;
        $sanitized['list_content_padding_top'] = isset( $input['list_content_padding_top'] ) ? absint( $input['list_content_padding_top'] ) : 0;
        $sanitized['list_content_padding_right'] = isset( $input['list_content_padding_right'] ) ? absint( $input['list_content_padding_right'] ) : 0;
        $sanitized['list_content_padding_bottom'] = isset( $input['list_content_padding_bottom'] ) ? absint( $input['list_content_padding_bottom'] ) : 0;
        $sanitized['list_content_padding_left'] = isset( $input['list_content_padding_left'] ) ? absint( $input['list_content_padding_left'] ) : 0;
        $sanitized['border_radius'] = isset( $input['border_radius'] ) ? absint( $input['border_radius'] ) : 12;
        $sanitized['title_font_size'] = isset( $input['title_font_size'] ) ? absint( $input['title_font_size'] ) : 16;
        $sanitized['meta_font_size'] = isset( $input['meta_font_size'] ) ? absint( $input['meta_font_size'] ) : 12;
        $sanitized['show_category'] = isset( $input['show_category'] ) ? 1 : 0;
        $sanitized['show_author'] = isset( $input['show_author'] ) ? 1 : 0;
        $sanitized['show_date'] = isset( $input['show_date'] ) ? 1 : 0;

        $sanitized['show_excerpt'] = isset( $input['show_excerpt'] ) ? 1 : 0;
        $sanitized['excerpt_length'] = isset( $input['excerpt_length'] ) ? absint($input['excerpt_length']) : 25;
        $sanitized['excerpt_more_text'] = isset( $input['excerpt_more_text'] ) ? sanitize_text_field( wp_unslash( $input['excerpt_more_text'] ) ) : 'Lire la suite';
        $sanitized['excerpt_font_size'] = isset( $input['excerpt_font_size'] ) ? absint($input['excerpt_font_size']) : 14;
        $sanitized['excerpt_color'] = my_articles_sanitize_color($input['excerpt_color'] ?? '', '#4b5563');

        $sanitized['module_bg_color'] = my_articles_sanitize_color($input['module_bg_color'] ?? '', 'rgba(255,255,255,0)');
        $sanitized['vignette_bg_color'] = my_articles_sanitize_color($input['vignette_bg_color'] ?? '', '#ffffff');
        $sanitized['title_wrapper_bg_color'] = my_articles_sanitize_color($input['title_wrapper_bg_color'] ?? '', '#ffffff');
        $sanitized['title_color'] = my_articles_sanitize_color($input['title_color'] ?? '', '#333333');
        $sanitized['meta_color'] = my_articles_sanitize_color($input['meta_color'] ?? '', '#6b7280');
        $sanitized['meta_color_hover'] = my_articles_sanitize_color($input['meta_color_hover'] ?? '', '#000000');
        $sanitized['pagination_color'] = my_articles_sanitize_color($input['pagination_color'] ?? '', '#333333');
        $sanitized['shadow_color'] = my_articles_sanitize_color($input['shadow_color'] ?? '', 'rgba(0,0,0,0.07)');
        $sanitized['shadow_color_hover'] = my_articles_sanitize_color($input['shadow_color_hover'] ?? '', 'rgba(0,0,0,0.12)');

        update_post_meta( $post_id, $this->option_key, $sanitized );
    }

}
