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
        if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
            return;
        }

        $post_type = '';

        // Prefer the global $typenow when available.
        if ( isset( $GLOBALS['typenow'] ) && is_string( $GLOBALS['typenow'] ) ) {
            $post_type = $GLOBALS['typenow'];
        }

        if ( '' === $post_type && function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();

            if ( $screen && isset( $screen->post_type ) ) {
                $post_type = $screen->post_type;
            }
        }

        if ( '' === $post_type && 'post.php' === $hook ) {
            $post_type = (string) get_post_type();
        }

        if ( 'mon_affichage' !== $post_type ) {
            return;
        }

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
                'placeholder' => esc_html__( 'Rechercher un contenu par son titre...', 'mon-articles' ),
            ]
        );
        wp_localize_script(
            'my-articles-dynamic-fields',
            'myArticlesAdmin',
            [
                'nonce'             => wp_create_nonce('my_articles_admin_nonce'),
                'allCategoriesText' => esc_html__( 'Toutes les catégories', 'mon-articles' ),
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
                'warningMessage'  => esc_html__( 'Attention : %1$d colonnes nécessitent environ %2$spx de largeur. Réduisez le nombre de colonnes ou agrandissez la zone de contenu.', 'mon-articles' ),
                /* translators: 1: number of columns, 2: estimated width in pixels. */
                'infoMessage'     => esc_html__( 'Largeur estimée : %2$spx pour %1$d colonnes.', 'mon-articles' ),
            ]
        );
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
        add_meta_box('my_articles_settings_metabox', esc_html__('Réglages de l\'Affichage', 'mon-articles'), array( $this, 'render_main_metabox' ), 'mon_affichage', 'normal', 'high');
        add_meta_box('my_articles_shortcode_metabox', esc_html__('Shortcode à utiliser', 'mon-articles'), array( $this, 'render_shortcode_metabox' ), 'mon_affichage', 'side', 'high');
    }

    public function render_shortcode_metabox( $post ) {
        if ( $post->ID && 'auto-draft' !== $post->post_status ) {
            echo '<p>' . esc_html__( 'Copiez ce shortcode dans vos pages ou articles :', 'mon-articles' ) . '</p>';
            echo '<input type="text" value="[mon_affichage_articles id=&quot;' . esc_attr( $post->ID ) . '&quot;]" readonly style="width: 100%; padding: 8px; text-align: center;">';
        } else {
            echo '<p>' . esc_html__( 'Enregistrez cet affichage pour générer le shortcode.', 'mon-articles' ) . '</p>';
        }
    }

    public function render_main_metabox( $post ) {
        wp_nonce_field( 'my_articles_save_meta_box_data', 'my_articles_meta_box_nonce' );
        $opts = (array) get_post_meta( $post->ID, $this->option_key, true );

        if ( ! isset( $opts['sort'] ) && isset( $opts['orderby'] ) ) {
            $opts['sort'] = $opts['orderby'];
        }

        $helper_message = wp_kses_post(
            __('<strong>Tutoriel :</strong> copier / coller le shortcode dans une balise HTML de WordPress.', 'mon-articles')
        );

        printf(
            '<p style="font-size: 14px; background-color: #f0f6fc; border-left: 4px solid #72aee6; padding: 10px;">%s</p>',
            $helper_message
        );

        echo '<h3>' . esc_html__('Contenu & Filtres', 'mon-articles') . '</h3>';
        $this->render_field(
            'post_type',
            esc_html__( 'Source du contenu', 'mon-articles' ),
            'post_type_select',
            $opts,
            [
                'input_id' => 'post_type_selector',
            ]
        );
        $this->render_field(
            'taxonomy',
            esc_html__( 'Filtrer par taxonomie', 'mon-articles' ),
            'taxonomy_select',
            $opts,
            [
                'input_id' => 'taxonomy_selector',
            ]
        );
        $this->render_field(
            'term',
            esc_html__( 'Filtrer par catégorie/terme', 'mon-articles' ),
            'term_select',
            $opts,
            [
                'input_id' => 'term_selector',
            ]
        );
        $this->render_field(
            'tax_filters',
            esc_html__( 'Filtres additionnels', 'mon-articles' ),
            'taxonomy_filter_select',
            $opts,
            [
                'post_type'    => $opts['post_type'] ?? '',
                'description'  => esc_html__( 'Sélectionnez des couples taxonomie + terme appliqués en permanence au module.', 'mon-articles' ),
            ]
        );
        $this->render_field('counting_behavior', esc_html__('Comportement du comptage', 'mon-articles'), 'select', $opts, [ 'default' => 'exact', 'options' => [ 'exact' => __('Nombre exact', 'mon-articles'), 'auto_fill' => __('Remplissage automatique (Grille complète)', 'mon-articles') ] ]);
        $this->render_field(
            'posts_per_page',
            esc_html__('Nombre d\'articles souhaité', 'mon-articles'),
            'number',
            $opts,
            [
                'default'     => 10,
                'min'         => 0,
                'max'         => 50,
                'description' => esc_html__( 'Le nombre exact ou approximatif selon le comportement choisi. Utilisez 0 pour un affichage illimité.', 'mon-articles' ),
            ]
        );
        $this->render_field(
            'sort',
            esc_html__( 'Ordre de tri', 'mon-articles' ),
            'select',
            $opts,
            [
                'default'     => 'date',
                'options'     => [
                    'date'          => __( 'Date de publication', 'mon-articles' ),
                    'title'         => __( 'Titre', 'mon-articles' ),
                    'menu_order'    => __( 'Ordre du menu', 'mon-articles' ),
                    'meta_value'    => __( 'Méta personnalisée', 'mon-articles' ),
                    'comment_count' => __( 'Nombre de commentaires', 'mon-articles' ),
                    'post__in'      => __( 'Ordre personnalisé (post__in)', 'mon-articles' ),
                ],
                'description' => __( 'Choisissez le champ principal utilisé pour trier les contenus.', 'mon-articles' ),
            ]
        );
        $this->render_field(
            'order',
            esc_html__( 'Sens du tri', 'mon-articles' ),
            'select',
            $opts,
            [
                'default' => 'DESC',
                'options' => [
                    'DESC' => __( 'Décroissant (Z → A)', 'mon-articles' ),
                    'ASC'  => __( 'Croissant (A → Z)', 'mon-articles' ),
                ],
            ]
        );
        $this->render_field(
            'meta_key',
            esc_html__( 'Clé de méta personnalisée', 'mon-articles' ),
            'text',
            $opts,
            [
                'default'       => '',
                'wrapper_class' => 'meta-key-option',
                'description'   => __( 'Renseignez la clé utilisée lors d\'un tri par méta personnalisée.', 'mon-articles' ),
            ]
        );
        $this->render_field('pagination_mode', esc_html__('Type de pagination', 'mon-articles'), 'select', $opts, [
            'default'     => 'none',
            'options'     => [
                'none'      => __('Aucune', 'mon-articles'),
                'load_more' => __('Bouton "Charger plus"', 'mon-articles'),
                'numbered'  => __('Liens numérotés', 'mon-articles'),
            ],
            'description' => __('Ne s\'applique pas au mode Diaporama.', 'mon-articles'),
        ]);
        $this->render_field('load_more_auto', esc_html__('Déclencher automatiquement le bouton « Charger plus »', 'mon-articles'), 'checkbox', $opts, [
            'default'     => 0,
            'description' => __('Lance la requête dès que le bouton devient visible. Se désactive automatiquement si le navigateur est incompatible ou après un clic manuel.', 'mon-articles'),
        ]);
        $this->render_field(
            'enable_keyword_search',
            esc_html__( 'Activer la recherche par mots-clés', 'mon-articles' ),
            'checkbox',
            $opts,
            [
                'default'     => 0,
                'description' => __( 'Affiche un champ permettant aux visiteurs de filtrer par mots-clés.', 'mon-articles' ),
            ]
        );
        $this->render_field('show_category_filter', esc_html__('Afficher le filtre de catégories', 'mon-articles'), 'checkbox', $opts, ['default' => 0]);
        $this->render_field('filter_alignment', esc_html__('Alignement du filtre', 'mon-articles'), 'select', $opts, [
            'default' => 'right',
            'options' => [
                'left'   => __('Gauche', 'mon-articles'),
                'center' => __('Centre', 'mon-articles'),
                'right'  => __('Droite', 'mon-articles'),
            ],
        ]);
        $this->render_field(
            'filter_categories',
            esc_html__( 'Catégories à inclure dans le filtre', 'mon-articles' ),
            'category_checklist',
            $opts,
            [
                'taxonomy'  => $opts['taxonomy'] ?? '',
                'input_id'  => 'filter_categories',
                'label_for' => '',
            ]
        );
        
        echo '<hr><h3>' . esc_html__('Articles Épinglés', 'mon-articles') . '</h3>';
        $this->render_field('pinned_posts', esc_html__('Choisir les articles à épingler', 'mon-articles'), 'select2_ajax', $opts);
        $this->render_field('pinned_posts_ignore_filter', esc_html__('Toujours afficher les articles épinglés', 'mon-articles'), 'checkbox', $opts, [
            'default'     => 0,
            'description' => __('Si coché, les articles épinglés s\'afficheront toujours, même si une autre catégorie est sélectionnée dans le filtre.', 'mon-articles'),
        ]);
        $this->render_field('pinned_border_color', esc_html__('Couleur de bordure (épinglés)', 'mon-articles'), 'color', $opts, ['default' => '#eab308']);
        $this->render_field('pinned_show_badge', esc_html__('Afficher un badge sur les épinglés', 'mon-articles'), 'checkbox', $opts, ['default' => 0]);
        $this->render_field('pinned_badge_text', esc_html__('Texte du badge', 'mon-articles'), 'text', $opts, ['default' => 'Épinglé', 'wrapper_class' => 'badge-option']);
        $this->render_field('pinned_badge_bg_color', esc_html__('Couleur de fond du badge', 'mon-articles'), 'color', $opts, ['default' => '#eab308', 'wrapper_class' => 'badge-option']);
        $this->render_field('pinned_badge_text_color', esc_html__('Couleur du texte du badge', 'mon-articles'), 'color', $opts, ['default' => '#ffffff', 'wrapper_class' => 'badge-option']);

        echo '<hr><h3>' . esc_html__('Exclusions & Comportements Avancés', 'mon-articles') . '</h3>';
        $this->render_field('exclude_posts', esc_html__('ID des articles à exclure', 'mon-articles'), 'text', $opts, [
            'description' => __('Séparez les ID par des virgules (ex: 21, 56). Ces articles n\'apparaîtront jamais.', 'mon-articles'),
        ]);
        $this->render_field('ignore_native_sticky', esc_html__('Ignorer les articles "Épinglés" de WordPress', 'mon-articles'), 'checkbox', $opts, [
            'default'     => 1,
            'description' => __('Cochez pour ignorer les articles marqués comme "épinglés" dans l\'éditeur WordPress.', 'mon-articles'),
        ]);

        echo '<hr><h3>' . esc_html__('Mise en Page', 'mon-articles') . '</h3>';
        $this->render_field('display_mode', esc_html__('Mode d\'affichage', 'mon-articles'), 'select', $opts, [ 'default' => 'grid', 'options' => [ 'grid' => 'Grille', 'slideshow' => 'Diaporama', 'list' => 'Liste' ] ]);

        echo '<div class="my-articles-slideshow-settings" data-field="slideshow" style="display:none;">';
        $this->render_field('slideshow_loop', esc_html__('Boucle infinie', 'mon-articles'), 'checkbox', $opts, [
            'default'     => 1,
            'description' => __('Autorise le carrousel à revenir automatiquement au début.', 'mon-articles'),
        ]);
        $this->render_field('slideshow_autoplay', esc_html__('Lecture automatique', 'mon-articles'), 'checkbox', $opts, [
            'default'     => 0,
            'description' => __('Fait défiler les diapositives sans interaction de l’utilisateur.', 'mon-articles'),
        ]);
        $this->render_field('slideshow_delay', esc_html__('Délai entre les diapositives (ms)', 'mon-articles'), 'number', $opts, [
            'default'     => 5000,
            'min'         => 1000,
            'max'         => 20000,
            'description' => __('Utilisé lorsque la lecture automatique est activée.', 'mon-articles'),
        ]);
        $this->render_field('slideshow_pause_on_interaction', esc_html__('Mettre en pause lors des interactions', 'mon-articles'), 'checkbox', $opts, [
            'default'     => 1,
            'description' => __('Suspend l’autoplay lorsqu’un lien ou un contrôle du carrousel est utilisé.', 'mon-articles'),
        ]);
        $this->render_field('slideshow_pause_on_mouse_enter', esc_html__('Mettre en pause au survol', 'mon-articles'), 'checkbox', $opts, [
            'default'     => 1,
            'description' => __('Gèle le carrousel quand la souris survole la zone.', 'mon-articles'),
        ]);
        $this->render_field('slideshow_show_navigation', esc_html__('Afficher les flèches de navigation', 'mon-articles'), 'checkbox', $opts, [
            'default' => 1,
        ]);
        $this->render_field('slideshow_show_pagination', esc_html__('Afficher la pagination', 'mon-articles'), 'checkbox', $opts, [
            'default' => 1,
        ]);
        echo '</div>';

        echo '<div class="my-articles-columns-warning" data-field="columns_mobile">';
        $this->render_field('columns_mobile', esc_html__('Colonnes (Mobile < 768px)', 'mon-articles'), 'number', $opts, [
            'default'     => 1,
            'min'         => 1,
            'max'         => 3,
            'description' => __('Pour Grille et Diaporama', 'mon-articles'),
        ]);
        echo '<p class="my-articles-columns-warning__message" aria-live="polite"></p>';
        echo '</div>';

        echo '<div class="my-articles-columns-warning" data-field="columns_tablet">';
        $this->render_field('columns_tablet', esc_html__('Colonnes (Tablette ≥ 768px)', 'mon-articles'), 'number', $opts, [
            'default'     => 2,
            'min'         => 1,
            'max'         => 4,
            'description' => __('Pour Grille et Diaporama', 'mon-articles'),
        ]);
        echo '<p class="my-articles-columns-warning__message" aria-live="polite"></p>';
        echo '</div>';

        echo '<div class="my-articles-columns-warning" data-field="columns_desktop">';
        $this->render_field('columns_desktop', esc_html__('Colonnes (Desktop ≥ 1024px)', 'mon-articles'), 'number', $opts, [
            'default'     => 3,
            'min'         => 1,
            'max'         => 6,
            'description' => __('Pour Grille et Diaporama', 'mon-articles'),
        ]);
        echo '<p class="my-articles-columns-warning__message" aria-live="polite"></p>';
        echo '</div>';

        echo '<div class="my-articles-columns-warning" data-field="columns_ultrawide">';
        $this->render_field('columns_ultrawide', esc_html__('Colonnes (Ultra-Wide ≥ 1536px)', 'mon-articles'), 'number', $opts, [
            'default'     => 4,
            'min'         => 1,
            'max'         => 8,
            'description' => __('Pour Grille et Diaporama', 'mon-articles'),
        ]);
        echo '<p class="my-articles-columns-warning__message" aria-live="polite"></p>';
        echo '</div>';

        echo '<hr><h3>' . esc_html__( 'Accessibilité', 'mon-articles' ) . '</h3>';

        $default_aria_label = '';
        if ( isset( $post->ID ) ) {
            $default_aria_label = trim( wp_strip_all_tags( get_the_title( $post ) ) );
        }

        if ( '' === $default_aria_label ) {
            $default_aria_label = __( 'Module d\'articles', 'mon-articles' );
        }

        /* translators: %s: module title. */
        $aria_description = sprintf(
            esc_html__( 'Texte lu par les lecteurs d’écran pour identifier ce module. Laissez vide pour utiliser le titre du module (« %s »).', 'mon-articles' ),
            $default_aria_label
        );

        $this->render_field(
            'aria_label',
            esc_html__( 'Étiquette ARIA', 'mon-articles' ),
            'text',
            $opts,
            [
                'placeholder' => $default_aria_label,
                'description' => $aria_description,
            ]
        );

        /* translators: %s: module accessible label. */
        $default_filter_aria_label = sprintf( __( 'Filtre des catégories pour %s', 'mon-articles' ), $default_aria_label );

        $this->render_field(
            'category_filter_aria_label',
            esc_html__( 'Étiquette du filtre de catégories (ARIA)', 'mon-articles' ),
            'text',
            $opts,
            [
                'placeholder' => $default_filter_aria_label,
                'description' => esc_html__( 'Texte lu par les lecteurs d’écran pour annoncer la navigation du filtre. Laissez vide pour utiliser l’étiquette générée automatiquement.', 'mon-articles' ),
            ]
        );

        echo '<hr><h3>' . esc_html__('Apparence & Performances', 'mon-articles') . '</h3>';
        $design_presets = My_Articles_Shortcode::get_design_presets();
        $design_preset_options = array();

        if ( is_array( $design_presets ) ) {
            foreach ( $design_presets as $preset_id => $preset ) {
                $label = $preset['label'] ?? $preset_id;

                if ( is_array( $label ) ) {
                    $label = $preset_id;
                }

                $design_preset_options[ $preset_id ] = (string) $label;
            }
        }

        if ( empty( $design_preset_options ) || ! isset( $design_preset_options['custom'] ) ) {
            $design_preset_options = array_merge(
                array( 'custom' => __( 'Personnalisé', 'mon-articles' ) ),
                $design_preset_options
            );
        }

        $this->render_field(
            'design_preset',
            esc_html__( 'Modèle', 'mon-articles' ),
            'select',
            $opts,
            [
                'default'     => 'custom',
                'options'     => $design_preset_options,
                'description' => __( 'Applique un préréglage de couleurs, d’ombres et d’espacements.', 'mon-articles' ),
            ]
        );

        $this->render_field('module_padding_top', esc_html__('Marge intérieure haute (px)', 'mon-articles'), 'number', $opts, ['default' => 0, 'min' => 0, 'max' => 200]);
        $this->render_field('module_padding_bottom', esc_html__('Marge intérieure basse (px)', 'mon-articles'), 'number', $opts, ['default' => 0, 'min' => 0, 'max' => 200]);
        $this->render_field('module_padding_left', esc_html__('Marge intérieure gauche (px)', 'mon-articles'), 'number', $opts, ['default' => 0, 'min' => 0, 'max' => 200]);
        $this->render_field('module_padding_right', esc_html__('Marge intérieure droite (px)', 'mon-articles'), 'number', $opts, ['default' => 0, 'min' => 0, 'max' => 200]);
        $this->render_field('gap_size', esc_html__('Espacement des vignettes (Grille)', 'mon-articles'), 'number', $opts, ['default' => 25, 'min' => 0, 'max' => 50]);
        $this->render_field('list_item_gap', esc_html__('Espacement vertical (Liste)', 'mon-articles'), 'number', $opts, ['default' => 25, 'min' => 0, 'max' => 50]);
        $this->render_field('border_radius', esc_html__('Arrondi des bordures (px)', 'mon-articles'), 'number', $opts, ['default' => 12, 'min' => 0, 'max' => 50]);
        $this->render_field(
            'thumbnail_aspect_ratio',
            esc_html__( 'Ratio des vignettes', 'mon-articles' ),
            'select',
            $opts,
            [
                'default'     => My_Articles_Shortcode::get_default_thumbnail_aspect_ratio(),
                'options'     => My_Articles_Shortcode::get_thumbnail_aspect_ratio_choices(),
                'description' => esc_html__( 'Les ratios autorisés sont 1, 4/3, 3/2 et 16/9.', 'mon-articles' ),
            ]
        );
        $this->render_field('enable_lazy_load', esc_html__('Activer le chargement paresseux des images (Lazy Load)', 'mon-articles'), 'checkbox', $opts, [
            'default'     => 1,
            'description' => __('Améliore considérablement la vitesse de chargement de la page.', 'mon-articles'),
        ]);
        
        echo '<h4>' . esc_html__('Marge intérieur du contenu (Mode liste)', 'mon-articles') . '</h4>';
        $this->render_field('list_content_padding_top', esc_html__('Haut (px)', 'mon-articles'), 'number', $opts, ['default' => 0, 'min' => 0, 'max' => 100]);
        $this->render_field('list_content_padding_right', esc_html__('Droite (px)', 'mon-articles'), 'number', $opts, ['default' => 0, 'min' => 0, 'max' => 100]);
        $this->render_field('list_content_padding_bottom', esc_html__('Bas (px)', 'mon-articles'), 'number', $opts, ['default' => 0, 'min' => 0, 'max' => 100]);
        $this->render_field('list_content_padding_left', esc_html__('Gauche (px)', 'mon-articles'), 'number', $opts, ['default' => 0, 'min' => 0, 'max' => 100]);
        
        echo '<hr><h3>' . esc_html__('Couleurs & Ombres', 'mon-articles') . '</h3>';
        $this->render_field('module_bg_color', esc_html__('Fond du module', 'mon-articles'), 'color', $opts, ['default' => 'rgba(255,255,255,0)', 'alpha' => true]);
        $this->render_field('vignette_bg_color', esc_html__('Fond de la vignette', 'mon-articles'), 'color', $opts, ['default' => '#ffffff']);
        $this->render_field('title_wrapper_bg_color', esc_html__('Fond du bloc titre', 'mon-articles'), 'color', $opts, ['default' => '#ffffff']);
        $this->render_field('title_color', esc_html__('Couleur du titre', 'mon-articles'), 'color', $opts, ['default' => '#333333']);
        $this->render_field('meta_color', esc_html__('Couleur du texte (méta)', 'mon-articles'), 'color', $opts, ['default' => '#6b7280']);
        $this->render_field('meta_color_hover', esc_html__('Couleur (méta, survol)', 'mon-articles'), 'color', $opts, ['default' => '#000000']);
        $this->render_field('pagination_color', esc_html__('Couleur de la pagination (Diaporama)', 'mon-articles'), 'color', $opts, ['default' => '#333333']);
        $this->render_field('shadow_color', esc_html__('Couleur de l\'ombre', 'mon-articles'), 'color', $opts, ['default' => 'rgba(0,0,0,0.07)', 'alpha' => true]);
        $this->render_field('shadow_color_hover', esc_html__('Couleur de l\'ombre (survol)', 'mon-articles'), 'color', $opts, ['default' => 'rgba(0,0,0,0.12)', 'alpha' => true]);

        echo '<hr><h3>' . esc_html__('Polices & Méta-données', 'mon-articles') . '</h3>';
        $this->render_field('title_font_size', esc_html__('Taille de police du titre (px)', 'mon-articles'), 'number', $opts, ['default' => 16, 'min' => 10, 'max' => 40]);
        $this->render_field('meta_font_size', esc_html__('Taille de police (méta)', 'mon-articles'), 'number', $opts, ['default' => 12, 'min' => 8, 'max' => 20]);
        $this->render_field('show_category', esc_html__('Afficher la catégorie', 'mon-articles'), 'checkbox', $opts, ['default' => 1]);
        $this->render_field('show_author', esc_html__('Afficher l\'auteur', 'mon-articles'), 'checkbox', $opts, ['default' => 1]);
        $this->render_field('show_date', esc_html__('Afficher la date', 'mon-articles'), 'checkbox', $opts, ['default' => 1]);

        echo '<hr><h3>' . esc_html__('Extrait', 'mon-articles') . '</h3>';
        $this->render_field('show_excerpt', esc_html__('Afficher l\'extrait', 'mon-articles'), 'checkbox', $opts, ['default' => 0]);
        $this->render_field('excerpt_length', esc_html__('Longueur de l\'extrait (mots)', 'mon-articles'), 'number', $opts, ['default' => 25, 'wrapper_class' => 'excerpt-option']);
        $this->render_field('excerpt_more_text', esc_html__('Texte "Lire la suite"', 'mon-articles'), 'text', $opts, ['default' => 'Lire la suite', 'wrapper_class' => 'excerpt-option']);
        $this->render_field('excerpt_font_size', esc_html__('Taille de police (px)', 'mon-articles'), 'number', $opts, ['default' => 14, 'wrapper_class' => 'excerpt-option']);
        $this->render_field('excerpt_color', esc_html__('Couleur du texte', 'mon-articles'), 'color', $opts, ['default' => '#4b5563', 'wrapper_class' => 'excerpt-option']);
        
        echo '<hr><h3 style="color: red;">' . esc_html__('Débogage', 'mon-articles') . '</h3>';
        $this->render_field('enable_debug_mode', esc_html__('Activer le mode de débogage', 'mon-articles'), 'checkbox', $opts, [
            'default'     => 0,
            'description' => __('Affiche des informations techniques sous le module sur le site public. À n\'utiliser que pour résoudre un problème.', 'mon-articles'),
        ]);
    }

    private function render_field($id, $label, $type, $opts, $args = []) {
        $name          = esc_attr( $this->option_key . '[' . $id . ']' );
        $value         = $opts[ $id ] ?? ( $args['default'] ?? '' );
        $wrapper_class = isset( $args['wrapper_class'] ) ? ' class="' . esc_attr( $args['wrapper_class'] ) . '"' : '';
        $input_id      = isset( $args['input_id'] ) ? (string) $args['input_id'] : (string) $id;
        $label_for     = array_key_exists( 'label_for', $args ) ? (string) $args['label_for'] : $input_id;
        $label_attr    = '' !== $label_for ? ' for="' . esc_attr( $label_for ) . '"' : '';

        echo '<table' . $wrapper_class . '><tr style="vertical-align: top;"><th style="width:250px; text-align: left; padding-left: 0;"><label' . $label_attr . '>' . esc_html( $label ) . '</label></th><td>';

        switch ($type) {
            case 'text':
                $placeholder_attr = '';
                if ( isset( $args['placeholder'] ) && '' !== $args['placeholder'] ) {
                    $placeholder_attr = ' placeholder="' . esc_attr( $args['placeholder'] ) . '"';
                }
                printf( '<input type="text" id="%s" name="%s" value="%s" class="regular-text"%s />', esc_attr( $input_id ), $name, esc_attr( $value ), $placeholder_attr );
                if (isset($args['description'])) {
                    echo '<p class="description">' . esc_html($args['description']) . '</p>';
                }
                break;
            case 'select2_ajax':
                $saved_ids = is_array($value) ? $value : array();
                echo '<select id="' . esc_attr( $input_id ) . '" name="' . $name . '[]" class="my-articles-post-selector" multiple="multiple" style="width: 100%;">';
                if (!empty($saved_ids)) {
                    foreach ($saved_ids as $post_id) {
                        echo '<option value="' . esc_attr($post_id) . '" selected="selected">' . esc_html(get_the_title($post_id)) . '</option>';
                    }
                }
                echo '</select>';
                break;
            case 'number':
                printf( '<input type="number" id="%s" name="%s" value="%s" min="%d" max="%d" />', esc_attr( $input_id ), $name, esc_attr( $value ), $args['min'] ?? 0, $args['max'] ?? 100 );
                 if (isset($args['description'])) {
                    echo '<p class="description" style="margin-left: 0; font-style: italic;">' . esc_html($args['description']) . '</p>';
                }
                break;
            case 'color':
                $alpha = ! empty( $args['alpha'] ) ? 'data-alpha-enabled="true"' : '';
                printf( '<input type="text" id="%s" name="%s" value="%s" class="my-color-picker" %s />', esc_attr( $input_id ), $name, esc_attr( $value ), $alpha );
                break;
            case 'select':
                echo '<select id="' . esc_attr( $input_id ) . '" name="' . $name . '">';
                foreach ($args['options'] as $val => $text) {
                    echo '<option value="' . esc_attr($val) . '" ' . selected($value, $val, false) . '>' . esc_html($text) . '</option>';
                }
                echo '</select>';
                 if (isset($args['description'])) {
                    echo '<p class="description" style="font-style: italic;">' . esc_html($args['description']) . '</p>';
                }
                break;
            case 'checkbox':
                printf( '<input type="checkbox" id="%s" name="%s" value="1" %s />', esc_attr( $input_id ), $name, checked( $value, 1, false ) );
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
                            $checkbox_id = $input_id ? $input_id . '-' . $term->term_id : $id . '-' . $term->term_id;
                            echo '<div style="margin-bottom: 4px;"><input type="checkbox" id="' . esc_attr( $checkbox_id ) . '" name="' . $name . '[]" value="' . esc_attr($term->term_id) . '" ' . $checked . '> <label for="' . esc_attr( $checkbox_id ) . '" style="display: inline;">' . esc_html($term->name) . '</label></div>';
                        }
                        echo '</div>';
                    } else {
                        echo '<p class="description">' . esc_html__('Aucun terme disponible pour cette taxonomie.', 'mon-articles') . '</p>';
                    }
                } else {
                    echo '<p class="description">' . esc_html__('Aucune taxonomie valide disponible.', 'mon-articles') . '</p>';
                }

                echo '<p class="description">' . esc_html__('Si aucune catégorie n\'est cochée, toutes seront affichées.', 'mon-articles') . '</p>';
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

                echo '<select id="' . esc_attr( $input_id ) . '" name="' . $name . '">';
                foreach ( $post_types as $post_type ) {
                    echo '<option value="' . esc_attr( $post_type->name ) . '" ' . selected( $value, $post_type->name, false ) . '>' . esc_html( $post_type->labels->singular_name ) . '</option>';
                }
                echo '</select>';
                break;
            case 'taxonomy_select':
                echo '<div id="taxonomy_selector_wrapper" style="display:none;"><select id="' . esc_attr( $input_id ) . '" name="' . $name . '" data-current="' . esc_attr($value) . '"></select></div>';
                break;
            case 'term_select':
                echo '<div id="term_selector_wrapper" style="display:none;"><select id="' . esc_attr( $input_id ) . '" name="' . $name . '" data-current="' . esc_attr($value) . '"></select></div>';
                break;
            case 'taxonomy_filter_select':
                $normalized_filters = array();

                if ( is_array( $value ) ) {
                    foreach ( $value as $filter ) {
                        if ( is_array( $filter ) ) {
                            $tax = isset( $filter['taxonomy'] ) ? sanitize_key( $filter['taxonomy'] ) : '';
                            $slug = isset( $filter['slug'] ) ? sanitize_title( $filter['slug'] ) : '';

                            if ( $tax && $slug ) {
                                $normalized_filters[] = $tax . ':' . $slug;
                            }
                        } elseif ( is_string( $filter ) && strpos( $filter, ':' ) !== false ) {
                            $normalized_filters[] = sanitize_text_field( $filter );
                        }
                    }
                } elseif ( is_string( $value ) ) {
                    $decoded_filters = json_decode( $value, true );

                    if ( is_array( $decoded_filters ) ) {
                        foreach ( $decoded_filters as $filter ) {
                            if ( is_array( $filter ) ) {
                                $tax = isset( $filter['taxonomy'] ) ? sanitize_key( $filter['taxonomy'] ) : '';
                                $slug = isset( $filter['slug'] ) ? sanitize_title( $filter['slug'] ) : '';

                                if ( $tax && $slug ) {
                                    $normalized_filters[] = $tax . ':' . $slug;
                                }
                            } elseif ( is_string( $filter ) && strpos( $filter, ':' ) !== false ) {
                                $normalized_filters[] = sanitize_text_field( $filter );
                            }
                        }
                    } elseif ( strpos( $value, ':' ) !== false ) {
                        $normalized_filters[] = sanitize_text_field( $value );
                    }
                }

                $normalized_filters = array_values( array_unique( $normalized_filters ) );

                $selected_post_type = isset( $args['post_type'] ) ? my_articles_normalize_post_type( $args['post_type'] ) : '';
                $taxonomy_objects   = array();

                if ( $selected_post_type ) {
                    $taxonomy_objects = get_object_taxonomies( $selected_post_type, 'objects' );
                }

                if ( empty( $taxonomy_objects ) ) {
                    $taxonomy_objects = array();
                }

                if ( empty( $taxonomy_objects ) ) {
                    echo '<p class="description">' . esc_html__( 'Aucune taxonomie disponible pour ce type de contenu.', 'mon-articles' ) . '</p>';
                    break;
                }

                echo '<select id="' . esc_attr( $input_id ) . '" name="' . $name . '[]" multiple="multiple" size="8" style="width: 100%;">';

                foreach ( $taxonomy_objects as $taxonomy ) {
                    if ( ! $taxonomy instanceof WP_Taxonomy ) {
                        continue;
                    }

                    $terms = get_terms(
                        array(
                            'taxonomy'   => $taxonomy->name,
                            'hide_empty' => false,
                        )
                    );

                    if ( is_wp_error( $terms ) || empty( $terms ) ) {
                        continue;
                    }

                    $label = $taxonomy->labels && isset( $taxonomy->labels->name ) ? $taxonomy->labels->name : $taxonomy->name;

                    echo '<optgroup label="' . esc_attr( $label ) . '">';

                    foreach ( $terms as $term ) {
                        $option_value = $taxonomy->name . ':' . $term->slug;
                        $selected     = in_array( $option_value, $normalized_filters, true ) ? ' selected="selected"' : '';
                        echo '<option value="' . esc_attr( $option_value ) . '"' . $selected . '>' . esc_html( $term->name ) . '</option>';
                    }

                    echo '</optgroup>';
                }

                echo '</select>';

                if ( isset( $args['description'] ) ) {
                    echo '<p class="description" style="font-style: italic;">' . esc_html( $args['description'] ) . '</p>';
                }
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
        $sanitized['posts_per_page'] = isset( $input['posts_per_page'] )
            ? min( 50, max( 0, absint( $input['posts_per_page'] ) ) )
            : 10;
        $sanitized['pagination_mode'] = isset($input['pagination_mode']) && in_array($input['pagination_mode'], ['none', 'load_more', 'numbered']) ? $input['pagination_mode'] : 'none';
        $sanitized['load_more_auto'] = isset( $input['load_more_auto'] ) ? 1 : 0;
        $allowed_sort = array( 'date', 'title', 'menu_order', 'meta_value', 'comment_count', 'post__in' );
        $resolved_sort = 'date';

        if ( isset( $input['sort'] ) && in_array( $input['sort'], $allowed_sort, true ) ) {
            $resolved_sort = $input['sort'];
        } elseif ( isset( $input['orderby'] ) && in_array( $input['orderby'], $allowed_sort, true ) ) {
            $resolved_sort = $input['orderby'];
        }

        $sanitized['sort']    = $resolved_sort;
        $sanitized['orderby'] = $resolved_sort;
        $order = isset( $input['order'] ) ? strtoupper( (string) $input['order'] ) : 'DESC';
        $sanitized['order'] = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';
        $meta_key = '';
        if ( isset( $input['meta_key'] ) ) {
            $meta_key = sanitize_text_field( (string) $input['meta_key'] );
            $meta_key = trim( $meta_key );
        }
        $sanitized['meta_key'] = $meta_key;
        $sanitized['enable_keyword_search'] = isset( $input['enable_keyword_search'] ) ? 1 : 0;
        $sanitized['show_category_filter'] = isset( $input['show_category_filter'] ) ? 1 : 0;
        $sanitized['filter_alignment'] = isset($input['filter_alignment']) && in_array($input['filter_alignment'], ['left', 'center', 'right']) ? $input['filter_alignment'] : 'right';
        
        $sanitized['filter_categories'] = array();
        if ( isset($input['filter_categories']) && is_array($input['filter_categories']) ) {
            $sanitized['filter_categories'] = array_map('absint', $input['filter_categories']);
        }

        $sanitized['tax_filters'] = My_Articles_Shortcode::sanitize_filter_pairs(
            $input['tax_filters'] ?? array(),
            $sanitized['post_type']
        );

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

        $aria_label = '';
        if ( isset( $input['aria_label'] ) && is_string( $input['aria_label'] ) ) {
            $aria_label = sanitize_text_field( $input['aria_label'] );
            $aria_label = trim( $aria_label );
        }
        $sanitized['aria_label'] = $aria_label;

        $category_filter_aria_label = '';
        if ( isset( $input['category_filter_aria_label'] ) && is_string( $input['category_filter_aria_label'] ) ) {
            $category_filter_aria_label = sanitize_text_field( $input['category_filter_aria_label'] );
            $category_filter_aria_label = trim( $category_filter_aria_label );
        }
        $sanitized['category_filter_aria_label'] = $category_filter_aria_label;

        $design_preset = 'custom';
        if ( isset( $input['design_preset'] ) && is_string( $input['design_preset'] ) ) {
            $candidate = sanitize_key( $input['design_preset'] );
            $available_presets = My_Articles_Shortcode::get_design_presets();
            if ( is_array( $available_presets ) && isset( $available_presets[ $candidate ] ) ) {
                $design_preset = $candidate;
            }
        }
        $sanitized['design_preset'] = $design_preset;

        $sanitized['display_mode'] = in_array($input['display_mode'] ?? 'grid', ['grid', 'slideshow', 'list']) ? $input['display_mode'] : 'grid';
        $allowed_thumbnail_ratios   = My_Articles_Shortcode::get_allowed_thumbnail_aspect_ratios();
        $default_thumbnail_ratio    = My_Articles_Shortcode::get_default_thumbnail_aspect_ratio();
        $requested_thumbnail_ratio  = isset( $input['thumbnail_aspect_ratio'] ) ? (string) $input['thumbnail_aspect_ratio'] : $default_thumbnail_ratio;

        if ( ! in_array( $requested_thumbnail_ratio, $allowed_thumbnail_ratios, true ) ) {
            $requested_thumbnail_ratio = $default_thumbnail_ratio;
        }

        $sanitized['thumbnail_aspect_ratio'] = $requested_thumbnail_ratio;
        $sanitized['columns_mobile'] = isset( $input['columns_mobile'] )
            ? min( 3, max( 1, absint( $input['columns_mobile'] ) ) )
            : 1;
        $sanitized['columns_tablet'] = isset( $input['columns_tablet'] )
            ? min( 4, max( 1, absint( $input['columns_tablet'] ) ) )
            : 2;
        $sanitized['columns_desktop'] = isset( $input['columns_desktop'] )
            ? min( 6, max( 1, absint( $input['columns_desktop'] ) ) )
            : 3;
        $sanitized['columns_ultrawide'] = isset( $input['columns_ultrawide'] )
            ? min( 8, max( 1, absint( $input['columns_ultrawide'] ) ) )
            : 4;
        $sanitized['module_padding_top'] = isset( $input['module_padding_top'] )
            ? min( 200, max( 0, absint( $input['module_padding_top'] ) ) )
            : 0;
        $sanitized['module_padding_bottom'] = isset( $input['module_padding_bottom'] )
            ? min( 200, max( 0, absint( $input['module_padding_bottom'] ) ) )
            : 0;
        $sanitized['module_padding_left'] = isset( $input['module_padding_left'] )
            ? min( 200, max( 0, absint( $input['module_padding_left'] ) ) )
            : 0;
        $sanitized['module_padding_right'] = isset( $input['module_padding_right'] )
            ? min( 200, max( 0, absint( $input['module_padding_right'] ) ) )
            : 0;
        $sanitized['gap_size'] = isset( $input['gap_size'] )
            ? min( 50, max( 0, absint( $input['gap_size'] ) ) )
            : 25;
        $sanitized['list_item_gap'] = isset( $input['list_item_gap'] )
            ? min( 50, max( 0, absint( $input['list_item_gap'] ) ) )
            : 25;
        $sanitized['list_content_padding_top'] = isset( $input['list_content_padding_top'] )
            ? min( 100, max( 0, absint( $input['list_content_padding_top'] ) ) )
            : 0;
        $sanitized['list_content_padding_right'] = isset( $input['list_content_padding_right'] )
            ? min( 100, max( 0, absint( $input['list_content_padding_right'] ) ) )
            : 0;
        $sanitized['list_content_padding_bottom'] = isset( $input['list_content_padding_bottom'] )
            ? min( 100, max( 0, absint( $input['list_content_padding_bottom'] ) ) )
            : 0;
        $sanitized['list_content_padding_left'] = isset( $input['list_content_padding_left'] )
            ? min( 100, max( 0, absint( $input['list_content_padding_left'] ) ) )
            : 0;
        $sanitized['border_radius'] = isset( $input['border_radius'] )
            ? min( 50, max( 0, absint( $input['border_radius'] ) ) )
            : 12;
        $sanitized['title_font_size'] = isset( $input['title_font_size'] )
            ? min( 40, max( 10, absint( $input['title_font_size'] ) ) )
            : 16;
        $sanitized['meta_font_size'] = isset( $input['meta_font_size'] )
            ? min( 20, max( 8, absint( $input['meta_font_size'] ) ) )
            : 12;
        $sanitized['show_category'] = isset( $input['show_category'] ) ? 1 : 0;
        $sanitized['show_author'] = isset( $input['show_author'] ) ? 1 : 0;
        $sanitized['show_date'] = isset( $input['show_date'] ) ? 1 : 0;

        $sanitized['slideshow_loop'] = isset( $input['slideshow_loop'] ) ? 1 : 0;
        $sanitized['slideshow_autoplay'] = isset( $input['slideshow_autoplay'] ) ? 1 : 0;
        $delay = isset( $input['slideshow_delay'] ) ? absint( $input['slideshow_delay'] ) : 5000;
        if ( $delay > 0 && $delay < 1000 ) {
            $delay = 1000;
        }
        if ( $delay > 20000 ) {
            $delay = 20000;
        }
        if ( 0 === $delay ) {
            $delay = 5000;
        }
        $sanitized['slideshow_delay'] = $delay;
        $sanitized['slideshow_pause_on_interaction'] = isset( $input['slideshow_pause_on_interaction'] ) ? 1 : 0;
        $sanitized['slideshow_pause_on_mouse_enter'] = isset( $input['slideshow_pause_on_mouse_enter'] ) ? 1 : 0;
        $sanitized['slideshow_show_navigation'] = isset( $input['slideshow_show_navigation'] ) ? 1 : 0;
        $sanitized['slideshow_show_pagination'] = isset( $input['slideshow_show_pagination'] ) ? 1 : 0;

        $sanitized['show_excerpt'] = isset( $input['show_excerpt'] ) ? 1 : 0;
        $sanitized['excerpt_length'] = isset( $input['excerpt_length'] ) ? absint($input['excerpt_length']) : 25;
        $sanitized['excerpt_more_text'] = isset( $input['excerpt_more_text'] ) ? sanitize_text_field( wp_unslash( $input['excerpt_more_text'] ) ) : 'Lire la suite';
        $sanitized['excerpt_font_size'] = isset( $input['excerpt_font_size'] )
            ? min( 100, max( 0, absint( $input['excerpt_font_size'] ) ) )
            : 14;
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
