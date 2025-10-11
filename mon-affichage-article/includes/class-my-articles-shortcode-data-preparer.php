<?php
if ( ! defined( 'WPINC' ) ) {
    die;
}

class My_Articles_Shortcode_Data_Preparer {
    const CACHE_GROUP = 'my_articles_shortcode_prep';

    /**
     * Runtime cache for prepared payloads within the same request.
     *
     * @var array<string, array|WP_Error>
     */
    private static $runtime_cache = array();

    /**
     * @var My_Articles_Shortcode
     */
    private $shortcode;

    /**
     * @param My_Articles_Shortcode $shortcode Shortcode instance used as data source.
     */
    public function __construct( My_Articles_Shortcode $shortcode ) {
        $this->shortcode = $shortcode;
    }

    /**
     * Prepare the normalized options and runtime metadata required to render a shortcode instance.
     *
     * @param int   $instance_id Instance (post) identifier.
     * @param array $overrides   Optional. Overrides coming from the shortcode attributes.
     * @param array $args        {
     *     Optional. Additional preparation arguments.
     *
     *     @type array|null $request       Request variables (defaults to $_GET).
     *     @type bool       $force_refresh Whether to bypass caches.
     *     @type array      $context       Optional. Pre-sanitized context overriding request derived values. Accepts
     *                                     `category`, `search`, `sort`, `filters`, `page` and `force_collect_terms` keys.
     * }
     *
     * @return array|WP_Error Prepared payload or error when the request is invalid.
     */
    public function prepare( $instance_id, array $overrides = array(), array $args = array() ) {
        $defaults = array(
            'request'       => null,
            'force_refresh' => false,
            'context'       => array(),
        );

        $args    = wp_parse_args( $args, $defaults );
        $request = $this->sanitize_request_payload( $args['request'] );
        $context = is_array( $args['context'] ) ? $args['context'] : array();

        $options_meta = get_post_meta( $instance_id, '_my_articles_settings', true );
        if ( ! is_array( $options_meta ) ) {
            $options_meta = array();
        }

        if ( ! empty( $overrides ) ) {
            $options_meta = array_merge( $options_meta, $overrides );
        }

        $options_meta = $this->prepare_accessibility_labels( $instance_id, $options_meta );

        $filter_categories_info = $this->resolve_filter_categories( $options_meta );
        $allows_requested_category = ! empty( $options_meta['show_category_filter'] ) || ! empty( $filter_categories_info['has_filter_categories'] );

        $query_vars = array(
            'category' => 'my_articles_cat_' . $instance_id,
            'search'   => 'my_articles_search_' . $instance_id,
            'sort'     => 'my_articles_sort_' . $instance_id,
            'paged'    => 'paged_' . $instance_id,
        );

        $requested_category = '';
        if ( $allows_requested_category ) {
            $requested_category = $this->read_request_value( $request, $query_vars['category'], 'sanitize_title' );
        }

        $requested_search = $this->read_request_value( $request, $query_vars['search'], 'sanitize_text_field' );
        $requested_sort   = $this->read_request_value( $request, $query_vars['sort'], array( $this, 'sanitize_sort_value' ) );
        $requested_page   = $this->normalize_requested_page(
            $this->read_request_value( $request, $query_vars['paged'] )
        );

        if ( array_key_exists( 'category', $context ) ) {
            $requested_category = $this->maybe_sanitize_category_value( $context['category'] );
        }

        if ( array_key_exists( 'search', $context ) ) {
            $requested_search = $this->maybe_sanitize_search_value( $context['search'] );
        }

        if ( array_key_exists( 'sort', $context ) ) {
            $requested_sort = $this->sanitize_sort_value( (string) $context['sort'] );
        }

        if ( array_key_exists( 'page', $context ) ) {
            $requested_page = $this->normalize_requested_page( $context['page'] );
        }

        $requested_filters = array();
        $should_force_collect_terms = ! empty( $context['force_collect_terms'] );

        if ( array_key_exists( 'filters', $context ) ) {
            $requested_filters = $this->sanitize_requested_filters( $context['filters'], $options_meta );
        }

        $cache_key = $this->build_cache_key(
            $instance_id,
            $options_meta,
            array(
                'category' => $requested_category,
                'search'   => $requested_search,
                'sort'     => $requested_sort,
                'filters'  => $requested_filters,
            )
        );

        if ( ! $args['force_refresh'] ) {
            if ( isset( self::$runtime_cache[ $cache_key ] ) ) {
                return self::$runtime_cache[ $cache_key ];
            }

            $cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
            if ( false !== $cached ) {
                self::$runtime_cache[ $cache_key ] = $cached;
                return $cached;
            }
        }

        $normalize_context = array();
        if ( '' !== $requested_category ) {
            $normalize_context['requested_category'] = $requested_category;
        }

        if ( '' !== $requested_search ) {
            $normalize_context['requested_search'] = $requested_search;
        }

        if ( '' !== $requested_sort ) {
            $normalize_context['requested_sort'] = $requested_sort;
        }

        if ( $allows_requested_category ) {
            $normalize_context['allow_external_requested_category'] = true;
        }

        if ( ! empty( $filter_categories_info['normalized'] ) ) {
            $normalize_context['requested_filters'] = $filter_categories_info['normalized'];
        }

        if ( ! empty( $requested_filters ) ) {
            $normalize_context['requested_filters'] = $requested_filters;
        }

        if ( $should_force_collect_terms ) {
            $normalize_context['force_collect_terms'] = true;
        }

        $options = My_Articles_Shortcode::normalize_instance_options( $options_meta, $normalize_context );

        if ( ! empty( $options['allowed_filter_term_slugs'] ) && empty( $options['is_requested_category_valid'] ) ) {
            $error = new WP_Error(
                'my_articles_category_not_allowed',
                __( 'Catégorie non autorisée.', 'mon-articles' ),
                array( 'status' => 403 )
            );

            self::$runtime_cache[ $cache_key ] = $error;
            wp_cache_set( $cache_key, $error, self::CACHE_GROUP, $this->get_cache_ttl() );

            return $error;
        }

        $rest_endpoints = $this->build_rest_endpoints();
        $instrumentation_payload = $this->build_instrumentation_payload( $rest_endpoints );

        $script_payloads = $this->build_script_payloads(
            $options,
            $rest_endpoints,
            $instrumentation_payload
        );

        $prepared = array(
            'instance_id'               => (int) $instance_id,
            'options'                   => $options,
            'normalize_context'         => $normalize_context,
            'requested'                 => array(
                'category' => $requested_category,
                'search'   => $requested_search,
                'sort'     => $requested_sort,
                'page'     => $requested_page,
                'filters'  => $requested_filters,
            ),
            'request_query_vars'        => $query_vars,
            'allows_requested_category' => (bool) $allows_requested_category,
            'rest'                      => $rest_endpoints,
            'instrumentation'           => $instrumentation_payload,
            'script_data'               => $script_payloads,
        );

        self::$runtime_cache[ $cache_key ] = $prepared;
        wp_cache_set( $cache_key, $prepared, self::CACHE_GROUP, $this->get_cache_ttl() );

        return $prepared;
    }

