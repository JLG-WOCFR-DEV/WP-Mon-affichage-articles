<?php
/**
 * Plugin Name:       Tuiles - LCV
 * Description:       Affiche les articles d'une catégorie spécifique via un shortcode, avec un design personnalisable.
 * Version:           2.4.0
 * Author:            LCV
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       mon-articles
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'MY_ARTICLES_VERSION', '2.4.0' );
define( 'MY_ARTICLES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MY_ARTICLES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

final class Mon_Affichage_Articles {
    private static $instance;

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new Mon_Affichage_Articles();
            self::$instance->includes();
            self::$instance->add_hooks();
        }
        return self::$instance;
    }

    private function includes() {
        require_once MY_ARTICLES_PLUGIN_DIR . 'includes/helpers.php';
        require_once MY_ARTICLES_PLUGIN_DIR . 'includes/class-my-articles-settings.php';
        require_once MY_ARTICLES_PLUGIN_DIR . 'includes/class-my-articles-metaboxes.php';
        require_once MY_ARTICLES_PLUGIN_DIR . 'includes/class-my-articles-shortcode.php';
        require_once MY_ARTICLES_PLUGIN_DIR . 'includes/class-my-articles-enqueue.php';
    }

    private function add_hooks() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

        add_action( 'wp_ajax_filter_articles', array( $this, 'filter_articles_callback' ) );
        add_action( 'wp_ajax_nopriv_filter_articles', array( $this, 'filter_articles_callback' ) );

        add_action( 'wp_ajax_get_post_type_taxonomies', array( $this, 'get_post_type_taxonomies_callback' ) );
        add_action( 'wp_ajax_get_taxonomy_terms', array( $this, 'get_taxonomy_terms_callback' ) );

        add_action( 'wp_ajax_search_posts_for_select2', array( $this, 'search_posts_callback' ) );

        add_action( 'wp_ajax_load_more_articles', array( $this, 'load_more_articles_callback' ) );
        add_action( 'wp_ajax_nopriv_load_more_articles', array( $this, 'load_more_articles_callback' ) );

        My_Articles_Settings::get_instance();
        My_Articles_Metaboxes::get_instance();
        My_Articles_Shortcode::get_instance();
        My_Articles_Enqueue::get_instance();
    }

    private function assert_valid_instance_post_type( $instance_id ) {
        $post_type = get_post_type( $instance_id );

        if ( 'mon_affichage' !== $post_type ) {
            wp_send_json_error( __( 'Type de contenu invalide pour cette instance.', 'mon-articles' ), 400 );
        }

        return $post_type;
    }

    public function filter_articles_callback() {
        check_ajax_referer( 'my_articles_filter_nonce', 'security' );

        $instance_id   = isset( $_POST['instance_id'] ) ? absint( wp_unslash( $_POST['instance_id'] ) ) : 0;
        $category_slug = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
        $raw_current_url = isset( $_POST['current_url'] ) ? wp_unslash( $_POST['current_url'] ) : '';

        $home_url  = home_url();
        $site_host = wp_parse_url( $home_url, PHP_URL_HOST );
        $site_host = is_string( $site_host ) ? strtolower( $site_host ) : '';
        $site_scheme = wp_parse_url( $home_url, PHP_URL_SCHEME );
        $site_scheme = is_string( $site_scheme ) ? strtolower( $site_scheme ) : '';

        $sanitize_referer = static function ( $url ) use ( $site_host, $site_scheme ) {
            if ( ! is_string( $url ) || '' === $url ) {
                return '';
            }

            $clean_url = esc_url_raw( $url );
            if ( '' === $clean_url ) {
                return '';
            }

            $hash_position = strpos( $clean_url, '#' );
            if ( false !== $hash_position ) {
                $clean_url = substr( $clean_url, 0, $hash_position );
            }

            $referer_host = wp_parse_url( $clean_url, PHP_URL_HOST );
            $referer_host = is_string( $referer_host ) ? strtolower( $referer_host ) : '';
            if ( '' !== $site_host && ( '' === $referer_host || $site_host !== $referer_host ) ) {
                return '';
            }

            $referer_scheme = wp_parse_url( $clean_url, PHP_URL_SCHEME );
            $referer_scheme = is_string( $referer_scheme ) ? strtolower( $referer_scheme ) : '';
            if ( '' !== $site_scheme && '' !== $referer_scheme && $site_scheme !== $referer_scheme ) {
                return '';
            }

            return $clean_url;
        };

        $referer_url = $sanitize_referer( $raw_current_url );
        if ( '' === $referer_url ) {
            $referer_url = $sanitize_referer( wp_get_referer() );
        }

        if ( ! $instance_id ) {
            wp_send_json_error( __( 'ID d\'instance manquant.', 'mon-articles' ) );
        }

        $this->assert_valid_instance_post_type( $instance_id );

        $shortcode_instance = My_Articles_Shortcode::get_instance();
        $options_meta       = (array) get_post_meta( $instance_id, '_my_articles_settings', true );
        $options            = My_Articles_Shortcode::normalize_instance_options(
            $options_meta,
            array(
                'requested_category'  => $category_slug,
                'force_collect_terms' => true,
            )
        );

        if ( ! empty( $options['allowed_filter_term_slugs'] ) && empty( $options['is_requested_category_valid'] ) ) {
            wp_send_json_error( __( 'Catégorie non autorisée.', 'mon-articles' ) );
        }

        $display_mode       = $options['display_mode'];
        $post_type          = $options['post_type'];
        $resolved_taxonomy  = $options['resolved_taxonomy'];
        $default_term       = $options['default_term'];
        $active_category    = $options['term'];

        $posts_per_page    = (int) $options['posts_per_page'];
        $is_unlimited      = ! empty( $options['is_unlimited'] );
        $ignore_sticky_posts = (int) $options['ignore_native_sticky'];

        $render_limit = $is_unlimited ? 0 : max( 0, $posts_per_page );

        $pinned_ids       = $options['pinned_posts'];
        $exclude_ids      = $options['exclude_post_ids'];
        $all_excluded_ids = $options['all_excluded_ids'];

        $pinned_query         = null;
        $total_pinned_posts   = 0;
        $displayed_pinned_ids = array();

        if ( ! empty( $pinned_ids ) ) {
            $pinned_posts_per_page = count( $pinned_ids );

            if ( 'slideshow' === $display_mode && $render_limit > 0 ) {
                $pinned_posts_per_page = min( $pinned_posts_per_page, $render_limit );
            }

            $pinned_query_args = [
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'post__in'       => $pinned_ids,
                'orderby'        => 'post__in',
                'posts_per_page' => $pinned_posts_per_page,
                'post__not_in'   => $exclude_ids,
            ];

            if ( empty( $options['pinned_posts_ignore_filter'] ) && '' !== $active_category && 'all' !== $active_category && '' !== $resolved_taxonomy ) {
                $pinned_query_args['tax_query'] = [
                    [
                        'taxonomy' => $resolved_taxonomy,
                        'field'    => 'slug',
                        'terms'    => $active_category,
                    ],
                ];
            }
            $pinned_query = new WP_Query( $pinned_query_args );
            if ( $pinned_query instanceof WP_Query ) {
                $total_pinned_posts = (int) ( $pinned_query->found_posts ?? $pinned_query->post_count );
            }
        }

        $displayed_posts_count = 0;
        $should_limit_display   = ( $render_limit > 0 );

        $projected_pinned_display = 0;
        if ( $pinned_query instanceof WP_Query ) {
            $projected_pinned_display = (int) $pinned_query->post_count;
            if ( $should_limit_display ) {
                $projected_pinned_display = min( $projected_pinned_display, $render_limit );
            }
        }

        $total_posts_needed   = $posts_per_page;
        $regular_posts_needed = $is_unlimited ? -1 : max( 0, $total_posts_needed - $projected_pinned_display );
        $articles_query = null;

        if ( $is_unlimited || $regular_posts_needed > 0 ) {
            $query_args = [
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => $is_unlimited ? -1 : $regular_posts_needed,
                'post__not_in' => $all_excluded_ids,
                'ignore_sticky_posts' => $ignore_sticky_posts,
            ];

            if ( '' !== $active_category && 'all' !== $active_category && '' !== $resolved_taxonomy ) {
                $query_args['tax_query'] = [
                    [
                        'taxonomy' => $resolved_taxonomy,
                        'field'    => 'slug',
                        'terms'    => $active_category,
                    ],
                ];
            }
            $articles_query = new WP_Query($query_args);
        }

        ob_start();

        if ( $pinned_query && $pinned_query->have_posts() ) {
            while ( $pinned_query->have_posts() && ( ! $should_limit_display || $displayed_posts_count < $render_limit ) ) {
                $pinned_query->the_post();
                if ($display_mode === 'slideshow') echo '<div class="swiper-slide">';
                $shortcode_instance->render_article_item($options, true);
                if ($display_mode === 'slideshow') echo '</div>';
                $displayed_posts_count++;
                $displayed_pinned_ids[] = get_the_ID();
            }
        }

        if ( $articles_query && $articles_query->have_posts() ) {
            while ( $articles_query->have_posts() && ( ! $should_limit_display || $displayed_posts_count < $render_limit ) ) {
                $articles_query->the_post();
                if ($display_mode === 'slideshow') echo '<div class="swiper-slide">';
                $shortcode_instance->render_article_item($options, false);
                if ($display_mode === 'slideshow') echo '</div>';
                $displayed_posts_count++;
            }
        }

        if ( 0 === $displayed_posts_count ) {
            echo '<p style="text-align: center; width: 100%; padding: 20px;">' . esc_html__( 'Aucun article trouvé dans cette catégorie.', 'mon-articles' ) . '</p>';
        }
        
        wp_reset_postdata();
        $html = ob_get_clean();

        $total_regular_posts = 0;

        if ( $articles_query instanceof WP_Query ) {
            $total_regular_posts = (int) $articles_query->found_posts;
        } else {
            $count_query_args = [
                'post_type'           => $post_type,
                'post_status'         => 'publish',
                'posts_per_page'      => 1,
                'post__not_in'        => $all_excluded_ids,
                'ignore_sticky_posts' => $ignore_sticky_posts,
                'fields'              => 'ids',
            ];

            if ( '' !== $active_category && 'all' !== $active_category && '' !== $resolved_taxonomy ) {
                $count_query_args['tax_query'] = [
                    [
                        'taxonomy' => $resolved_taxonomy,
                        'field'    => 'slug',
                        'terms'    => $active_category,
                    ],
                ];
            }

            $count_query = new WP_Query( $count_query_args );
            $total_regular_posts = (int) $count_query->found_posts;
            wp_reset_postdata();
        }

        if ( 0 === $total_pinned_posts && ! empty( $displayed_pinned_ids ) ) {
            $total_pinned_posts = count( $displayed_pinned_ids );
        }

        $pagination_totals = my_articles_calculate_total_pages(
            $total_pinned_posts,
            $total_regular_posts,
            $posts_per_page
        );
        $total_pages = $pagination_totals['total_pages'];
        $next_page   = $pagination_totals['next_page'];
        $pinned_ids_string = ! empty( $displayed_pinned_ids ) ? implode( ',', array_map( 'absint', $displayed_pinned_ids ) ) : '';

        $pagination_html = '';
        if ( 'numbered' === ( $options['pagination_mode'] ?? '' ) ) {
            $pagination_query_args = array();
            $category_query_var    = 'my_articles_cat_' . $instance_id;
            $current_filter_slug   = $active_category;

            if ( '' === $current_filter_slug ) {
                $current_filter_slug = $default_term;
            }

            if ( '' !== $current_filter_slug ) {
                $pagination_query_args[ $category_query_var ] = $current_filter_slug;
            }

            $pagination_html = $shortcode_instance->get_numbered_pagination_html(
                $total_pages,
                1,
                'paged_' . $instance_id,
                $pagination_query_args,
                $referer_url
            );
        }

        wp_send_json_success(
            [
                'html'             => $html,
                'total_pages'      => $total_pages,
                'next_page'        => $next_page,
                'pinned_ids'       => $pinned_ids_string,
                'pagination_html'  => $pagination_html,
            ]
        );
    }

    public function load_more_articles_callback() {
        check_ajax_referer( 'my_articles_load_more_nonce', 'security' );

        $instance_id = isset( $_POST['instance_id'] ) ? absint( wp_unslash( $_POST['instance_id'] ) ) : 0;
        $paged = isset( $_POST['paged'] ) ? absint( wp_unslash( $_POST['paged'] ) ) : 1;
        $pinned_ids_str = isset( $_POST['pinned_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['pinned_ids'] ) ) : '';
        $category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';

        if ( ! $instance_id ) {
            wp_send_json_error( __( 'ID d\'instance manquant.', 'mon-articles' ) );
        }

        $this->assert_valid_instance_post_type( $instance_id );

        $shortcode_instance = My_Articles_Shortcode::get_instance();
        $options_meta       = (array) get_post_meta( $instance_id, '_my_articles_settings', true );
        $options            = My_Articles_Shortcode::normalize_instance_options(
            $options_meta,
            array(
                'requested_category'  => $category,
                'force_collect_terms' => true,
            )
        );

        if ( ! empty( $options['allowed_filter_term_slugs'] ) && empty( $options['is_requested_category_valid'] ) ) {
            wp_send_json_error( __( 'Catégorie non autorisée.', 'mon-articles' ) );
        }

        $display_mode      = $options['display_mode'];
        $post_type         = $options['post_type'];
        $resolved_taxonomy = $options['resolved_taxonomy'];
        $default_term      = $options['default_term'];
        $active_category   = $options['term'];
        $configured_pinned_ids = $options['pinned_posts'];

        $posts_per_page = (int) $options['posts_per_page'];
        $is_unlimited   = ! empty( $options['is_unlimited'] );

        $seen_pinned_ids = array();
        if ( ! empty( $pinned_ids_str ) ) {
            $seen_pinned_ids = array_map( 'absint', array_filter( array_map( 'trim', explode( ',', $pinned_ids_str ) ) ) );
            $seen_pinned_ids = array_values( array_unique( array_filter( $seen_pinned_ids ) ) );
        }

        if ( $is_unlimited && $paged > 1 ) {
            $sanitized_seen = ! empty( $seen_pinned_ids ) ? implode( ',', $seen_pinned_ids ) : '';
            wp_send_json_success(
                [
                    'html'       => '',
                    'pinned_ids' => $sanitized_seen,
                ]
            );
        }

        $exclude_ids = $options['exclude_post_ids'];

        $ignore_sticky_posts = (int) $options['ignore_native_sticky'];
        $matching_pinned_ids = array();
        if ( ! empty( $configured_pinned_ids ) ) {
            $pinned_lookup_args = [
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'post__in'       => $configured_pinned_ids,
                'orderby'        => 'post__in',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'post__not_in'   => $exclude_ids,
            ];

            if ( empty( $options['pinned_posts_ignore_filter'] ) && '' !== $active_category && 'all' !== $active_category && '' !== $resolved_taxonomy ) {
                $pinned_lookup_args['tax_query'] = [
                    [
                        'taxonomy' => $resolved_taxonomy,
                        'field'    => 'slug',
                        'terms'    => $active_category,
                    ],
                ];
            }

            $pinned_lookup_query = new WP_Query( $pinned_lookup_args );
            if ( $pinned_lookup_query->have_posts() ) {
                $matching_pinned_ids = array_values(
                    array_filter(
                        array_map( 'absint', $pinned_lookup_query->posts ),
                        static function ( $post_id ) use ( $post_type ) {
                            return $post_id > 0 && get_post_type( $post_id ) === $post_type;
                        }
                    )
                );
            }
            wp_reset_postdata();
        }

        $seen_pinned_ids = array_values( array_intersect( $matching_pinned_ids, $seen_pinned_ids ) );
        $remaining_pinned_ids = array_values( array_diff( $matching_pinned_ids, $seen_pinned_ids ) );

        $initial_seen_pinned_count = count( $seen_pinned_ids );

        $pinned_render_limit = $is_unlimited ? -1 : max( 0, $posts_per_page );
        if ( $is_unlimited ) {
            $pinned_ids_to_render = $remaining_pinned_ids;
        } elseif ( $pinned_render_limit > 0 ) {
            $pinned_ids_to_render = array_slice( $remaining_pinned_ids, 0, $pinned_render_limit );
        } else {
            $pinned_ids_to_render = array();
        }

        $actual_pinned_rendered = 0;

        ob_start();

        if ( ! empty( $pinned_ids_to_render ) ) {
            $pinned_render_query = new WP_Query(
                [
                    'post_type'      => $post_type,
                    'post_status'    => 'publish',
                    'post__in'       => $pinned_ids_to_render,
                    'orderby'        => 'post__in',
                    'posts_per_page' => count( $pinned_ids_to_render ),
                ]
            );

            if ( $pinned_render_query->have_posts() ) {
                while ( $pinned_render_query->have_posts() ) {
                    $pinned_render_query->the_post();
                    $shortcode_instance->render_article_item( $options, true );
                }
                $rendered_pinned_ids = array_map( 'absint', wp_list_pluck( $pinned_render_query->posts, 'ID' ) );
                $actual_pinned_rendered = count( $rendered_pinned_ids );
                $seen_pinned_ids    = array_values( array_unique( array_merge( $seen_pinned_ids, $rendered_pinned_ids ) ) );
            }
            wp_reset_postdata();
        }

        $max_items_before_current_page = $is_unlimited ? 0 : max( 0, ( $paged - 1 ) * $posts_per_page );
        $regular_posts_already_displayed = max( 0, $max_items_before_current_page - $initial_seen_pinned_count );

        $offset = $regular_posts_already_displayed;

        $regular_excluded_ids = array_unique(
            array_merge(
                $seen_pinned_ids,
                $exclude_ids,
                $matching_pinned_ids
            )
        );

        $regular_posts_limit = $is_unlimited ? -1 : max( 0, $posts_per_page - $actual_pinned_rendered );

        if ( $is_unlimited || $regular_posts_limit > 0 ) {
            $query_args = [
                'post_type'           => $post_type,
                'post_status'         => 'publish',
                'posts_per_page'      => $is_unlimited ? -1 : $regular_posts_limit,
                'post__not_in'        => $regular_excluded_ids,
                'offset'              => $is_unlimited ? 0 : $offset,
                'ignore_sticky_posts' => $ignore_sticky_posts,
            ];

            if ( '' !== $active_category && 'all' !== $active_category && '' !== $resolved_taxonomy ) {
                $query_args['tax_query'] = [
                    [
                        'taxonomy' => $resolved_taxonomy,
                        'field'    => 'slug',
                        'terms'    => $active_category,
                    ],
                ];
            }

            $query = new WP_Query( $query_args );

            if ( $query->have_posts() ) {
                while ( $query->have_posts() ) {
                    $query->the_post();
                    $shortcode_instance->render_article_item( $options, false );
                }
            }

            wp_reset_postdata();
        }

        $html = ob_get_clean();
        $pinned_ids_string = ! empty( $seen_pinned_ids ) ? implode( ',', array_map( 'absint', $seen_pinned_ids ) ) : '';

        wp_send_json_success(
            [
                'html'       => $html,
                'pinned_ids' => $pinned_ids_string,
            ]
        );
    }

    public function get_post_type_taxonomies_callback() {
        check_ajax_referer( 'my_articles_admin_nonce', 'security' );

        $raw_post_type = isset( $_POST['post_type'] ) ? wp_unslash( $_POST['post_type'] ) : '';
        $post_type     = sanitize_key( $raw_post_type );

        if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
            wp_send_json_error( array( 'message' => __( 'Type de contenu invalide.', 'mon-articles' ) ), 400 );
        }

        $post_type_object = get_post_type_object( $post_type );
        $required_cap     = isset( $post_type_object->cap->edit_posts ) ? $post_type_object->cap->edit_posts : 'edit_posts';

        if ( ! current_user_can( $required_cap ) ) {
            wp_send_json_error( array( 'message' => __( 'Action non autorisée.', 'mon-articles' ) ), 403 );
        }

        $taxonomies_objects = get_object_taxonomies( $post_type, 'objects' );
        $taxonomies = array();

        foreach ( $taxonomies_objects as $taxonomy ) {
            if ( isset( $taxonomy->show_ui ) && ! $taxonomy->show_ui ) {
                continue;
            }

            $label = isset( $taxonomy->labels->singular_name ) ? $taxonomy->labels->singular_name : $taxonomy->label;

            $taxonomies[] = array(
                'name'  => $taxonomy->name,
                'label' => $label,
            );
        }

        wp_send_json_success( array_values( $taxonomies ) );
    }

    public function get_taxonomy_terms_callback() {
        check_ajax_referer( 'my_articles_admin_nonce', 'security' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Action non autorisée.', 'mon-articles' ) ) );
        }

        $taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) ) : '';

        if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) {
            wp_send_json_error( array( 'message' => __( 'Taxonomie invalide.', 'mon-articles' ) ) );
        }

        $terms = get_terms(
            array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            )
        );

        if ( is_wp_error( $terms ) ) {
            wp_send_json_error( array( 'message' => $terms->get_error_message() ) );
        }

        $formatted_terms = array();

        foreach ( $terms as $term ) {
            $formatted_terms[] = array(
                'term_id' => $term->term_id,
                'slug'    => $term->slug,
                'name'    => $term->name,
            );
        }

        wp_send_json_success( array_values( $formatted_terms ) );
    }

    public function search_posts_callback() {
        check_ajax_referer( 'my_articles_select2_nonce', 'security' );

        $search_term = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
        $raw_post_type = isset( $_GET['post_type'] ) ? wp_unslash( $_GET['post_type'] ) : '';
        $post_type     = sanitize_key( $raw_post_type );

        if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
            wp_send_json_error( __( 'Type de contenu invalide.', 'mon-articles' ), 400 );
        }

        $post_type_object = get_post_type_object( $post_type );
        $required_cap     = isset( $post_type_object->cap->edit_posts ) ? $post_type_object->cap->edit_posts : 'edit_posts';

        if ( ! current_user_can( $required_cap ) ) {
            wp_send_json_error( __( 'Unauthorized', 'mon-articles' ), 403 );
        }
        $results = [];

        if ( !empty($search_term) ) {
            $query = new WP_Query([
                's' => $search_term,
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => 20,
            ]);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $results[] = [
                        'id' => get_the_ID(),
                        'text' => wp_strip_all_tags( get_the_title() ),
                    ];
                }
                wp_reset_postdata();
            }
        }

        wp_send_json_success($results);
    }

    public function register_post_type() {
        $labels = [
            'name' => _x( 'Affichages Articles', 'Post Type General Name', 'mon-articles' ),
            'singular_name' => _x( 'Affichage Articles', 'Post Type Singular Name', 'mon-articles' ),
            'menu_name' => __( 'Mes Affichages', 'mon-articles' ),
            'name_admin_bar' => __( 'Affichage Articles', 'mon-articles' ),
            'all_items' => __( 'Tous les Affichages', 'mon-articles' ),
            'add_new_item' => __( 'Ajouter un nouvel Affichage', 'mon-articles' ),
            'add_new' => __( 'Ajouter', 'mon-articles' ),
            'new_item' => __( 'Nouvel Affichage', 'mon-articles' ),
            'edit_item' => __( 'Modifier l\'Affichage', 'mon-articles' ),
            'update_item' => __( 'Mettre à jour l\'Affichage', 'mon-articles' ),
        ];
        $args = [
            'label' => __( 'Affichage Articles', 'mon-articles' ),
            'description' => __( 'Configurations pour le shortcode d\'affichage d\'articles.', 'mon-articles' ),
            'labels' => $labels,
            'supports' => [ 'title' ],
            'hierarchical' => false,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 26,
            'menu_icon' => 'dashicons-layout',
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => false,
            'can_export' => true,
            'has_archive' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'capability_type' => 'post',
            'show_in_rest' => false,
        ];
        register_post_type( 'mon_affichage', $args );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'mon-articles', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
}

function my_articles_plugin_run() {
    return Mon_Affichage_Articles::get_instance();
}

my_articles_plugin_run();