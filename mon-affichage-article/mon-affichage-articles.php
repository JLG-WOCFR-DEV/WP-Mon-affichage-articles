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

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

final class Mon_Affichage_Articles {
    private static $instance;
    private $cache_namespace = null;
    private $rest_controller;

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
        require_once MY_ARTICLES_PLUGIN_DIR . 'includes/interface-my-articles-content-adapter.php';
        require_once MY_ARTICLES_PLUGIN_DIR . 'includes/class-my-articles-settings.php';
        require_once MY_ARTICLES_PLUGIN_DIR . 'includes/class-my-articles-metaboxes.php';
        require_once MY_ARTICLES_PLUGIN_DIR . 'includes/class-my-articles-shortcode-data-preparer.php';
        require_once MY_ARTICLES_PLUGIN_DIR . 'includes/class-my-articles-shortcode.php';
        require_once MY_ARTICLES_PLUGIN_DIR . 'includes/class-my-articles-frontend-data.php';
        require_once MY_ARTICLES_PLUGIN_DIR . 'includes/class-my-articles-enqueue.php';
        require_once MY_ARTICLES_PLUGIN_DIR . 'includes/class-my-articles-block.php';
        require_once MY_ARTICLES_PLUGIN_DIR . 'includes/class-my-articles-response-cache-key.php';
        require_once MY_ARTICLES_PLUGIN_DIR . 'includes/class-my-articles-telemetry.php';
        require_once MY_ARTICLES_PLUGIN_DIR . 'includes/rest/class-my-articles-controller.php';

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once MY_ARTICLES_PLUGIN_DIR . 'includes/class-my-articles-cli.php';
        }

        $this->rest_controller = new My_Articles_Controller( $this );
    }

    private function add_hooks() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

        add_action( 'wp_ajax_get_post_type_taxonomies', array( $this, 'get_post_type_taxonomies_callback' ) );
        add_action( 'wp_ajax_get_taxonomy_terms', array( $this, 'get_taxonomy_terms_callback' ) );

        add_action( 'wp_ajax_search_posts_for_select2', array( $this, 'search_posts_callback' ) );
        add_action( 'wp_ajax_my_articles_render_preview', array( $this, 'render_admin_preview' ) );

        add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );

        add_action( 'save_post', array( $this, 'handle_post_save_cache_invalidation' ), 10, 3 );
        add_action( 'clean_post_cache', array( $this, 'handle_clean_post_cache_invalidation' ), 10, 2 );
        add_action( 'set_object_terms', array( $this, 'handle_set_object_terms_cache_invalidation' ), 10, 6 );
        add_action( 'clean_object_term_cache', array( $this, 'handle_clean_object_term_cache_invalidation' ), 10, 3 );

        My_Articles_Settings::get_instance();
        My_Articles_Metaboxes::get_instance();
        My_Articles_Shortcode::get_instance();
        My_Articles_Block::get_instance();
        My_Articles_Enqueue::get_instance();
        My_Articles_Frontend_Data::get_instance();
        My_Articles_Telemetry::init();
    }

    /**
     * Safely extracts and sanitizes a scalar value from an arguments array.
     *
     * Accepts strings, numbers or booleans and gracefully handles array values
     * by reading their first scalar entry—mirroring what professional-grade
     * applications do to guard against malformed requests.
     *
     * @param array    $args       Arguments array coming from user input.
     * @param string   $key        Key to retrieve from the arguments array.
     * @param callable $sanitizer  Sanitizing callback applied to the scalar value.
     * @param string   $default    Optional. Default value when the key is missing or invalid.
     *
     * @return string Sanitized scalar value or the provided default fallback.
     */
    private function sanitize_scalar_argument( array $args, $key, callable $sanitizer, $default = '' ) {
        if ( ! array_key_exists( $key, $args ) ) {
            return $default;
        }

        $value = my_articles_normalize_scalar_value( $args[ $key ] );

        if ( null === $value || ! is_callable( $sanitizer ) ) {
            return $default;
        }

        $sanitized = call_user_func( $sanitizer, $value );

        if ( is_scalar( $sanitized ) ) {
            return (string) $sanitized;
        }

        if ( is_object( $sanitized ) && method_exists( $sanitized, '__toString' ) ) {
            return (string) $sanitized;
        }

        return $default;
    }

    /**
     * Normalizes filter selections coming from user requests.
     *
     * Accepts JSON strings or array payloads and always returns the
     * fully-sanitized structure expected by the shortcode layer.
     *
     * @param mixed $raw_filters Incoming filters payload.
     * @return array<int, array{taxonomy:string,slug:string}>
     */
    private function sanitize_filters_parameter( $raw_filters ) {
        $prepared_filters = my_articles_prepare_filters_value( $raw_filters );

        return My_Articles_Shortcode::sanitize_filter_pairs( $prepared_filters );
    }

    public function validate_instance_for_request( $instance_id ) {
        $post_type = get_post_type( $instance_id );

        if ( 'mon_affichage' !== $post_type ) {
            return new WP_Error(
                'my_articles_invalid_instance_type',
                __( 'Type de contenu invalide pour cette instance.', 'mon-articles' ),
                array( 'status' => 400 )
            );
        }

        $post_status      = get_post_status( $instance_id );
        $allowed_statuses = My_Articles_Shortcode::get_allowed_instance_statuses( $instance_id );

        if ( empty( $post_status ) || ! in_array( $post_status, $allowed_statuses, true ) ) {
            $error_code = (int) apply_filters( 'my_articles_unpublished_instance_error_code', 404, $instance_id, $post_status );
            if ( $error_code <= 0 ) {
                $error_code = 404;
            }

            return new WP_Error(
                'my_articles_instance_not_published',
                __( 'Instance non publiée.', 'mon-articles' ),
                array( 'status' => $error_code )
            );
        }

        return $post_type;
    }

    private function assert_valid_instance_post_type( $instance_id ) {
        $validation = $this->validate_instance_for_request( $instance_id );

        if ( is_wp_error( $validation ) ) {
            $data   = $validation->get_error_data();
            $status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;

            wp_send_json_error(
                array( 'message' => $validation->get_error_message() ),
                $status
            );
        }

        return $validation;
    }

    private function render_articles_for_response( $shortcode_instance, $options, $pinned_query, $regular_query, $args = array() ) {
        $defaults = array(
            'render_limit'  => 0,
            'regular_limit' => -1,
            'track_pinned'  => false,
            'wrap_slides'   => ( isset( $options['display_mode'] ) && 'slideshow' === $options['display_mode'] ),
            'skip_empty_state_when_empty' => false,
            'include_skeleton'            => false,
        );

        $args = wp_parse_args( $args, $defaults );

        $render_limit  = (int) $args['render_limit'];
        $regular_limit = (int) $args['regular_limit'];
        $track_pinned  = ! empty( $args['track_pinned'] );
        $wrap_slides   = ! empty( $args['wrap_slides'] );
        $skip_empty_state = ! empty( $args['skip_empty_state_when_empty'] );
        $include_skeleton = ! empty( $args['include_skeleton'] );
        $should_limit  = ( $render_limit > 0 && ! $wrap_slides );

        ob_start();

        if ( $include_skeleton && ! $wrap_slides && method_exists( $shortcode_instance, 'get_skeleton_placeholder_markup' ) ) {
            $display_mode = $options['display_mode'] ?? 'grid';

            if ( in_array( $display_mode, array( 'grid', 'list' ), true ) ) {
                $container_class = ( 'list' === $display_mode ) ? 'my-articles-list-content' : 'my-articles-grid-content';
                if ( method_exists( $shortcode_instance, 'get_skeleton_placeholder_markup' ) ) {
                    echo $shortcode_instance->get_skeleton_placeholder_markup( $container_class, $options, $render_limit );
                }
            }
        }

        $displayed_posts_count = 0;
        $displayed_pinned_ids  = array();
        $pinned_rendered       = 0;

        if ( $pinned_query instanceof WP_Query && $pinned_query->have_posts() ) {
            while ( $pinned_query->have_posts() ) {
                if ( $should_limit && $displayed_posts_count >= $render_limit ) {
                    break;
                }

                $pinned_query->the_post();

                if ( $wrap_slides ) {
                    echo '<div class="swiper-slide">';
                }

                $shortcode_instance->render_article_item( $options, true );

                if ( $wrap_slides ) {
                    echo '</div>';
                }

                $displayed_posts_count++;
                $pinned_rendered++;

                if ( $track_pinned ) {
                    $displayed_pinned_ids[] = absint( get_the_ID() );
                }
            }
        }

        $regular_rendered = 0;

        if ( $regular_query instanceof WP_Query && $regular_query->have_posts() ) {
            while ( $regular_query->have_posts() ) {
                if ( $should_limit && $displayed_posts_count >= $render_limit ) {
                    break;
                }

                if ( $regular_limit >= 0 && $regular_rendered >= $regular_limit ) {
                    break;
                }

                $regular_query->the_post();

                if ( $wrap_slides ) {
                    echo '<div class="swiper-slide">';
                }

                $shortcode_instance->render_article_item( $options, false );

                if ( $wrap_slides ) {
                    echo '</div>';
                }

                $displayed_posts_count++;
                $regular_rendered++;
            }
        }

        $html = ob_get_clean();

        if ( $pinned_query instanceof WP_Query ) {
            wp_reset_postdata();
        }

        if ( $regular_query instanceof WP_Query ) {
            wp_reset_postdata();
        }

        if ( 0 === $displayed_posts_count ) {
            if ( $skip_empty_state ) {
                $html = '';
            } elseif ( $wrap_slides ) {
                $html = $shortcode_instance->get_empty_state_slide_html();
            } else {
                $html = $shortcode_instance->get_empty_state_html();
            }
        }

        return array(
            'html'                   => $html,
            'displayed_posts_count'  => $displayed_posts_count,
            'displayed_pinned_ids'   => $displayed_pinned_ids,
            'pinned_rendered_count'  => $pinned_rendered,
            'regular_rendered_count' => $regular_rendered,
        );
    }

