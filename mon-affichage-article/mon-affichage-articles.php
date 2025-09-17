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
    
    public function filter_articles_callback() {
        check_ajax_referer( 'my_articles_filter_nonce', 'security' );

        $instance_id = isset( $_POST['instance_id'] ) ? absint( wp_unslash( $_POST['instance_id'] ) ) : 0;
        $category_slug = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';

        if ( !$instance_id ) {
            wp_send_json_error( __( 'ID d\'instance manquant.', 'mon-articles' ) );
        }

        $shortcode_instance = My_Articles_Shortcode::get_instance();
        $options = (array) get_post_meta( $instance_id, '_my_articles_settings', true );
        $display_mode = $options['display_mode'] ?? 'grid';
        $post_type = ( ! empty( $options['post_type'] ) && post_type_exists( $options['post_type'] ) ) ? $options['post_type'] : 'post';
        $taxonomy = ( ! empty( $options['taxonomy'] ) && taxonomy_exists( $options['taxonomy'] ) ) ? $options['taxonomy'] : '';
        if ( empty( $taxonomy ) && 'post' === $post_type && taxonomy_exists( 'category' ) ) {
            $taxonomy = 'category';
        }
        
        $pinned_ids = array();
        if ( ! empty( $options['pinned_posts'] ) && is_array( $options['pinned_posts'] ) ) {
            $pinned_ids = array_map( 'absint', $options['pinned_posts'] );
        }

        $exclude_ids = array();
        if ( ! empty( $options['exclude_posts'] ) ) {
            $exclude_ids = array_map( 'absint', explode( ',', $options['exclude_posts'] ) );
        }

        $posts_per_page = isset( $options['posts_per_page'] ) ? max( 1, (int) $options['posts_per_page'] ) : 10;
        if ( isset( $options['counting_behavior'] )
            && 'auto_fill' === $options['counting_behavior']
            && in_array( $display_mode, array( 'grid', 'slideshow' ), true )
        ) {
            $master_columns = isset( $options['columns_ultrawide'] ) ? (int) $options['columns_ultrawide'] : 0;
            if ( $master_columns > 0 ) {
                $rows_needed   = (int) ceil( $posts_per_page / $master_columns );
                $posts_per_page = max( 1, $rows_needed * $master_columns );
            }
        }

        $all_excluded_ids = array_unique( array_merge( $pinned_ids, $exclude_ids ) );

        $pinned_query = null;
        $pinned_posts_found = 0;
        $active_pinned_ids = array();

        if ( ! empty( $pinned_ids ) ) {
            $pinned_query_args = [
                'post_type'      => 'any',
                'post_status'    => 'publish',
                'post__in'       => $pinned_ids,
                'orderby'        => 'post__in',
                'posts_per_page' => count( $pinned_ids ),
            ];

            if ( empty( $options['pinned_posts_ignore_filter'] ) && ! empty( $category_slug ) && 'all' !== $category_slug ) {
                if ( ! empty( $taxonomy ) ) {
                    $pinned_query_args['tax_query'] = [
                        [
                            'taxonomy' => $taxonomy,
                            'field'    => 'slug',
                            'terms'    => $category_slug,
                        ],
                    ];
                } else {
                    $pinned_query_args['category_name'] = $category_slug;
                }
            }

            $pinned_query = new WP_Query( $pinned_query_args );
            $pinned_posts_found = $pinned_query->post_count;
            if ( $pinned_posts_found > 0 ) {
                $active_pinned_ids = array_map( 'absint', wp_list_pluck( $pinned_query->posts, 'ID' ) );
            }
        }

        $regular_posts_needed = max( 0, $posts_per_page - $pinned_posts_found );
        $articles_query = null;
        $total_regular_posts = 0;

        $base_query_args = [
            'post_type'           => $post_type,
            'post_status'         => 'publish',
            'post__not_in'        => $all_excluded_ids,
            'ignore_sticky_posts' => ! empty( $options['ignore_native_sticky'] ) ? (int) $options['ignore_native_sticky'] : 0,
        ];

        if ($regular_posts_needed > 0) {
            $query_args = $base_query_args;
            $query_args['posts_per_page'] = $regular_posts_needed;
            $query_args['paged']          = 1;

            if ( !empty($category_slug) && $category_slug !== 'all' ) {
                if ( ! empty( $taxonomy ) ) {
                    $query_args['tax_query'] = [
                        [
                            'taxonomy' => $taxonomy,
                            'field'    => 'slug',
                            'terms'    => $category_slug,
                        ],
                    ];
                } else {
                    $query_args['category_name'] = $category_slug;
                }
            }
            $articles_query = new WP_Query($query_args);
            if ( $articles_query instanceof WP_Query ) {
                $total_regular_posts = (int) $articles_query->found_posts;
            }
        }

        if ( 0 === $total_regular_posts ) {
            $count_query_args = $base_query_args;
            $count_query_args['posts_per_page'] = 1;
            $count_query_args['paged']          = 1;
            $count_query_args['fields']         = 'ids';

            if ( !empty($category_slug) && $category_slug !== 'all' ) {
                if ( ! empty( $taxonomy ) ) {
                    $count_query_args['tax_query'] = [
                        [
                            'taxonomy' => $taxonomy,
                            'field'    => 'slug',
                            'terms'    => $category_slug,
                        ],
                    ];
                } else {
                    $count_query_args['category_name'] = $category_slug;
                }
            }
            $count_query = new WP_Query( $count_query_args );
            $total_regular_posts = (int) $count_query->found_posts;
        }

        ob_start();

        if ( $pinned_query && $pinned_query->have_posts() ) {
            while ( $pinned_query->have_posts() ) {
                $pinned_query->the_post();
                if ($display_mode === 'slideshow') echo '<div class="swiper-slide">';
                $shortcode_instance->render_article_item($options, true);
                if ($display_mode === 'slideshow') echo '</div>';
            }
        }

        if ( $articles_query && $articles_query->have_posts() ) {
            while ( $articles_query->have_posts() ) {
                $articles_query->the_post();
                if ($display_mode === 'slideshow') echo '<div class="swiper-slide">';
                $shortcode_instance->render_article_item($options, false);
                if ($display_mode === 'slideshow') echo '</div>';
            }
        }
        
        if ( ( ! $pinned_query || ! $pinned_query->have_posts() ) && ( ! $articles_query || ! $articles_query->have_posts() ) ) {
            echo '<p style="text-align: center; width: 100%; padding: 20px;">' . esc_html__( 'Aucun article trouvé dans cette catégorie.', 'mon-articles' ) . '</p>';
        }
        
        wp_reset_postdata();
        $html = ob_get_clean();

        $total_posts = $pinned_posts_found + $total_regular_posts;
        $total_pages = $posts_per_page > 0 ? (int) ceil( $total_posts / $posts_per_page ) : 0;
        $next_page   = $total_pages > 1 ? 2 : null;

        wp_send_json_success( [
            'html'        => $html,
            'total_pages' => $total_pages,
            'next_page'   => $next_page,
            'pinned_ids'  => implode( ',', ! empty( $active_pinned_ids ) ? $active_pinned_ids : array() ),
        ] );
    }

    public function load_more_articles_callback() {
        check_ajax_referer( 'my_articles_load_more_nonce', 'security' );

        $instance_id = isset( $_POST['instance_id'] ) ? absint( wp_unslash( $_POST['instance_id'] ) ) : 0;
        $paged = isset( $_POST['paged'] ) ? absint( wp_unslash( $_POST['paged'] ) ) : 1;
        $pinned_ids_str = isset( $_POST['pinned_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['pinned_ids'] ) ) : '';
        $category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';

        if (!$instance_id) { wp_send_json_error(); }

        $shortcode_instance = My_Articles_Shortcode::get_instance();
        $options = (array) get_post_meta( $instance_id, '_my_articles_settings', true );
        $post_type = ( ! empty( $options['post_type'] ) && post_type_exists( $options['post_type'] ) ) ? $options['post_type'] : 'post';
        $taxonomy = ( ! empty( $options['taxonomy'] ) && taxonomy_exists( $options['taxonomy'] ) ) ? $options['taxonomy'] : '';
        if ( empty( $taxonomy ) && 'post' === $post_type && taxonomy_exists( 'category' ) ) {
            $taxonomy = 'category';
        }
        $pinned_ids = !empty($pinned_ids_str) ? array_map('absint', explode(',', $pinned_ids_str)) : array();

        $query_args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $options['posts_per_page'] ?? 10,
            'post__not_in' => $pinned_ids,
            'paged' => $paged,
        ];

        if ( !empty($category) && $category !== 'all' ) {
            if ( ! empty( $taxonomy ) ) {
                $query_args['tax_query'] = [
                    [
                        'taxonomy' => $taxonomy,
                        'field'    => 'slug',
                        'terms'    => $category,
                    ],
                ];
            } else {
                $query_args['category_name'] = $category;
            }
        }

        $query = new WP_Query($query_args);

        ob_start();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $shortcode_instance->render_article_item($options, false);
            }
        }
        wp_reset_postdata();
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    public function get_post_type_taxonomies_callback() {
        check_ajax_referer( 'my_articles_admin_nonce', 'security' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Action non autorisée.', 'mon-articles' ) ) );
        }

        $post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : '';

        if ( empty( $post_type ) || ! post_type_exists( $post_type ) ) {
            wp_send_json_error( array( 'message' => __( 'Type de contenu invalide.', 'mon-articles' ) ) );
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

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'mon-articles' ), 403 );
        }

        $search_term = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
        $post_type   = 'post';

        if ( isset( $_GET['post_type'] ) ) {
            $post_type = sanitize_key( wp_unslash( $_GET['post_type'] ) );
        }

        if ( empty( $post_type ) ) {
            $post_type = 'post';
        }

        if ( ! post_type_exists( $post_type ) ) {
            wp_send_json_error( __( 'Type de contenu invalide.', 'mon-articles' ), 400 );
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