    /**
     * Build the REST endpoints metadata used on the frontend.
     *
     * @return array{
     *     root: string,
     *     nonce: string,
     *     filter: string,
     *     load_more: string,
     *     nonce_refresh: string,
     *     track: string
     * }
     */
    private function build_rest_endpoints() {
        $rest_nonce        = wp_create_nonce( 'wp_rest' );
        $rest_root         = esc_url_raw( rest_url() );
        $filter_endpoint   = esc_url_raw( rest_url( 'my-articles/v1/filter' ) );
        $load_more_endpoint = esc_url_raw( rest_url( 'my-articles/v1/load-more' ) );
        $nonce_endpoint    = esc_url_raw( rest_url( 'my-articles/v1/nonce' ) );
        $track_endpoint    = esc_url_raw( rest_url( 'my-articles/v1/track' ) );

        return array(
            'root'         => $rest_root,
            'nonce'        => $rest_nonce,
            'filter'       => $filter_endpoint,
            'load_more'    => $load_more_endpoint,
            'nonce_refresh'=> $nonce_endpoint,
            'track'        => $track_endpoint,
        );
    }

    /**
     * Build the instrumentation payload shared with frontend scripts.
     *
     * @param array $rest_endpoints REST endpoints metadata.
     * @return array
     */
    private function build_instrumentation_payload( array $rest_endpoints ) {
        $global_instrumentation = my_articles_get_instrumentation_settings();

        return array(
            'enabled' => ! empty( $global_instrumentation['enabled'] ),
            'channel' => $global_instrumentation['channel'],
            'fetchUrl' => $rest_endpoints['track'],
        );
    }

