<?php
// Fichier: includes/class-my-articles-settings.php

if ( ! defined( 'WPINC' ) ) {
    die;
}

class My_Articles_Settings {

    private static $instance;
    private $option_group = 'my_articles_options_group';
    private $option_name = 'my_articles_options';
    private $plugin_page_hook;

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        add_action( 'admin_post_my_articles_reset_settings', array( $this, 'reset_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    public function add_plugin_page() {
        $this->plugin_page_hook = add_menu_page( 'Réglages Tuiles - LCV', 'Tuiles - LCV', 'manage_options', 'my-articles-settings', array( $this, 'create_admin_page' ), 'dashicons-admin-post', 27 );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( $hook !== $this->plugin_page_hook ) { return; }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'my-articles-admin-script', MY_ARTICLES_PLUGIN_URL . 'assets/js/admin.js', array( 'wp-color-picker' ), MY_ARTICLES_VERSION, true );
    }

    public function create_admin_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
        $allowed_tabs = array( 'settings', 'tutorial' );

        if ( ! in_array( $active_tab, $allowed_tabs, true ) ) {
            $active_tab = 'settings';
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Réglages Tuiles - LCV', 'mon-articles' ); ?></h1>
            <div style="margin-bottom: 20px;">
                <p style="margin: 0;"><strong><?php esc_html_e( 'Auteur :', 'mon-articles' ); ?></strong> LCV</p>
                <p style="margin: 0;"><strong><?php esc_html_e( 'Version :', 'mon-articles' ); ?></strong> <?php echo esc_html( MY_ARTICLES_VERSION ); ?></p>
            </div>

            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'my-articles-settings', 'tab' => 'settings' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Réglages', 'mon-articles' ); ?>
                </a>
                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'my-articles-settings', 'tab' => 'tutorial' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo 'tutorial' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Tutoriel', 'mon-articles' ); ?>
                </a>
            </h2>

            <?php if ( 'tutorial' === $active_tab ) : ?>
                <div class="my-articles-tutorial">
                    <h2><?php esc_html_e( 'Instrumentation : comprendre ce que vous activez', 'mon-articles' ); ?></h2>
                    <p><?php esc_html_e( 'La section « Instrumentation » vous permet de suivre ce que font les utilisateurs dans vos tuiles (filtrage, chargement progressif, etc.). En activant l’option, le plugin publie automatiquement des événements JavaScript qui décrivent chaque étape de ces interactions (requête, succès, erreur).', 'mon-articles' ); ?></p>

                    <h3><?php esc_html_e( 'Choisir le canal de sortie', 'mon-articles' ); ?></h3>
                    <ul>
                        <li><strong><?php esc_html_e( 'Console du navigateur', 'mon-articles' ); ?></strong> — <?php esc_html_e( 'les événements sont simplement journalisés pour le débogage.', 'mon-articles' ); ?></li>
                        <li><strong><?php esc_html_e( 'dataLayer', 'mon-articles' ); ?></strong> — <?php esc_html_e( 'les événements sont poussés dans window.dataLayer, idéal avec Google Tag Manager ou un outil d’analytics similaire.', 'mon-articles' ); ?></li>
                        <li><strong><?php esc_html_e( 'fetch', 'mon-articles' ); ?></strong> — <?php esc_html_e( 'chaque événement est envoyé en POST JSON vers l’endpoint REST wp-json/my-articles/v1/track pour un traitement serveur ou une redirection vers un service tiers.', 'mon-articles' ); ?></li>
                    </ul>

                    <h3><?php esc_html_e( 'Utilisation côté front-end', 'mon-articles' ); ?></h3>
                    <p><?php esc_html_e( 'Une fois l’instrumentation activée, la configuration est disponible dans window.myArticlesFilter.instrumentation et window.myArticlesLoadMore.instrumentation. Deux événements personnalisés sont émis :', 'mon-articles' ); ?></p>
                    <ul>
                        <li><code>my-articles:filter</code></li>
                        <li><code>my-articles:load-more</code></li>
                    </ul>
                    <p><?php esc_html_e( 'Chaque événement possède un objet detail indiquant la phase (request, success, error), l’instance concernée, les paramètres de filtrage et des informations additionnelles (pages totales, nombre d’éléments ajoutés, etc.). Vous pouvez y réagir pour afficher des messages, alimenter votre outil d’analyse ou déclencher d’autres actions.', 'mon-articles' ); ?></p>

                    <h3><?php esc_html_e( 'Traitement côté serveur (optionnel)', 'mon-articles' ); ?></h3>
                    <p><?php esc_html_e( 'Avec le canal « fetch », les événements sont également disponibles via l’action WordPress my_articles_track_interaction. C’est l’occasion de connecter facilement votre solution de monitoring ou d’analytics existante.', 'mon-articles' ); ?></p>

                    <p><?php esc_html_e( 'En résumé, cette section vous offre un moyen simple de suivre, analyser et rediriger les interactions des utilisateurs avec vos tuiles, sans code supplémentaire.', 'mon-articles' ); ?></p>
                </div>
            <?php else : ?>
                <p><?php esc_html_e( 'Utilisez le shortcode [mon_affichage_articles id="123"] pour afficher les articles. Vous pouvez récupérer l\'identifiant dans la metabox « Shortcode à utiliser ».', 'mon-articles' ); ?></p>

                <form method="post" action="options.php">
                    <?php settings_fields( $this->option_group ); do_settings_sections( 'my-articles-admin' ); submit_button(); ?>
                </form>
                <hr>
                <h2><?php esc_html_e( 'Maintenance', 'mon-articles' ); ?></h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block;">
                    <input type="hidden" name="action" value="my_articles_reset_settings">
                    <?php wp_nonce_field( 'my_articles_reset_settings_nonce' ); ?>
                    <?php submit_button( __( 'Réinitialiser les réglages', 'mon-articles' ), 'delete', 'submit', false, ['onclick' => 'return confirm("' . esc_js( __( 'Êtes-vous sûr de vouloir réinitialiser tous les réglages ?', 'mon-articles' ) ) . '");'] ); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public function page_init() {
        register_setting( $this->option_group, $this->option_name, array( $this, 'sanitize' ) );

        add_settings_section( 'setting_section_general', __( 'Réglages Généraux', 'mon-articles' ), null, 'my-articles-admin' );
        add_settings_field( 'default_category', __( 'Catégorie par défaut', 'mon-articles' ), array( $this, 'default_category_callback' ), 'my-articles-admin', 'setting_section_general' );
        add_settings_field( 'posts_per_page', __( 'Nombre d\'articles à afficher', 'mon-articles' ), array( $this, 'posts_per_page_callback' ), 'my-articles-admin', 'setting_section_general' );
        add_settings_field( 'enable_keyword_search', __( 'Activer la recherche par mots-clés', 'mon-articles' ), array( $this, 'enable_keyword_search_callback' ), 'my-articles-admin', 'setting_section_general' );

        add_settings_section( 'setting_section_layout', __( 'Mise en page', 'mon-articles' ), null, 'my-articles-admin' );
        add_settings_field( 'display_mode', __( 'Mode d\'affichage', 'mon-articles' ), array( $this, 'display_mode_callback' ), 'my-articles-admin', 'setting_section_layout' );
        add_settings_field( 'desktop_columns', __( 'Articles visibles (Desktop)', 'mon-articles' ), array( $this, 'desktop_columns_callback' ), 'my-articles-admin', 'setting_section_layout' );
        add_settings_field( 'mobile_columns', __( 'Articles visibles (Mobile)', 'mon-articles' ), array( $this, 'mobile_columns_callback' ), 'my-articles-admin', 'setting_section_layout' );
        add_settings_field( 'module_margin_top', __( 'Marge en haut (px)', 'mon-articles' ), array( $this, 'module_margin_top_callback' ), 'my-articles-admin', 'setting_section_layout' );
        add_settings_field( 'module_margin_bottom', __( 'Marge en bas (px)', 'mon-articles' ), array( $this, 'module_margin_bottom_callback' ), 'my-articles-admin', 'setting_section_layout' );
        add_settings_field( 'module_margin_left', __( 'Marge à gauche (px)', 'mon-articles' ), array( $this, 'module_margin_left_callback' ), 'my-articles-admin', 'setting_section_layout' );
        add_settings_field( 'module_margin_right', __( 'Marge à droite (px)', 'mon-articles' ), array( $this, 'module_margin_right_callback' ), 'my-articles-admin', 'setting_section_layout' );
        
        add_settings_section( 'setting_section_appearance', __( 'Apparence', 'mon-articles' ), null, 'my-articles-admin' );
        add_settings_field( 'module_bg_color', __( 'Couleur de fond du module', 'mon-articles' ), array( $this, 'module_bg_color_callback' ), 'my-articles-admin', 'setting_section_appearance' );
        add_settings_field( 'vignette_bg_color', __( 'Couleur de fond de la vignette', 'mon-articles' ), array( $this, 'vignette_bg_color_callback' ), 'my-articles-admin', 'setting_section_appearance' );
        add_settings_field( 'title_wrapper_bg_color', __( 'Couleur de fond du bloc titre', 'mon-articles' ), array( $this, 'title_wrapper_bg_color_callback' ), 'my-articles-admin', 'setting_section_appearance' );
        add_settings_field( 'gap_size', __( 'Espacement des vignettes (px)', 'mon-articles' ), array( $this, 'gap_size_callback' ), 'my-articles-admin', 'setting_section_appearance' );
        add_settings_field( 'border_radius', __( 'Arrondi des bordures (px)', 'mon-articles' ), array( $this, 'border_radius_callback' ), 'my-articles-admin', 'setting_section_appearance' );
        add_settings_field( 'title_color', __( 'Couleur du titre', 'mon-articles' ), array( $this, 'title_color_callback' ), 'my-articles-admin', 'setting_section_appearance' );
        add_settings_field( 'title_font_size', __( 'Taille de police du titre (px)', 'mon-articles' ), array( $this, 'title_font_size_callback' ), 'my-articles-admin', 'setting_section_appearance' );
        add_settings_field( 'show_category', __( 'Afficher la catégorie', 'mon-articles' ), array( $this, 'show_category_callback' ), 'my-articles-admin', 'setting_section_appearance' );
        add_settings_field( 'show_author', __( 'Afficher l\'auteur', 'mon-articles' ), array( $this, 'show_author_callback' ), 'my-articles-admin', 'setting_section_appearance' );
        add_settings_field( 'show_date', __( 'Afficher la date de publication', 'mon-articles' ), array( $this, 'show_date_callback' ), 'my-articles-admin', 'setting_section_appearance' );
        add_settings_field( 'meta_font_size', __( 'Taille de police (méta)', 'mon-articles' ), array( $this, 'meta_font_size_callback' ), 'my-articles-admin', 'setting_section_appearance' );
        add_settings_field( 'meta_color', __( 'Couleur du texte (méta)', 'mon-articles' ), array( $this, 'meta_color_callback' ), 'my-articles-admin', 'setting_section_appearance' );
        add_settings_field( 'meta_color_hover', __( 'Couleur du texte (méta, survol)', 'mon-articles' ), array( $this, 'meta_color_hover_callback' ), 'my-articles-admin', 'setting_section_appearance' );
        add_settings_field( 'pagination_color', __( 'Couleur de la pagination (Diaporama)', 'mon-articles' ), array( $this, 'pagination_color_callback' ), 'my-articles-admin', 'setting_section_appearance' );

        add_settings_section( 'setting_section_instrumentation', __( 'Instrumentation', 'mon-articles' ), null, 'my-articles-admin' );
        add_settings_field( 'instrumentation_enabled', __( 'Activer l\'instrumentation', 'mon-articles' ), array( $this, 'instrumentation_enabled_callback' ), 'my-articles-admin', 'setting_section_instrumentation' );
        add_settings_field( 'instrumentation_channel', __( 'Canal de sortie', 'mon-articles' ), array( $this, 'instrumentation_channel_callback' ), 'my-articles-admin', 'setting_section_instrumentation' );
    }

    public function sanitize( $input ) {
        $sanitized_input = [];
        $sanitized_input['display_mode'] = isset( $input['display_mode'] ) && in_array($input['display_mode'], ['grid', 'slideshow', 'list']) ? $input['display_mode'] : 'grid';
        $sanitized_input['default_category'] = isset( $input['default_category'] ) ? sanitize_text_field( $input['default_category'] ) : '';
        $sanitized_input['posts_per_page'] = isset( $input['posts_per_page'] )
            ? min( 50, max( 0, intval( $input['posts_per_page'] ) ) )
            : 10;
        $sanitized_input['enable_keyword_search'] = isset( $input['enable_keyword_search'] ) ? 1 : 0;
        $sanitized_input['desktop_columns'] = isset( $input['desktop_columns'] )
            ? min( 6, max( 1, intval( $input['desktop_columns'] ) ) )
            : 3;
        $sanitized_input['mobile_columns'] = isset( $input['mobile_columns'] )
            ? min( 3, max( 1, intval( $input['mobile_columns'] ) ) )
            : 1;
        $allowed_thumbnail_ratios = My_Articles_Shortcode::get_allowed_thumbnail_aspect_ratios();
        $default_thumbnail_ratio  = My_Articles_Shortcode::get_default_thumbnail_aspect_ratio();
        $requested_thumbnail_ratio = isset( $input['thumbnail_aspect_ratio'] ) ? (string) $input['thumbnail_aspect_ratio'] : $default_thumbnail_ratio;

        if ( ! in_array( $requested_thumbnail_ratio, $allowed_thumbnail_ratios, true ) ) {
            $requested_thumbnail_ratio = $default_thumbnail_ratio;
        }

        $sanitized_input['thumbnail_aspect_ratio'] = $requested_thumbnail_ratio;
        $sanitized_input['gap_size'] = isset( $input['gap_size'] )
            ? min( 50, max( 0, intval( $input['gap_size'] ) ) )
            : 25;
        $sanitized_input['border_radius'] = isset( $input['border_radius'] )
            ? min( 50, max( 0, intval( $input['border_radius'] ) ) )
            : 12;
        $sanitized_input['title_color'] = my_articles_sanitize_color($input['title_color'] ?? '', '#333333');
        $sanitized_input['title_font_size'] = isset( $input['title_font_size'] )
            ? min( 40, max( 10, intval( $input['title_font_size'] ) ) )
            : 16;
        $sanitized_input['show_category'] = isset( $input['show_category'] ) ? 1 : 0;
        $sanitized_input['show_author'] = isset( $input['show_author'] ) ? 1 : 0;
        $sanitized_input['show_date'] = isset( $input['show_date'] ) ? 1 : 0;
        $sanitized_input['meta_font_size'] = isset( $input['meta_font_size'] )
            ? min( 20, max( 8, intval( $input['meta_font_size'] ) ) )
            : 12;
        $sanitized_input['meta_color'] = my_articles_sanitize_color($input['meta_color'] ?? '', '#6b7280');
        $sanitized_input['meta_color_hover'] = my_articles_sanitize_color($input['meta_color_hover'] ?? '', '#000000');
        $sanitized_input['module_bg_color'] = my_articles_sanitize_color($input['module_bg_color'] ?? '', 'rgba(255,255,255,0)');
        $sanitized_input['vignette_bg_color'] = my_articles_sanitize_color($input['vignette_bg_color'] ?? '', '#ffffff');
        $sanitized_input['title_wrapper_bg_color'] = my_articles_sanitize_color($input['title_wrapper_bg_color'] ?? '', '#ffffff');
        $sanitized_input['module_margin_top'] = isset( $input['module_margin_top'] )
            ? min( 200, max( 0, intval( $input['module_margin_top'] ) ) )
            : 0;
        $sanitized_input['module_margin_bottom'] = isset( $input['module_margin_bottom'] )
            ? min( 200, max( 0, intval( $input['module_margin_bottom'] ) ) )
            : 0;
        $sanitized_input['module_margin_left'] = isset( $input['module_margin_left'] )
            ? min( 200, max( 0, intval( $input['module_margin_left'] ) ) )
            : 0;
        $sanitized_input['module_margin_right'] = isset( $input['module_margin_right'] )
            ? min( 200, max( 0, intval( $input['module_margin_right'] ) ) )
            : 0;
        $sanitized_input['pagination_color'] = my_articles_sanitize_color($input['pagination_color'] ?? '', '#333333');

        $sanitized_input['instrumentation_enabled'] = ! empty( $input['instrumentation_enabled'] ) ? 1 : 0;

        $allowed_instrumentation_channels = array( 'console', 'dataLayer', 'fetch' );
        $requested_instrumentation_channel = isset( $input['instrumentation_channel'] ) ? (string) $input['instrumentation_channel'] : 'console';

        if ( ! in_array( $requested_instrumentation_channel, $allowed_instrumentation_channels, true ) ) {
            $requested_instrumentation_channel = 'console';
        }

        $sanitized_input['instrumentation_channel'] = $requested_instrumentation_channel;

        return $sanitized_input;
    }

    // Callbacks
    public function display_mode_callback() {
        $options = get_option($this->option_name);
        $current_mode = $options['display_mode'] ?? 'grid';
        ?>
        <select id="display_mode" name="<?php echo esc_attr($this->option_name); ?>[display_mode]">
            <option value="grid" <?php selected($current_mode, 'grid'); ?>><?php esc_html_e('Grille', 'mon-articles'); ?></option>
            <option value="slideshow" <?php selected($current_mode, 'slideshow'); ?>><?php esc_html_e('Diaporama', 'mon-articles'); ?></option>
            <option value="list" <?php selected($current_mode, 'list'); ?>><?php esc_html_e('Liste', 'mon-articles'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('Choisissez comment afficher les articles.', 'mon-articles'); ?></p>
        <?php
    }
    public function default_category_callback() {
        $options = get_option($this->option_name);
        $current_category = $options['default_category'] ?? '';
        wp_dropdown_categories( array(
            'name'             => esc_attr($this->option_name) . '[default_category]',
            'selected'         => $current_category,
            'show_option_none' => __( 'Toutes les catégories', 'mon-articles' ),
            'taxonomy'         => 'category',
            'hide_empty'       => 0,
            'value_field'      => 'slug',
            'class'            => 'regular-text'
        ) );
        echo '<p class="description">' . esc_html__( 'Catégorie à utiliser si aucune n\'est spécifiée dans le shortcode.', 'mon-articles' ) . '</p>';
    }
    
    public function pagination_color_callback() {
        $this->render_color_input('pagination_color', '#333333');
    }
    
    public function posts_per_page_callback() {
        $this->render_number_input('posts_per_page', 10, 0, 50);
        echo '<p class="description">' . esc_html__( 'Utilisez 0 pour afficher un nombre illimité d\'articles.', 'mon-articles' ) . '</p>';
    }
    public function enable_keyword_search_callback() {
        $this->render_checkbox('enable_keyword_search');
        echo '<p class="description">' . esc_html__( 'Affiche un champ de recherche sur les modules compatibles.', 'mon-articles' ) . '</p>';
    }
    public function desktop_columns_callback() { $this->render_number_input('desktop_columns', 3, 1, 6); }
    public function mobile_columns_callback() { $this->render_number_input('mobile_columns', 1, 1, 3); }
    public function gap_size_callback() { $this->render_number_input('gap_size', 25, 0, 50); }
    public function border_radius_callback() { $this->render_number_input('border_radius', 12, 0, 50); }
    public function title_color_callback() { $this->render_color_input('title_color', '#333333'); }
    public function title_font_size_callback() { $this->render_number_input('title_font_size', 16, 10, 40); }
    public function show_category_callback() { $this->render_checkbox('show_category', true); }
    public function show_author_callback() { $this->render_checkbox('show_author', true); }
    public function show_date_callback() { $this->render_checkbox('show_date', true); }
    public function meta_font_size_callback() { $this->render_number_input('meta_font_size', 12, 8, 20); }
    public function meta_color_callback() { $this->render_color_input('meta_color', '#6b7280'); }
    public function meta_color_hover_callback() { $this->render_color_input('meta_color_hover', '#000000'); }
    public function module_bg_color_callback() { $this->render_color_input('module_bg_color', 'rgba(255,255,255,0)', true); }
    public function vignette_bg_color_callback() { $this->render_color_input('vignette_bg_color', '#ffffff'); }
    public function title_wrapper_bg_color_callback() { $this->render_color_input('title_wrapper_bg_color', '#ffffff'); }
    public function module_margin_top_callback() { $this->render_number_input('module_margin_top', 0, 0, 200); }
    public function module_margin_bottom_callback() { $this->render_number_input('module_margin_bottom', 0, 0, 200); }
    public function module_margin_left_callback() { $this->render_number_input('module_margin_left', 0, 0, 200); }
    public function module_margin_right_callback() { $this->render_number_input('module_margin_right', 0, 0, 200); }

    public function instrumentation_enabled_callback() {
        $this->render_checkbox('instrumentation_enabled');
        echo '<p class="description">' . esc_html__( 'Active l\'émission d\'événements JavaScript et leur routage via le canal sélectionné.', 'mon-articles' ) . '</p>';
    }

    public function instrumentation_channel_callback() {
        $options = get_option($this->option_name);
        $current_channel = isset($options['instrumentation_channel']) ? $options['instrumentation_channel'] : 'console';
        $channels = array(
            'console'   => __( 'Console du navigateur', 'mon-articles' ),
            'dataLayer' => __( 'dataLayer (Google Tag Manager)', 'mon-articles' ),
            'fetch'     => __( 'Requête Fetch (API REST)', 'mon-articles' ),
        );

        echo '<select id="instrumentation_channel" name="' . esc_attr($this->option_name) . '[instrumentation_channel]">';

        foreach ($channels as $value => $label) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr($value),
                selected($current_channel, $value, false),
                esc_html($label)
            );
        }

        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Choisissez où transmettre les événements front-end (console, dataLayer ou API REST).', 'mon-articles' ) . '</p>';
    }