public function prepare_filter_articles_response( array $args ) {
        $instance_id   = isset( $args['instance_id'] ) ? absint( $args['instance_id'] ) : 0;
        $category_slug = isset( $args['category'] ) ? sanitize_title( $args['category'] ) : '';
        $incoming_filters = $args['filters'] ?? array();
        $raw_current_url = isset( $args['current_url'] ) ? (string) $args['current_url'] : '';
        $raw_http_referer = isset( $args['http_referer'] ) ? (string) $args['http_referer'] : '';
        $search_term      = $this->sanitize_scalar_argument( $args, 'search', 'sanitize_text_field' );
        $requested_sort   = $this->sanitize_scalar_argument( $args, 'sort', 'sanitize_key' );

        $home_url    = home_url();
        $referer_url = my_articles_normalize_internal_url( $raw_current_url, $home_url );

        if ( '' === $referer_url ) {
            if ( '' === $raw_http_referer ) {
                $raw_http_referer = wp_get_referer();
            }

            $referer_url = my_articles_normalize_internal_url( $raw_http_referer, $home_url );
        }

        if ( ! $instance_id ) {
            return new WP_Error(
                'my_articles_missing_instance_id',
                __( 'ID d\'instance manquant.', 'mon-articles' ),
                array( 'status' => 400 )
            );
        }

        $validation = $this->validate_instance_for_request( $instance_id );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $shortcode_instance = My_Articles_Shortcode::get_instance();
        $preparer           = $shortcode_instance->get_data_preparer();

        $preparation = $preparer->prepare(
            $instance_id,
            array(),
            array(
                'context' => array(
                    'category' => $category_slug,
                    'search'   => $search_term,
                    'sort'     => $requested_sort,
                    'filters'  => $incoming_filters,
                    'page'     => 1,
                ),
            )
        );

        if ( is_wp_error( $preparation ) ) {
            return $preparation;
        }

        $options = isset( $preparation['options'] ) && is_array( $preparation['options'] )
            ? $preparation['options']
            : array();

        $requested_values = isset( $preparation['requested'] ) && is_array( $preparation['requested'] )
            ? $preparation['requested']
            : array();

        $requested_category = isset( $requested_values['category'] ) ? (string) $requested_values['category'] : '';
        $requested_search   = isset( $requested_values['search'] ) ? (string) $requested_values['search'] : $search_term;
        $requested_sort     = isset( $requested_values['sort'] ) ? (string) $requested_values['sort'] : $requested_sort;
        $requested_filters  = isset( $requested_values['filters'] ) && is_array( $requested_values['filters'] )
            ? $requested_values['filters']
            : array();

        $allows_requested_category = ! empty( $preparation['allows_requested_category'] );

        if ( ! $allows_requested_category && '' !== $category_slug ) {
            $default_term = isset( $options['default_term'] ) ? (string) $options['default_term'] : '';

            if ( '' === $default_term || $category_slug !== $default_term ) {
                return new WP_Error(
                    'my_articles_category_not_allowed',
                    __( 'Catégorie non autorisée.', 'mon-articles' ),
                    array( 'status' => 403 )
                );
            }
        }

        $display_mode      = $options['display_mode'];
        $resolved_taxonomy = $options['resolved_taxonomy'];
        $default_term      = $options['default_term'];
        $active_category   = $options['term'];
        $active_filters    = isset( $options['active_tax_filters'] ) && is_array( $options['active_tax_filters'] )
            ? $options['active_tax_filters']
            : array();

        $cache_fragments = array();

        if ( ! empty( $options['search_query'] ) ) {
            $cache_fragments['search'] = $options['search_query'];
        }

        if ( ! empty( $options['sort'] ) ) {
            $cache_fragments['sort'] = $options['sort'];
        }

        $cache_fragments['debug'] = ! empty( $options['enable_debug_mode'] ) ? '1' : '0';

        if ( ! empty( $options['active_tax_filter_keys'] ) ) {
            $filter_keys = array_map( 'strval', $options['active_tax_filter_keys'] );
            sort( $filter_keys );
            $cache_fragments['filters'] = implode( ',', $filter_keys );
        }

        $cache_fragments = apply_filters(
            'my_articles_cache_fragments',
            $cache_fragments,
            array(
                'source'        => 'filter',
                'instance_id'   => $instance_id,
                'category'      => $active_category,
                'display_mode'  => $display_mode,
                'paged'         => 1,
                'options'       => $options,
                'request_args'  => array(
                    'requested_category' => $category_slug,
                    'requested_filters'  => $requested_filters,
                    'requested_sort'     => $requested_sort,
                ),
            )
        );

        $cache_key = $this->generate_response_cache_key(
            $instance_id,
            $active_category,
            1,
            $display_mode,
            is_array( $cache_fragments ) ? $cache_fragments : array()
        );

        $cached_response = $this->get_cached_response( $cache_key );

        if ( is_array( $cached_response ) ) {
            return $cached_response;
        }

        $state = $shortcode_instance->build_display_state(
            $options,
            array(
                'paged'                   => 1,
                'pagination_strategy'     => 'page',
                'enforce_unlimited_batch' => ( ! empty( $options['is_unlimited'] ) && 'slideshow' !== $display_mode ),
            )
        );

        $pinned_query             = $state['pinned_query'];
        $articles_query           = $state['regular_query'];
        $total_pinned_posts       = $state['total_pinned_posts'];
        $total_regular_posts      = $state['total_regular_posts'];
        $render_limit             = $state['render_limit'];
        $is_unlimited             = ! empty( $state['is_unlimited'] );
        $effective_posts_per_page = $state['effective_posts_per_page'];

        if ( 0 === $total_regular_posts && ! ( $articles_query instanceof WP_Query ) ) {
            $count_query_args = array(
                'post_type'           => $options['post_type'],
                'post_status'         => 'publish',
                'posts_per_page'      => 1,
                'post__not_in'        => $options['all_excluded_ids'] ?? array(),
                'ignore_sticky_posts' => (int) ( $options['ignore_native_sticky'] ?? 0 ),
                'fields'              => 'ids',
            );

            if ( ! empty( $options['search_query'] ) ) {
                $count_query_args['s'] = $options['search_query'];
            }

            $count_query_args = My_Articles_Shortcode::append_active_tax_query(
                $count_query_args,
                $resolved_taxonomy,
                $active_category,
                $active_filters
            );

            $count_query        = new WP_Query( $count_query_args );
            $total_regular_posts = (int) $count_query->found_posts;
        }

        $posts_per_page_for_render = $render_limit > 0 ? $render_limit : $effective_posts_per_page;

        if ( $is_unlimited && 0 === $posts_per_page_for_render ) {
            $posts_per_page_for_render = 0;
        }

        $render_results = $this->render_articles_for_response(
            $shortcode_instance,
            $options,
            $pinned_query,
            $articles_query,
            array(
                'render_limit'  => $posts_per_page_for_render,
                'regular_limit' => $state['regular_posts_needed'],
                'track_pinned'  => true,
                'wrap_slides'   => ( 'slideshow' === $display_mode ),
                'include_skeleton' => true,
            )
        );

        $html                   = $render_results['html'];
        $displayed_pinned_ids   = $render_results['displayed_pinned_ids'];
        $displayed_count        = (int) $render_results['displayed_posts_count'];
        $rendered_regular_count = (int) $render_results['regular_rendered_count'];
        $rendered_pinned_count  = (int) $render_results['pinned_rendered_count'];

        $pagination_context = array(
            'current_page' => 1,
        );

        if ( $is_unlimited ) {
            $unlimited_batch_size = isset( $state['unlimited_batch_size'] ) ? (int) $state['unlimited_batch_size'] : 0;

            if ( $unlimited_batch_size > 0 ) {
                $pagination_context['unlimited_page_size'] = $unlimited_batch_size;
                $pagination_context['analytics_page_size'] = $unlimited_batch_size;
            }
        }

        $pagination_totals = my_articles_calculate_total_pages(
            $total_pinned_posts,
            $total_regular_posts,
            $effective_posts_per_page,
            $pagination_context
        );
        $total_pages = $pagination_totals['total_pages'];
        $next_page   = $pagination_totals['next_page'];
        $total_results = (int) $total_regular_posts + (int) $total_pinned_posts;
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

        if ( ! empty( $options['search_query'] ) ) {
            $pagination_query_args[ 'my_articles_search_' . $instance_id ] = $options['search_query'];
        }

        if ( ! empty( $options['sort'] ) ) {
            $pagination_query_args[ 'my_articles_sort_' . $instance_id ] = $options['sort'];
        }

            $pagination_html = $shortcode_instance->get_numbered_pagination_html(
                $total_pages,
                1,
                'paged_' . $instance_id,
                $pagination_query_args,
                $referer_url
            );
        }

        $response = array(
            'html'                    => $html,
            'total_pages'             => $total_pages,
            'next_page'               => $next_page,
            'pinned_ids'              => $pinned_ids_string,
            'pagination_html'         => $pagination_html,
            'search_query'            => $options['search_query'],
            'sort'                    => $options['sort'],
            'filters'                 => $active_filters,
            'displayed_count'         => $displayed_count,
            'rendered_regular_count'  => $rendered_regular_count,
            'rendered_pinned_count'   => $rendered_pinned_count,
            'total_regular'           => (int) $total_regular_posts,
            'total_pinned'            => (int) $total_pinned_posts,
            'total_results'           => $total_results,
        );

        $this->set_cached_response(
            $cache_key,
            $response,
            $this->get_cache_expiration(
                array(
                    'instance_id'   => $instance_id,
                    'category_slug' => $active_category,
                    'paged'         => 1,
                    'display_mode'  => $display_mode,
                    'context'       => 'filter_articles',
                )
            )
        );

        if ( my_articles_is_instrumentation_enabled() ) {
            do_action(
                'my_articles_track_interaction',
                'filter_response',
                array(
                    'instance_id'  => $instance_id,
                    'category'     => $active_category,
                    'search_query' => $options['search_query'],
                    'sort'         => $options['sort'],
                    'total_pages'  => $total_pages,
                    'next_page'    => $next_page,
                    'displayed'    => $displayed_count,
                    'total_results' => $total_results,
                    'rendered_regular' => $rendered_regular_count,
                    'rendered_pinned'  => $rendered_pinned_count,
                )
            );
        }

        return $response;
    }

    public function filter_articles_callback() {
        $instance_id   = isset( $_POST['instance_id'] ) ? absint( wp_unslash( $_POST['instance_id'] ) ) : 0;
        $category_slug = isset( $_POST['category'] ) ? sanitize_title( wp_unslash( $_POST['category'] ) ) : '';
        $raw_current_url = isset( $_POST['current_url'] ) ? wp_unslash( $_POST['current_url'] ) : '';
        $search_term    = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

        $filters_raw = $_POST['filters'] ?? array();
        $filters     = $this->sanitize_filters_parameter( $filters_raw );

        $raw_sort = isset( $_POST['sort'] ) ? wp_unslash( $_POST['sort'] ) : '';
        $requested_sort = '';

        if ( is_scalar( $raw_sort ) ) {
            $requested_sort = sanitize_key( (string) $raw_sort );
        }

        $response = $this->prepare_filter_articles_response(
            array(
                'instance_id'  => $instance_id,
                'category'     => $category_slug,
                'current_url'  => $raw_current_url,
                'http_referer' => wp_get_referer(),
                'search'       => $search_term,
                'sort'         => $requested_sort,
                'filters'      => $filters,
            )
        );

        if ( is_wp_error( $response ) ) {
            $data   = $response->get_error_data();
            $status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;

            wp_send_json_error(
                array( 'message' => $response->get_error_message() ),
                $status
            );
        }

        wp_send_json_success( $response );
    }

    public function prepare_load_more_articles_response( array $args ) {
        $instance_id = isset( $args['instance_id'] ) ? absint( $args['instance_id'] ) : 0;
        $requested_page = isset( $args['paged'] ) ? absint( $args['paged'] ) : 1;
        if ( $requested_page < 1 ) {
            $requested_page = 1;
        }

        $category          = isset( $args['category'] ) ? sanitize_title( $args['category'] ) : '';
        $raw_filters       = $args['filters'] ?? array();
        $requested_filters = My_Articles_Shortcode::sanitize_filter_pairs( $raw_filters );
        $search_term       = $this->sanitize_scalar_argument( $args, 'search', 'sanitize_text_field' );
        $requested_sort    = $this->sanitize_scalar_argument( $args, 'sort', 'sanitize_key' );

        $pinned_ids_param = $args['pinned_ids'] ?? '';
        if ( is_array( $pinned_ids_param ) ) {
            $pinned_ids_param = implode( ',', array_map( 'strval', $pinned_ids_param ) );
        }
        $pinned_ids_str = is_string( $pinned_ids_param ) ? $pinned_ids_param : '';

        if ( ! $instance_id ) {
            return new WP_Error(
                'my_articles_missing_instance_id',
                __( 'ID d\'instance manquant.', 'mon-articles' ),
                array( 'status' => 400 )
            );
        }

        $validation = $this->validate_instance_for_request( $instance_id );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $shortcode_instance = My_Articles_Shortcode::get_instance();
        $preparer           = $shortcode_instance->get_data_preparer();

        $preparation = $preparer->prepare(
            $instance_id,
            array(),
            array(
                'context' => array(
                    'category'            => $category,
                    'search'              => $search_term,
                    'sort'                => $requested_sort,
                    'filters'             => $requested_filters,
                    'page'                => $requested_page,
                    'force_collect_terms' => true,
                ),
            )
        );

        if ( is_wp_error( $preparation ) ) {
            return $preparation;
        }

        $options = isset( $preparation['options'] ) && is_array( $preparation['options'] )
            ? $preparation['options']
            : array();

        $requested_values = isset( $preparation['requested'] ) && is_array( $preparation['requested'] )
            ? $preparation['requested']
            : array();

        $requested_category = isset( $requested_values['category'] ) ? (string) $requested_values['category'] : '';
        $requested_search   = isset( $requested_values['search'] ) ? (string) $requested_values['search'] : $search_term;
        $requested_sort     = isset( $requested_values['sort'] ) ? (string) $requested_values['sort'] : $requested_sort;
        $requested_filters  = isset( $requested_values['filters'] ) && is_array( $requested_values['filters'] )
            ? $requested_values['filters']
            : array();
        $requested_page     = isset( $requested_values['page'] )
            ? max( 1, (int) $requested_values['page'] )
            : $requested_page;

        $allows_requested_category = ! empty( $preparation['allows_requested_category'] );

        if ( ! $allows_requested_category && '' !== $category ) {
            $default_term = isset( $options['default_term'] ) ? (string) $options['default_term'] : '';

            if ( '' === $default_term || $category !== $default_term ) {
                return new WP_Error(
                    'my_articles_category_not_allowed',
                    __( 'Catégorie non autorisée.', 'mon-articles' ),
                    array( 'status' => 403 )
                );
            }
        }

        $pagination_mode = isset( $options['pagination_mode'] ) ? $options['pagination_mode'] : 'none';

        if ( 'load_more' !== $pagination_mode ) {
            return new WP_Error(
                'my_articles_load_more_disabled',
                __( 'Le chargement progressif est désactivé pour cette instance.', 'mon-articles' ),
                array( 'status' => 400 )
            );
        }

        $display_mode = isset( $options['display_mode'] ) ? $options['display_mode'] : '';

        if ( ! in_array( $display_mode, array( 'grid', 'list' ), true ) ) {
            return new WP_Error(
                'my_articles_invalid_display_mode',
                __( 'Le mode d\'affichage sélectionné est incompatible avec "Charger plus".', 'mon-articles' ),
                array( 'status' => 400 )
            );
        }

        $seen_pinned_ids = array();
        if ( '' !== $pinned_ids_str ) {
            $seen_pinned_ids = array_map( 'absint', array_filter( array_map( 'trim', explode( ',', $pinned_ids_str ) ) ) );
            $seen_pinned_ids = array_values( array_unique( array_filter( $seen_pinned_ids ) ) );
        }

        $active_category = isset( $options['term'] ) ? (string) $options['term'] : '';
        $active_filters  = isset( $options['active_tax_filters'] ) && is_array( $options['active_tax_filters'] )
            ? $options['active_tax_filters']
            : array();

        $cache_fragments = array();

        if ( ! empty( $options['search_query'] ) ) {
            $cache_fragments['search'] = (string) $options['search_query'];
        }

        if ( ! empty( $options['sort'] ) ) {
            $cache_fragments['sort'] = (string) $options['sort'];
        }

        $cache_fragments['debug'] = ! empty( $options['enable_debug_mode'] ) ? '1' : '0';

        if ( ! empty( $seen_pinned_ids ) ) {
            $normalized_pinned = array_map( 'absint', $seen_pinned_ids );
            sort( $normalized_pinned );
            $cache_fragments['pinned'] = implode( ',', $normalized_pinned );
        }

        if ( ! empty( $options['active_tax_filter_keys'] ) ) {
            $filter_keys = array_map( 'strval', $options['active_tax_filter_keys'] );
            sort( $filter_keys );
            $cache_fragments['filters'] = implode( ',', $filter_keys );
        }

        $cache_fragments = apply_filters(
            'my_articles_cache_fragments',
            $cache_fragments,
            array(
                'source'        => 'load-more',
                'instance_id'   => $instance_id,
                'category'      => $active_category,
                'display_mode'  => $display_mode,
                'paged'         => $requested_page,
                'options'       => $options,
                'seen_pinned'   => $seen_pinned_ids,
            )
        );

        $cache_key = $this->generate_response_cache_key(
            $instance_id,
            $active_category,
            $requested_page,
            $display_mode,
            is_array( $cache_fragments ) ? $cache_fragments : array()
        );

        $cached_response = $this->get_cached_response( $cache_key );

        if ( is_array( $cached_response ) ) {
            return $cached_response;
        }

        $state = $shortcode_instance->build_display_state(
            $options,
            array(
                'paged'                   => $requested_page,
                'pagination_strategy'     => 'sequential',
                'seen_pinned_ids'         => $seen_pinned_ids,
                'enforce_unlimited_batch' => ( ! empty( $options['is_unlimited'] ) && 'slideshow' !== $display_mode ),
            )
        );

        $pinned_query             = $state['pinned_query'];
        $articles_query           = $state['regular_query'];
        $updated_seen_pinned      = $state['updated_seen_pinned_ids'];
        $total_pinned_posts       = $state['total_pinned_posts'];
        $total_regular_posts      = $state['total_regular_posts'];
        $effective_posts_per_page = $state['effective_posts_per_page'];
        $is_unlimited             = ! empty( $state['is_unlimited'] );

        $render_results = $this->render_articles_for_response(
            $shortcode_instance,
            $options,
            $pinned_query,
            $articles_query,
            array(
                'wrap_slides'                 => ( 'slideshow' === $display_mode ),
                'skip_empty_state_when_empty' => true,
            )
        );

        $html                   = $render_results['html'];
        $displayed_count        = (int) $render_results['displayed_posts_count'];
        $rendered_regular_count = (int) $render_results['regular_rendered_count'];
        $rendered_pinned_count  = (int) $render_results['pinned_rendered_count'];

        $pinned_ids_string = ! empty( $updated_seen_pinned ) ? implode( ',', array_map( 'absint', $updated_seen_pinned ) ) : '';

        $pagination_context = array(
            'current_page' => $requested_page,
        );

        if ( $is_unlimited ) {
            $pagination_context['unlimited_page_size'] = $state['unlimited_batch_size'];
            $pagination_context['analytics_page_size'] = $state['unlimited_batch_size'];
        }

        $pagination_totals = my_articles_calculate_total_pages(
            $total_pinned_posts,
            $total_regular_posts,
            $effective_posts_per_page,
            $pagination_context
        );

        $total_pages   = $pagination_totals['total_pages'];
        $next_page     = 0;
        $total_results = (int) $total_regular_posts + (int) $total_pinned_posts;

        if ( $total_pages > 0 && $requested_page < $total_pages ) {
            $next_page = $requested_page + 1;
        }

        $response = array(
            'html'                    => $html,
            'pinned_ids'              => $pinned_ids_string,
            'total_pages'             => $total_pages,
            'next_page'               => $next_page,
            'search_query'            => $options['search_query'] ?? '',
            'sort'                    => $options['sort'] ?? '',
            'filters'                 => $active_filters,
            'displayed_count'         => $displayed_count,
            'rendered_regular_count'  => $rendered_regular_count,
            'rendered_pinned_count'   => $rendered_pinned_count,
            'total_regular'           => (int) $total_regular_posts,
            'total_pinned'            => (int) $total_pinned_posts,
            'total_results'           => $total_results,
            'added_count'             => $displayed_count,
        );

        $this->set_cached_response(
            $cache_key,
            $response,
            $this->get_cache_expiration(
                array(
                    'instance_id'   => $instance_id,
                    'category_slug' => $active_category,
                    'paged'         => $requested_page,
                    'display_mode'  => $display_mode,
                    'context'       => 'load_more_articles',
                )
            )
        );

        if ( my_articles_is_instrumentation_enabled() ) {
            do_action(
                'my_articles_track_interaction',
                'load_more_response',
                array(
                    'instance_id'    => $instance_id,
                    'category'       => $active_category,
                    'search_query'   => $options['search_query'] ?? '',
                    'sort'           => $options['sort'] ?? '',
                    'requested_page' => $requested_page,
                    'next_page'      => $response['next_page'],
                    'total_pages'    => $response['total_pages'],
                    'displayed'      => $displayed_count,
                    'total_results'  => $total_results,
                    'added_count'    => $displayed_count,
                    'rendered_regular' => $rendered_regular_count,
                    'rendered_pinned'  => $rendered_pinned_count,
                )
            );
        }

        return $response;
    }

    public function load_more_articles_callback() {
        $instance_id = isset( $_POST['instance_id'] ) ? absint( wp_unslash( $_POST['instance_id'] ) ) : 0;
        $paged       = isset( $_POST['paged'] ) ? absint( wp_unslash( $_POST['paged'] ) ) : 1;
        $pinned_ids  = isset( $_POST['pinned_ids'] ) ? wp_unslash( $_POST['pinned_ids'] ) : '';
        $category    = isset( $_POST['category'] ) ? sanitize_title( wp_unslash( $_POST['category'] ) ) : '';
        $search_term = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

        $filters_raw = $_POST['filters'] ?? array();
        $filters     = $this->sanitize_filters_parameter( $filters_raw );

        $raw_sort = isset( $_POST['sort'] ) ? wp_unslash( $_POST['sort'] ) : '';
        $requested_sort = '';

        if ( is_scalar( $raw_sort ) ) {
            $requested_sort = sanitize_key( (string) $raw_sort );
        }

        $response = $this->prepare_load_more_articles_response(
            array(
                'instance_id' => $instance_id,
                'paged'       => $paged,
                'pinned_ids'  => $pinned_ids,
                'category'    => $category,
                'search'      => $search_term,
                'filters'     => $filters,
                'sort'        => $requested_sort,
            )
        );

        if ( is_wp_error( $response ) ) {
            $data   = $response->get_error_data();
            $status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;

            wp_send_json_error(
                array( 'message' => $response->get_error_message() ),
                $status
            );
        }

        wp_send_json_success( $response );
    }

    public function prepare_search_posts_response( array $args ) {
        $search_term = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';
        $post_type   = isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : '';

        if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
            $normalized_post_type = my_articles_normalize_post_type( $post_type );

            if ( '' !== $normalized_post_type ) {
                $post_type = $normalized_post_type;
            }
        }

        if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
            return new WP_Error(
                'my_articles_invalid_post_type',
                __( 'Type de contenu invalide.', 'mon-articles' ),
                array( 'status' => 400 )
            );
        }

        $post_type_object = get_post_type_object( $post_type );
        $required_cap     = isset( $post_type_object->cap->edit_posts ) ? $post_type_object->cap->edit_posts : 'edit_posts';

        if ( ! current_user_can( $required_cap ) ) {
            return new WP_Error(
                'my_articles_search_forbidden',
                __( 'Unauthorized', 'mon-articles' ),
                array( 'status' => 403 )
            );
        }

        $results = array();

        if ( '' !== $search_term ) {
            $query_args = array(
                's'                       => $search_term,
                'post_type'               => $post_type,
                'post_status'             => 'publish',
                'posts_per_page'          => 20,
                'no_found_rows'           => true,
                'ignore_sticky_posts'     => true,
                'suppress_filters'        => true,
                'fields'                  => 'ids',
                'orderby'                 => 'date',
                'order'                   => 'DESC',
                'update_post_meta_cache'  => false,
                'update_post_term_cache'  => false,
            );

            $query = new WP_Query( $query_args );

            if ( $query instanceof WP_Query && ! empty( $query->posts ) ) {
                foreach ( $query->posts as $post_id ) {
                    $post_id = absint( $post_id );

                    if ( ! $post_id ) {
                        continue;
                    }

                    $results[] = array(
                        'id'   => $post_id,
                        'text' => wp_strip_all_tags( get_the_title( $post_id ) ),
                    );
                }
            }
        }

        return array(
            'results' => $results,
        );
    }

    private function generate_response_cache_key( $instance_id, $category_slug, $paged, $display_mode, $extra = array() ) {
        $namespace = $this->get_cache_namespace();

        $builder = new My_Articles_Response_Cache_Key(
            $namespace,
            array(
                'instance' => absint( $instance_id ),
                'category' => (string) $category_slug,
                'paged'    => absint( $paged ),
                'mode'     => (string) $display_mode,
            )
        );

        if ( is_array( $extra ) ) {
            $builder->add_fragments( $extra );
        } elseif ( is_string( $extra ) && '' !== $extra ) {
            $builder->add_fragment( 'legacy', $extra );
        }

        return $builder->to_string();
    }

    private function get_cache_namespace() {
        if ( null === $this->cache_namespace ) {
            $namespace = get_option( 'my_articles_cache_namespace', '' );

            if ( empty( $namespace ) ) {
                $namespace = $this->generate_cache_namespace();

                if ( function_exists( 'update_option' ) ) {
                    update_option( 'my_articles_cache_namespace', $namespace );
                }
            }

            $this->cache_namespace = $namespace;
        }

        return $this->cache_namespace;
    }

    private function refresh_cache_namespace() {
        $this->cache_namespace = $this->generate_cache_namespace();

        if ( function_exists( 'update_option' ) ) {
            update_option( 'my_articles_cache_namespace', $this->cache_namespace );
        }

        do_action( 'my_articles_response_cache_flushed' );
    }

    private function generate_cache_namespace() {
        if ( function_exists( 'wp_generate_password' ) ) {
            return wp_generate_password( 12, false );
        }

        try {
            return substr( bin2hex( random_bytes( 6 ) ), 0, 12 );
        } catch ( Exception $e ) {
            return substr( md5( microtime( true ) . uniqid( 'my_articles', true ) ), 0, 12 );
        }
    }

    private function get_cached_response( $cache_key ) {
        $cached = false;

        if ( function_exists( 'wp_cache_get' ) ) {
            $cached = wp_cache_get( $cache_key, 'my_articles_response' );

            if ( false !== $cached ) {
                $this->log_cache_event( 'hit', $cache_key, array( 'layer' => 'object-cache' ) );

                return $cached;
            }

            $this->log_cache_event( 'miss', $cache_key, array( 'layer' => 'object-cache' ) );
        }

        $transient = false;

        if ( function_exists( 'get_transient' ) ) {
            $transient = get_transient( $cache_key );

            if ( false !== $transient ) {
                $this->log_cache_event( 'hit', $cache_key, array( 'layer' => 'transient' ) );

                if ( function_exists( 'wp_cache_set' ) ) {
                    $promoted = wp_cache_set( $cache_key, $transient, 'my_articles_response', 0 );
                    if ( $promoted ) {
                        $this->log_cache_event( 'promote', $cache_key, array( 'layer' => 'object-cache' ) );
                    }
                }

                return $transient;
            }

            $this->log_cache_event( 'miss', $cache_key, array( 'layer' => 'transient' ) );
        }

        return $transient;
    }

    private function set_cached_response( $cache_key, $data, $expiration ) {
        $expiration = absint( $expiration );

        if ( function_exists( 'wp_cache_set' ) ) {
            $stored = wp_cache_set( $cache_key, $data, 'my_articles_response', $expiration );
            $this->log_cache_event(
                $stored ? 'store' : 'store_failed',
                $cache_key,
                array(
                    'layer'      => 'object-cache',
                    'expiration' => $expiration,
                )
            );
        }

        if ( function_exists( 'set_transient' ) ) {
            $saved = set_transient( $cache_key, $data, $expiration );
            $this->log_cache_event(
                $saved ? 'store' : 'store_failed',
                $cache_key,
                array(
                    'layer'      => 'transient',
                    'expiration' => $expiration,
                )
            );
        }
    }

    private function log_cache_event( $event, $cache_key, array $context = array() ) {
        if ( ! defined( 'MY_ARTICLES_DEBUG_CACHE' ) || true !== MY_ARTICLES_DEBUG_CACHE ) {
            return;
        }

        $context['event'] = (string) $event;
        $context['key']   = (string) $cache_key;
        $context['time']  = time();

        $encoder = function_exists( 'wp_json_encode' ) ? 'wp_json_encode' : 'json_encode';
        $encoded = call_user_func( $encoder, $context );

        if ( false === $encoded || null === $encoded ) {
            $encoded = print_r( $context, true );
        }

        if ( function_exists( 'error_log' ) ) {
            error_log( '[my-articles-cache] ' . $encoded );
        }
    }

    private function get_cache_expiration( $context = array() ) {
        $default_expiration = HOUR_IN_SECONDS;

        $expiration = apply_filters( 'my_articles_response_cache_expiration', $default_expiration, $context );

        if ( ! is_numeric( $expiration ) ) {
            return $default_expiration;
        }

        $expiration = (int) $expiration;

        if ( $expiration < 0 ) {
            $expiration = 0;
        }

        return $expiration;
    }

    public function handle_post_save_cache_invalidation( $post_id, $post, $update ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        $post_type = get_post_type( $post_id );

        $tracked_post_types = my_articles_get_cache_tracked_post_types();

        if ( in_array( $post_type, $tracked_post_types, true ) ) {
            $this->refresh_cache_namespace();
        }
    }

    public function handle_clean_post_cache_invalidation( $post_id, $post ) {
        $post_type = null;

        if ( $post instanceof WP_Post ) {
            $post_type = $post->post_type;
        }

        if ( null === $post_type ) {
            $post_type = get_post_type( $post_id );
        }

        $tracked_post_types = my_articles_get_cache_tracked_post_types();

        if ( in_array( $post_type, $tracked_post_types, true ) ) {
            $this->refresh_cache_namespace();
        }
    }

    public function handle_set_object_terms_cache_invalidation( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
        $post_type = get_post_type( $object_id );

        $tracked_post_types = my_articles_get_cache_tracked_post_types();

        if ( in_array( $post_type, $tracked_post_types, true ) ) {
            $this->refresh_cache_namespace();
        }
    }

    public function handle_clean_object_term_cache_invalidation( $object_ids, $taxonomies, $clean_terms = true ) {
        if ( empty( $object_ids ) ) {
            return;
        }

        $tracked_post_types = my_articles_get_cache_tracked_post_types();

        if ( empty( $tracked_post_types ) ) {
            return;
        }

        foreach ( (array) $object_ids as $object_id ) {
            $post_type = get_post_type( $object_id );

            if ( in_array( $post_type, $tracked_post_types, true ) ) {
                $this->refresh_cache_namespace();
                break;
            }
        }
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

            $raw_name  = isset( $taxonomy->name ) ? $taxonomy->name : '';
            $label     = isset( $taxonomy->labels->singular_name ) ? $taxonomy->labels->singular_name : $taxonomy->label;
            $cleaned_name  = sanitize_text_field( $raw_name );
            $cleaned_label = sanitize_text_field( $label );

            $taxonomies[] = array(
                'name'  => $cleaned_name,
                'label' => $cleaned_label,
            );
        }

        wp_send_json_success( array_values( $taxonomies ) );
    }

    public function get_taxonomy_terms_callback() {
        check_ajax_referer( 'my_articles_admin_nonce', 'security' );

        $taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) ) : '';

        if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) {
            wp_send_json_error( array( 'message' => __( 'Taxonomie invalide.', 'mon-articles' ) ), 400 );
        }

        $taxonomy_object = get_taxonomy( $taxonomy );
        $required_caps   = array();

        if ( isset( $taxonomy_object->cap->assign_terms ) && ! empty( $taxonomy_object->cap->assign_terms ) ) {
            $required_caps[] = $taxonomy_object->cap->assign_terms;
        }

        if ( isset( $taxonomy_object->cap->manage_terms ) && ! empty( $taxonomy_object->cap->manage_terms ) ) {
            $required_caps[] = $taxonomy_object->cap->manage_terms;
        }

        if ( empty( $required_caps ) ) {
            $required_caps[] = 'edit_posts';
        }

        $has_cap = false;

        foreach ( $required_caps as $required_cap ) {
            if ( current_user_can( $required_cap ) ) {
                $has_cap = true;
                break;
            }
        }

        if ( ! $has_cap ) {
            wp_send_json_error( array( 'message' => __( 'Action non autorisée.', 'mon-articles' ) ), 403 );
        }

        $search_term = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

        $per_page_raw = isset( $_POST['per_page'] ) ? wp_unslash( $_POST['per_page'] ) : '';
        if ( is_array( $per_page_raw ) ) {
            $per_page_raw = '';
        }
        $per_page = absint( $per_page_raw );
        if ( $per_page <= 0 ) {
            $per_page = 50;
        }
        $per_page = max( 1, min( 100, $per_page ) );

        $page_raw = isset( $_POST['page'] ) ? wp_unslash( $_POST['page'] ) : '';
        if ( is_array( $page_raw ) ) {
            $page_raw = '';
        }
        $page = absint( $page_raw );
        if ( $page <= 0 ) {
            $page = 1;
        }

        $include_param = isset( $_POST['include'] ) ? wp_unslash( $_POST['include'] ) : array();
        if ( is_string( $include_param ) && '' !== $include_param ) {
            $include_param = array_map( 'absint', array_filter( array_map( 'trim', explode( ',', $include_param ) ) ) );
        } elseif ( is_array( $include_param ) ) {
            $include_param = array_map( 'absint', $include_param );
        } else {
            $include_param = array();
        }

        $term_args = array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'orderby'    => 'name',
        );

        if ( ! empty( $search_term ) ) {
            $term_args['search']     = $search_term;
            $term_args['name__like'] = $search_term;
        }

        if ( ! empty( $include_param ) ) {
            $term_args['include'] = array_values( array_unique( array_filter( $include_param ) ) );
            $term_args['orderby'] = 'include';
        } else {
            $term_args['number'] = $per_page;
            $term_args['offset'] = ( $page - 1 ) * $per_page;
        }

        $terms = get_terms( $term_args );

        if ( is_wp_error( $terms ) ) {
            wp_send_json_error( array( 'message' => $terms->get_error_message() ), 500 );
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

        $response = $this->prepare_search_posts_response(
            array(
                'search'    => isset( $_GET['search'] ) ? wp_unslash( $_GET['search'] ) : '',
                'post_type' => isset( $_GET['post_type'] ) ? wp_unslash( $_GET['post_type'] ) : '',
            )
        );

        if ( is_wp_error( $response ) ) {
            $data   = $response->get_error_data();
            $status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;

            wp_send_json_error(
                array( 'message' => $response->get_error_message() ),
                $status
            );
        }

        wp_send_json_success( $response['results'] );
    }

    public function render_admin_preview() {
        check_ajax_referer( 'my_articles_render_preview', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        if ( $post_id <= 0 ) {
            wp_send_json_error(
                array( 'message' => __( 'ID d\'instance manquant.', 'mon-articles' ) ),
                400
            );
        }

        if ( 'mon_affichage' !== get_post_type( $post_id ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Type de contenu invalide.', 'mon-articles' ) ),
                400
            );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Vous n’avez pas les droits nécessaires pour cette prévisualisation.', 'mon-articles' ) ),
                rest_authorization_required_code()
            );
        }

        $raw_settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

        if ( is_string( $raw_settings ) ) {
            $decoded_settings = json_decode( $raw_settings, true );

            if ( is_array( $decoded_settings ) ) {
                $raw_settings = $decoded_settings;
            }
        }

        if ( ! is_array( $raw_settings ) ) {
            $raw_settings = array();
        }

        $normalized_settings = array();

        foreach ( $raw_settings as $key => $value ) {
            $normalized_settings[ $key ] = wp_unslash( $value );
        }

        $shortcode = My_Articles_Shortcode::get_instance();

        $html = $shortcode->render_shortcode(
            array(
                'id'        => $post_id,
                'overrides' => $normalized_settings,
            )
        );

        $summary = My_Articles_Shortcode::get_last_render_summary();

        wp_send_json_success(
            array(
                'html'    => $html,
                'summary' => $summary,
            )
        );
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
            'map_meta_cap' => true,
            'show_in_rest' => true,
            'rest_base' => 'mon_affichage',
        ];
        register_post_type( 'mon_affichage', $args );
    }

    public function load_textdomain() {
        $domain = 'mon-articles';
        $locale = apply_filters( 'plugin_locale', determine_locale(), $domain );

        $base64_filename = sprintf( '%1$s-%2$s.mo.base64', $domain, $locale );
        $base64_path     = MY_ARTICLES_PLUGIN_DIR . 'languages/' . $base64_filename;

        if ( ! file_exists( $base64_path ) ) {
            return load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }

        $uploads = wp_upload_dir();

        if ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
            $target_dir = trailingslashit( $uploads['basedir'] ) . 'mon-affichage-articles/languages/';
        } else {
            $target_dir = MY_ARTICLES_PLUGIN_DIR . 'languages/generated/';
        }

        if ( ! wp_mkdir_p( $target_dir ) ) {
            return load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }

        $mo_filename = sprintf( '%1$s-%2$s.mo', $domain, $locale );
        $mo_path     = $target_dir . $mo_filename;

        $base64_mtime = @filemtime( $base64_path );
        $mo_mtime     = @filemtime( $mo_path );

        $needs_refresh = ! file_exists( $mo_path );

        if ( ! $needs_refresh && $base64_mtime && $mo_mtime ) {
            $needs_refresh = $base64_mtime > $mo_mtime;
        }

        if ( $needs_refresh ) {
            $encoded = file_get_contents( $base64_path );

            if ( false === $encoded ) {
                return load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
            }

            $decoded = base64_decode( trim( $encoded ), true );

            if ( false === $decoded ) {
                return load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
            }

            if ( false === file_put_contents( $mo_path, $decoded ) ) {
                return load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
            }
        }

        return load_textdomain( $domain, $mo_path );
    }
}

function my_articles_plugin_run() {
    return Mon_Affichage_Articles::get_instance();
}

if ( ! defined( 'MY_ARTICLES_DISABLE_AUTOBOOT' ) ) {
    my_articles_plugin_run();
}