    /**
     * Assemble the script payloads that need to be registered.
     *
     * @param array $options                  Normalized options.
     * @param array $rest_endpoints           REST endpoints metadata.
     * @param array $instrumentation_payload  Instrumentation payload.
     * @return array<int, array{handle:string,object:string,data:array}>
     */
    private function build_script_payloads( array $options, array $rest_endpoints, array $instrumentation_payload ) {
        $payloads = array();

        if ( ! empty( $options['show_category_filter'] ) || ! empty( $options['enable_keyword_search'] ) ) {
            $payloads[] = array(
                'handle' => 'my-articles-filter',
                'object' => 'myArticlesFilter',
                'data'   => array(
                    'endpoint'      => $rest_endpoints['filter'],
                    'restRoot'      => $rest_endpoints['root'],
                    'restNonce'     => $rest_endpoints['nonce'],
                    'nonceEndpoint' => $rest_endpoints['nonce_refresh'],
                    'errorText'     => __( 'Erreur AJAX.', 'mon-articles' ),
                    'countSingle'   => __( '%s article affiché.', 'mon-articles' ),
                    'countPlural'   => __( '%s articles affichés.', 'mon-articles' ),
                    'countNone'     => __( 'Aucun article à afficher.', 'mon-articles' ),
                    'countPartialSingle' => __( 'Affichage de %1$s article sur %2$s.', 'mon-articles' ),
                    'countPartialPlural' => __( 'Affichage de %1$s articles sur %2$s.', 'mon-articles' ),
                    'instrumentation'    => $instrumentation_payload,
                ),
            );
        }

        if ( isset( $options['pagination_mode'] ) && 'load_more' === $options['pagination_mode'] ) {
            $payloads[] = array(
                'handle' => 'my-articles-load-more',
                'object' => 'myArticlesLoadMore',
                'data'   => array(
                    'endpoint'        => $rest_endpoints['load_more'],
                    'restRoot'        => $rest_endpoints['root'],
                    'restNonce'       => $rest_endpoints['nonce'],
                    'nonceEndpoint'   => $rest_endpoints['nonce_refresh'],
                    'loadingText'     => __( 'Chargement...', 'mon-articles' ),
                    'loadMoreText'    => esc_html__( 'Charger plus', 'mon-articles' ),
                    'errorText'       => __( 'Erreur AJAX.', 'mon-articles' ),
                    'totalSingle'     => __( '%s article affiché au total.', 'mon-articles' ),
                    'totalPlural'     => __( '%s articles affichés au total.', 'mon-articles' ),
                    'addedSingle'     => __( '%s article ajouté.', 'mon-articles' ),
                    'addedPlural'     => __( '%s articles ajoutés.', 'mon-articles' ),
                    'noAdditional'    => __( 'Aucun article supplémentaire.', 'mon-articles' ),
                    'none'            => __( 'Aucun article à afficher.', 'mon-articles' ),
                    'instrumentation' => $instrumentation_payload,
                ),
            );
        }

        return $payloads;
    }

    /**
     * Prepares the accessibility labels stored in the instance metadata.
     *
     * @param int   $instance_id Instance ID.
     * @param array $options_meta Raw options metadata.
     * @return array
     */
    private function prepare_accessibility_labels( $instance_id, array $options_meta ) {
        $default_aria_label = trim( wp_strip_all_tags( get_the_title( $instance_id ) ) );
        if ( '' === $default_aria_label ) {
            /* translators: %d: module (post) ID. */
            $default_aria_label = sprintf( __( "Module d'articles %d", 'mon-articles' ), $instance_id );
        }

        $resolved_aria_label = '';
        if ( isset( $options_meta['aria_label'] ) && is_string( $options_meta['aria_label'] ) ) {
            $resolved_aria_label = trim( sanitize_text_field( $options_meta['aria_label'] ) );
        }

        if ( '' === $resolved_aria_label ) {
            $resolved_aria_label = sanitize_text_field( $default_aria_label );
        }

        $options_meta['aria_label'] = $resolved_aria_label;

        /* translators: %s: module accessible label. */
        $default_filter_aria_label = sprintf( __( 'Filtre des catégories pour %s', 'mon-articles' ), $resolved_aria_label );

        $resolved_filter_aria_label = '';
        if ( isset( $options_meta['category_filter_aria_label'] ) && is_string( $options_meta['category_filter_aria_label'] ) ) {
            $resolved_filter_aria_label = trim( sanitize_text_field( $options_meta['category_filter_aria_label'] ) );
        }

        if ( '' === $resolved_filter_aria_label ) {
            $resolved_filter_aria_label = $default_filter_aria_label;
        }

        $options_meta['category_filter_aria_label'] = $resolved_filter_aria_label;

        return $options_meta;
    }