    // Helpers
    private function render_text_input($id, $description = '') {
        $options = get_option($this->option_name);
        printf('<input type="text" class="regular-text" id="%s" name="%s[%s]" value="%s" />', esc_attr($id), esc_attr($this->option_name), esc_attr($id), esc_attr($options[$id] ?? ''));
        if ($description) echo '<p class="description">' . esc_html__($description, 'mon-articles') . '</p>';
    }
    private function render_number_input($id, $default, $min, $max, $step = 1) {
        $options = get_option($this->option_name);
        printf('<input type="number" step="%s" min="%s" max="%s" id="%s" name="%s[%s]" value="%s" />', esc_attr($step), esc_attr($min), esc_attr($max), esc_attr($id), esc_attr($this->option_name), esc_attr($id), esc_attr($options[$id] ?? $default));
    }
    private function render_color_input($id, $default, $alpha = false) {
        $options = get_option($this->option_name);
        $alpha_attr = $alpha ? 'data-alpha-enabled="true"' : '';
        printf('<input type="text" class="my-color-picker" id="%s" name="%s[%s]" value="%s" %s />', esc_attr($id), esc_attr($this->option_name), esc_attr($id), esc_attr($options[$id] ?? $default), $alpha_attr);
    }
    private function render_checkbox($id, $default_checked = false) {
        $options = get_option($this->option_name);
        $checked = '';
        if ( isset($options[$id]) ) {
            $checked = $options[$id] ? 'checked' : '';
        } elseif ( $default_checked ) {
            $checked = 'checked';
        }
        echo '<input type="checkbox" id="'.esc_attr($id).'" name="'.esc_attr($this->option_name).'['.esc_attr($id).']" value="1" ' . $checked . ' />';
    }

    public function reset_settings() {
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'my_articles_reset_settings_nonce' ) ) { wp_die( 'La vérification a échoué.' ); }
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Permission refusée.' ); }
        delete_option( $this->option_name );
        wp_safe_redirect( admin_url( 'admin.php?page=my-articles-settings&status=reset' ) );
        exit;
    }
}