    /**
     * Normalizes the filter categories stored in the metadata.
     *
     * @param array $options_meta Instance metadata.
     * @return array{has_filter_categories:bool,normalized:array}
     */
    private function resolve_filter_categories( array $options_meta ) {
        $normalized_filter_categories = array();
        $has_filter_categories        = false;

        if ( isset( $options_meta['filter_categories'] ) ) {
            $raw_filter_categories = $options_meta['filter_categories'];

            if ( is_string( $raw_filter_categories ) ) {
                $raw_filter_categories = explode( ',', $raw_filter_categories );
            }

            if ( is_array( $raw_filter_categories ) ) {
                $normalized_filter_categories = array_values( array_filter( array_map( 'absint', $raw_filter_categories ) ) );
                $has_filter_categories        = ! empty( $normalized_filter_categories );
            }
        }

        return array(
            'has_filter_categories' => $has_filter_categories,
            'normalized'            => $normalized_filter_categories,
        );
    }

    /**
     * Sanitize the request payload.
     *
     * @param array|null $request Raw request payload.
     * @return array
     */
    private function sanitize_request_payload( $request ) {
        if ( is_array( $request ) ) {
            return $request;
        }

        return array_map( 'wp_unslash', $_GET );
    }

    /**
     * Extract a scalar value from the request payload and sanitize it.
     *
     * @param array         $request   Request payload.
     * @param string        $key       Array key to read from the request.
     * @param callable|null $sanitizer Sanitizing callback.
     * @return string
     */
    private function read_request_value( array $request, $key, $sanitizer = null ) {
        if ( ! isset( $request[ $key ] ) ) {
            return '';
        }

        $value = $request[ $key ];

        if ( is_array( $value ) ) {
            foreach ( $value as $candidate ) {
                if ( is_scalar( $candidate ) ) {
                    $value = $candidate;
                    break;
                }
            }
        }

        if ( ! is_scalar( $value ) ) {
            return '';
        }

        $value = (string) $value;

        if ( null === $sanitizer ) {
            return $value;
        }

        if ( is_callable( $sanitizer ) ) {
            $value = call_user_func( $sanitizer, $value );
        }

        return (string) $value;
    }

    /**
     * Sanitizes a category value coming from contextual overrides.
     *
     * @param mixed $value Raw contextual value.
     * @return string
     */
    private function maybe_sanitize_category_value( $value ) {
        if ( is_scalar( $value ) ) {
            return sanitize_title( (string) $value );
        }

        return '';
    }

    /**
     * Sanitizes a search term coming from contextual overrides.
     *
     * @param mixed $value Raw contextual value.
     * @return string
     */
    private function maybe_sanitize_search_value( $value ) {
        if ( is_scalar( $value ) ) {
            return sanitize_text_field( (string) $value );
        }

        return '';
    }

    /**
     * Ensures the sort parameter matches the whitelist of allowed values.
     *
     * @param string $value Raw sort value.
     * @return string
     */
    public function sanitize_sort_value( $value ) {
        $allowed_sort_values = array( 'date', 'title', 'menu_order', 'meta_value', 'comment_count', 'post__in' );

        if ( in_array( $value, $allowed_sort_values, true ) ) {
            return $value;
        }

        return '';
    }

    /**
     * Normalizes the requested page number ensuring it is always positive.
     *
     * @param string $value Raw page value extracted from the request.
     * @return int
     */
    private function normalize_requested_page( $value ) {
        if ( '' === $value ) {
            return 1;
        }

        $page = absint( $value );

        if ( $page < 1 ) {
            return 1;
        }

        return $page;
    }

    /**
     * Sanitizes requested taxonomy filters coming from contextual overrides.
     *
     * @param mixed $raw_filters Raw filters payload.
     * @param array $options_meta Instance metadata.
     * @return array<int, array{taxonomy:string,slug:string}>
     */
    private function sanitize_requested_filters( $raw_filters, array $options_meta ) {
        if ( is_string( $raw_filters ) && '' === trim( $raw_filters ) ) {
            return array();
        }

        $post_type = '';

        if ( isset( $options_meta['post_type'] ) && is_scalar( $options_meta['post_type'] ) ) {
            $post_type = (string) $options_meta['post_type'];
        }

        if ( '' === $post_type ) {
            $defaults = My_Articles_Shortcode::get_default_options();
            if ( isset( $defaults['post_type'] ) ) {
                $post_type = (string) $defaults['post_type'];
            }
        }

        return My_Articles_Shortcode::sanitize_filter_pairs( $raw_filters, $post_type );
    }

    /**
     * Builds a deterministic cache key for the prepared payload.
     *
     * @param int   $instance_id Instance identifier.
     * @param array $options_meta Normalized options metadata (with overrides).
     * @param array $requested    Requested parameters subset.
     * @return string
     */
    private function build_cache_key( $instance_id, array $options_meta, array $requested ) {
        $signature = array(
            'namespace' => $this->get_cache_namespace(),
            'id'        => (int) $instance_id,
            'meta'      => $this->normalize_signature_value( $options_meta ),
            'requested' => $this->normalize_signature_value( $requested ),
        );

        $encoded = wp_json_encode( $signature );
        if ( false === $encoded ) {
            $encoded = maybe_serialize( $signature );
        }

        return 'prep_' . md5( $encoded );
    }

    /**
     * Normalizes a value for cache signature generation.
     *
     * @param mixed $value Raw value.
     * @return mixed
     */
    private function normalize_signature_value( $value ) {
        if ( is_scalar( $value ) || null === $value ) {
            return $value;
        }

        if ( $value instanceof WP_Error ) {
            return $this->normalize_signature_value( $value->get_error_data() );
        }

        if ( is_object( $value ) ) {
            $value = get_object_vars( $value );
        }

        if ( is_array( $value ) ) {
            $normalized = array();

            foreach ( $value as $key => $item ) {
                $normalized[ (string) $key ] = $this->normalize_signature_value( $item );
            }

            ksort( $normalized );

            return $normalized;
        }

        return (string) $value;
    }

    /**
     * Retrieve the cache namespace ensuring it is always a safe, non-empty string.
     *
     * @return string
     */
    private function get_cache_namespace() {
        $namespace = '';

        if ( function_exists( 'get_option' ) ) {
            $namespace = (string) get_option( 'my_articles_cache_namespace', '' );
        }

        $namespace = sanitize_key( $namespace );

        if ( '' === $namespace ) {
            $namespace = 'default';
        }

        /**
         * Filters the namespace used for the shortcode preparation cache.
         *
         * This allows third-party integrations to align the namespace with
         * custom invalidation strategies.
         *
         * @param string $namespace Normalized namespace value.
         */
        $namespace = apply_filters( 'my_articles_shortcode_cache_namespace', $namespace );

        if ( ! is_string( $namespace ) ) {
            $namespace = 'default';
        }

        $namespace = sanitize_key( $namespace );

        if ( '' === $namespace ) {
            $namespace = 'default';
        }

        return $namespace;
    }

    /**
     * Cache TTL (in seconds) for prepared payloads.
     *
     * @return int
     */
    private function get_cache_ttl() {
        $default_ttl = MINUTE_IN_SECONDS * 5;

        /**
         * Filters the cache TTL applied to shortcode preparation payloads.
         *
         * @param int $default_ttl Default cache duration (in seconds).
         */
        return (int) apply_filters( 'my_articles_shortcode_prepare_cache_ttl', $default_ttl );
    }

    /**
     * Clears runtime caches. Useful when invalidating during the same request.
     */
    public static function reset_runtime_cache() {
        self::$runtime_cache = array();
    }
}
