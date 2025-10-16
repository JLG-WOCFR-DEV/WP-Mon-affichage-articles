<?php
if ( ! defined( 'WPINC' ) ) {
    die;
}

class My_Articles_Display_State_Builder {
    const CACHE_GROUP = 'my_articles_display_state';

    /**
     * Runtime cache for computed states within a single request.
     *
     * @var array<string, array<string, mixed>>
     */
    private static $runtime_cache = array();

    /**
     * @var My_Articles_Shortcode
     */
    private $shortcode;

    /**
     * @var array<string, mixed>
     */
    private $options;

    /**
     * @var array<string, mixed>
     */
    private $args;

    /**
     * @var array<string, mixed>|null
     */
    private $state;

    /**
     * @param My_Articles_Shortcode $shortcode Shortcode service.
     * @param array<string, mixed>  $options   Normalized options for the instance.
     * @param array<string, mixed>  $args      Optional. Runtime arguments controlling pagination and overrides.
     */
    public function __construct( My_Articles_Shortcode $shortcode, array $options, array $args = array() ) {
        $defaults = array(
            'paged'                   => 1,
            'pagination_strategy'     => 'page',
            'seen_pinned_ids'         => array(),
            'enforce_unlimited_batch' => false,
            'force_refresh'           => false,
        );

        $this->shortcode = $shortcode;
        $this->options   = $options;
        $this->args      = wp_parse_args( $args, $defaults );
        $this->args['paged'] = max( 1, (int) $this->args['paged'] );
    }

    /**
     * Build the full display state required to render an instance.
     *
     * @return array<string, mixed>
     */
    public function build() {
        if ( is_array( $this->state ) ) {
            return $this->state;
        }

        $precomputed = apply_filters( 'my_articles_display_state_precomputed', null, $this->options, $this->args );
        if ( is_array( $precomputed ) ) {
            $this->state = $this->finalize_state( $precomputed );
            return $this->state;
        }

        $cache_key = $this->get_cache_key();
        if ( ! empty( $cache_key ) && empty( $this->args['force_refresh'] ) ) {
            if ( isset( self::$runtime_cache[ $cache_key ] ) ) {
                $this->state = self::$runtime_cache[ $cache_key ];
                return $this->state;
            }

            $cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
            if ( false !== $cached && is_array( $cached ) ) {
                $this->state = $this->hydrate_state_from_cache( $cached );
                if ( is_array( $this->state ) ) {
                    self::$runtime_cache[ $cache_key ] = $this->state;
                    return $this->state;
                }
            }
        }

        $external_results = apply_filters( 'my_articles_display_state_external_results', null, $this->options, $this->args );
        if ( is_array( $external_results ) ) {
            $this->state = $this->finalize_state( $external_results );
        } else {
            $this->state = $this->compute_state();
        }

        if ( ! empty( $cache_key ) && is_array( $this->state ) ) {
            self::$runtime_cache[ $cache_key ] = $this->state;
            $payload = $this->convert_state_for_cache( $this->state );
            if ( is_array( $payload ) ) {
                wp_cache_set( $cache_key, $payload, self::CACHE_GROUP, $this->get_cache_ttl() );
            }
        }

        return $this->state;
    }

    /**
     * Retrieve the identifiers of the pinned posts rendered for the current request.
     *
     * @return array<int, int>
     */
    public function get_pinned_post_ids() {
        $state = $this->build();

        return isset( $state['rendered_pinned_ids'] ) && is_array( $state['rendered_pinned_ids'] )
            ? array_map( 'absint', $state['rendered_pinned_ids'] )
            : array();
    }

    /**
     * Retrieve the main query (non pinned posts) for the current request.
     *
     * @return WP_Query|null
     */
    public function get_regular_query() {
        $state = $this->build();

        return isset( $state['regular_query'] ) && $state['regular_query'] instanceof WP_Query
            ? $state['regular_query']
            : null;
    }

    /**
     * Retrieve the pagination summary for the current request.
     *
     * @return array<string, mixed>
     */
    public function get_pagination_summary() {
        $state = $this->build();

        $keys = array(
            'should_limit_display',
            'render_limit',
            'regular_posts_needed',
            'total_pinned_posts',
            'total_regular_posts',
            'effective_posts_per_page',
            'is_unlimited',
            'updated_seen_pinned_ids',
            'unlimited_batch_size',
            'should_enforce_unlimited',
        );

        $summary = array();
        foreach ( $keys as $key ) {
            if ( array_key_exists( $key, $state ) ) {
                $summary[ $key ] = $state[ $key ];
            }
        }

        return $summary;
    }

    /**
     * Reset runtime caches. Useful when invalidating during the same request.
     */
    public static function reset_runtime_cache() {
        self::$runtime_cache = array();
    }

    /**
     * Compute the display state using the default WordPress data sources.
     *
     * @return array<string, mixed>
     */
    private function compute_state() {
        $options = $this->options;
        $args    = $this->args;

        $paged        = $args['paged'];
        $posts_per_page = isset( $options['posts_per_page'] ) ? (int) $options['posts_per_page'] : 0;
        $is_unlimited   = ! empty( $options['is_unlimited'] );
        $batch_cap      = isset( $options['unlimited_query_cap'] ) ? (int) $options['unlimited_query_cap'] : 0;

        if ( $batch_cap <= 0 ) {
            $batch_cap = (int) apply_filters( 'my_articles_unlimited_batch_size', 50, $options, $args );
            $batch_cap = max( 1, $batch_cap );
        }

        $should_enforce_unlimited = ! empty( $args['enforce_unlimited_batch'] );
        if ( 'slideshow' === ( $options['display_mode'] ?? '' ) ) {
            $should_enforce_unlimited = true;
        }

        if ( $is_unlimited ) {
            if ( $should_enforce_unlimited ) {
                $effective_limit         = $batch_cap;
                $effective_posts_per_page = $batch_cap;
                $should_limit_display    = true;
            } else {
                $effective_limit         = -1;
                $effective_posts_per_page = 0;
                $should_limit_display    = false;
            }
        } else {
            $effective_limit         = max( 0, $posts_per_page );
            $effective_posts_per_page = $effective_limit;
            $should_limit_display    = $effective_limit > 0;
        }

        $matching_pinned_ids = $this->resolve_pinned_ids( $options, $args );
        $total_matching_pinned = count( $matching_pinned_ids );

        $pinned_query         = null;
        $regular_query        = null;
        $rendered_pinned_ids  = array();
        $updated_seen_pinned  = array();
        $regular_posts_needed = -1;
        $regular_posts_limit  = -1;
        $regular_offset       = 0;
        $max_items_before_current_page = 0;

        if ( $effective_limit > 0 ) {
            $max_items_before_current_page = max( 0, ( $paged - 1 ) * $effective_limit );
        }

        if ( 'sequential' === $args['pagination_strategy'] ) {
            $seen_pinned_ids = array_map( 'absint', (array) $args['seen_pinned_ids'] );
            $seen_pinned_ids = array_values( array_intersect( $matching_pinned_ids, $seen_pinned_ids ) );

            if ( $effective_limit > 0 ) {
                $remaining_pinned_ids   = array_slice( $matching_pinned_ids, count( $seen_pinned_ids ) );
                $pinned_ids_for_request = array_slice( $remaining_pinned_ids, 0, $effective_limit );
            } else {
                $pinned_ids_for_request = array_values( array_diff( $matching_pinned_ids, $seen_pinned_ids ) );
            }

            if ( ! empty( $pinned_ids_for_request ) ) {
                $pinned_query_args = array(
                    'post_type'      => $options['post_type'],
                    'post_status'    => 'publish',
                    'post__in'       => $pinned_ids_for_request,
                    'orderby'        => 'post__in',
                    'posts_per_page' => count( $pinned_ids_for_request ),
                    'no_found_rows'  => true,
                );

                if ( ! empty( $options['meta_query'] ) && is_array( $options['meta_query'] ) ) {
                    $pinned_query_args = My_Articles_Shortcode::merge_meta_query_clauses( $pinned_query_args, $options['meta_query'] );
                }

                $pinned_query = new WP_Query( $pinned_query_args );

                if ( $pinned_query->have_posts() ) {
                    $rendered_pinned_ids = array_map( 'absint', wp_list_pluck( $pinned_query->posts, 'ID' ) );
                }
            }

            if ( empty( $rendered_pinned_ids ) && ! empty( $pinned_ids_for_request ) ) {
                $rendered_pinned_ids = array_map( 'absint', $pinned_ids_for_request );
            }

            $updated_seen_pinned = array_values( array_unique( array_merge( $seen_pinned_ids, $rendered_pinned_ids ) ) );

            if ( $effective_limit > 0 ) {
                $regular_posts_already_displayed = max( 0, $max_items_before_current_page - count( $seen_pinned_ids ) );
                $regular_posts_limit             = max( 0, $effective_limit - count( $rendered_pinned_ids ) );
                $regular_offset                  = $regular_posts_already_displayed;
                $regular_posts_needed            = $regular_posts_limit;
            }

            $regular_excluded_ids = array_unique(
                array_merge(
                    $updated_seen_pinned,
                    isset( $options['exclude_post_ids'] ) ? (array) $options['exclude_post_ids'] : array(),
                    $matching_pinned_ids
                )
            );

            if ( $effective_limit > 0 ) {
                if ( $regular_posts_limit > 0 ) {
                    $regular_query = My_Articles_Shortcode::build_regular_query(
                        $options,
                        array(
                            'posts_per_page' => $regular_posts_limit,
                            'post__not_in'   => $regular_excluded_ids,
                            'offset'         => $regular_offset,
                        ),
                        $options['term'] ?? ''
                    );
                }
            } else {
                $regular_query = My_Articles_Shortcode::build_regular_query(
                    $options,
                    array(
                        'posts_per_page' => -1,
                        'post__not_in'   => $regular_excluded_ids,
                    ),
                    $options['term'] ?? ''
                );
            }
        } else {
            if ( $effective_limit > 0 ) {
                $pinned_offset          = min( $total_matching_pinned, $max_items_before_current_page );
                $pinned_ids_for_request = array_slice( $matching_pinned_ids, $pinned_offset, $effective_limit );
                $regular_offset         = max( 0, $max_items_before_current_page - $pinned_offset );
            } else {
                $pinned_ids_for_request = $matching_pinned_ids;
                $regular_offset         = 0;
            }

            if ( ! empty( $pinned_ids_for_request ) ) {
                $pinned_query_args = array(
                    'post_type'      => $options['post_type'],
                    'post_status'    => 'publish',
                    'post__in'       => $pinned_ids_for_request,
                    'orderby'        => 'post__in',
                    'posts_per_page' => count( $pinned_ids_for_request ),
                    'no_found_rows'  => true,
                    'post__not_in'   => isset( $options['exclude_post_ids'] ) ? (array) $options['exclude_post_ids'] : array(),
                );

                if ( ! empty( $options['meta_query'] ) && is_array( $options['meta_query'] ) ) {
                    $pinned_query_args = My_Articles_Shortcode::merge_meta_query_clauses( $pinned_query_args, $options['meta_query'] );
                }

                $pinned_query = new WP_Query( $pinned_query_args );

                if ( $pinned_query->have_posts() ) {
                    $rendered_pinned_ids = array_map( 'absint', wp_list_pluck( $pinned_query->posts, 'ID' ) );
                }
            }

            if ( empty( $rendered_pinned_ids ) && ! empty( $pinned_ids_for_request ) ) {
                $rendered_pinned_ids = array_map( 'absint', $pinned_ids_for_request );
            }

            if ( $effective_limit > 0 ) {
                $projected_pinned_display = min( $effective_limit, count( $rendered_pinned_ids ) );
                if ( empty( $rendered_pinned_ids ) && ! empty( $pinned_ids_for_request ) ) {
                    $projected_pinned_display = min( $effective_limit, count( $pinned_ids_for_request ) );
                }

                $regular_posts_needed = max( 0, $effective_limit - $projected_pinned_display );
                $regular_posts_limit  = $regular_posts_needed;
            }

            if ( $effective_limit > 0 ) {
                if ( $regular_posts_limit > 0 ) {
                    $regular_query = My_Articles_Shortcode::build_regular_query(
                        $options,
                        array(
                            'posts_per_page' => $regular_posts_limit,
                            'offset'         => $regular_offset,
                        ),
                        $options['term'] ?? ''
                    );
                }
            } else {
                $regular_query = My_Articles_Shortcode::build_regular_query(
                    $options,
                    array(
                        'posts_per_page' => -1,
                    ),
                    $options['term'] ?? ''
                );
            }
        }

        $total_regular_posts = (int) ( $regular_query instanceof WP_Query ? $regular_query->found_posts : 0 );

        $state = array(
            'pinned_query'                => $pinned_query,
            'regular_query'               => $regular_query,
            'rendered_pinned_ids'         => $rendered_pinned_ids,
            'should_limit_display'        => $should_limit_display,
            'render_limit'                => $effective_limit > 0 ? $effective_limit : 0,
            'regular_posts_needed'        => $regular_posts_needed,
            'total_pinned_posts'          => $total_matching_pinned,
            'total_regular_posts'         => $total_regular_posts,
            'effective_posts_per_page'    => $effective_posts_per_page,
            'is_unlimited'                => $is_unlimited,
            'updated_seen_pinned_ids'     => $updated_seen_pinned,
            'unlimited_batch_size'        => $batch_cap,
            'should_enforce_unlimited'    => (bool) $should_enforce_unlimited,
            'meta_query'                  => isset( $options['meta_query'] ) && is_array( $options['meta_query'] )
                ? $options['meta_query']
                : array(),
            'meta_query_relation'         => isset( $options['meta_query_relation'] ) && is_string( $options['meta_query_relation'] )
                ? $options['meta_query_relation']
                : 'AND',
            'content_adapters'            => isset( $options['content_adapters'] ) && is_array( $options['content_adapters'] )
                ? $options['content_adapters']
                : array(),
        );

        /**
         * Filters the computed display state prior to caching.
         *
         * @param array<string, mixed> $state   Computed display state.
         * @param array<string, mixed> $options Instance options.
         * @param array<string, mixed> $args    Runtime arguments.
         */
        $state = apply_filters( 'my_articles_display_state_computed', $state, $options, $args );

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function finalize_state( array $state ) {
        $defaults = array(
            'pinned_query'             => null,
            'regular_query'            => null,
            'rendered_pinned_ids'      => array(),
            'should_limit_display'     => false,
            'render_limit'             => 0,
            'regular_posts_needed'     => -1,
            'total_pinned_posts'       => 0,
            'total_regular_posts'      => 0,
            'effective_posts_per_page' => 0,
            'is_unlimited'             => false,
            'updated_seen_pinned_ids'  => array(),
            'unlimited_batch_size'     => 0,
            'should_enforce_unlimited' => false,
            'meta_query'               => array(),
            'meta_query_relation'      => 'AND',
            'content_adapters'         => array(),
        );

        $normalized = array_merge( $defaults, $state );

        if ( isset( $normalized['rendered_pinned_ids'] ) && is_array( $normalized['rendered_pinned_ids'] ) ) {
            $normalized['rendered_pinned_ids'] = array_map( 'absint', $normalized['rendered_pinned_ids'] );
        } else {
            $normalized['rendered_pinned_ids'] = array();
        }

        if ( ! isset( $normalized['pinned_query'] ) || ! ( $normalized['pinned_query'] instanceof WP_Query ) ) {
            $normalized['pinned_query'] = null;
        }

        if ( ! isset( $normalized['regular_query'] ) || ! ( $normalized['regular_query'] instanceof WP_Query ) ) {
            $normalized['regular_query'] = null;
        }

        return $normalized;
    }

    /**
     * Resolve the matching pinned IDs applying filters.
     *
     * @param array<string, mixed> $options
     * @param array<string, mixed> $args
     *
     * @return array<int, int>
     */
    private function resolve_pinned_ids( array $options, array $args ) {
        $default_ids = My_Articles_Shortcode::get_matching_pinned_ids( $options );
        $filtered    = apply_filters( 'my_articles_display_state_pinned_ids', $default_ids, $options, $args );

        if ( ! is_array( $filtered ) ) {
            $filtered = $default_ids;
        }

        $filtered = array_values( array_map( 'absint', $filtered ) );
        $filtered = array_filter( $filtered );

        return array_values( array_unique( $filtered ) );
    }

    /**
     * Convert a computed state to a cache payload safe for persistence.
     *
     * @param array<string, mixed> $state
     *
     * @return array<string, mixed>
     */
    private function convert_state_for_cache( array $state ) {
        $payload = array(
            'summary'       => array(),
            'pinned_query'  => null,
            'regular_query' => null,
        );

        $summary_keys = array(
            'rendered_pinned_ids',
            'should_limit_display',
            'render_limit',
            'regular_posts_needed',
            'total_pinned_posts',
            'total_regular_posts',
            'effective_posts_per_page',
            'is_unlimited',
            'updated_seen_pinned_ids',
            'unlimited_batch_size',
            'should_enforce_unlimited',
            'meta_query',
            'meta_query_relation',
            'content_adapters',
        );

        foreach ( $summary_keys as $key ) {
            if ( array_key_exists( $key, $state ) ) {
                $payload['summary'][ $key ] = $state[ $key ];
            }
        }

        if ( $state['pinned_query'] instanceof WP_Query ) {
            $payload['pinned_query'] = $this->normalize_query_for_cache( $state['pinned_query'] );
        }

        if ( $state['regular_query'] instanceof WP_Query ) {
            $payload['regular_query'] = $this->normalize_query_for_cache( $state['regular_query'] );
        }

        /**
         * Filters the payload stored in persistent caches.
         *
         * @param array<string, mixed> $payload Cache payload.
         * @param array<string, mixed> $state   Computed state.
         * @param array<string, mixed> $options Instance options.
         * @param array<string, mixed> $args    Runtime arguments.
         */
        $payload = apply_filters( 'my_articles_display_state_cache_payload', $payload, $state, $this->options, $this->args );

        return $payload;
    }

    /**
     * Normalize a WP_Query object for cache storage.
     *
     * @param WP_Query $query Query instance.
     *
     * @return array<string, mixed>
     */
    private function normalize_query_for_cache( WP_Query $query ) {
        $posts = array();
        if ( isset( $query->posts ) && is_array( $query->posts ) ) {
            foreach ( $query->posts as $post ) {
                if ( $post instanceof WP_Post ) {
                    $posts[] = $post->to_array();
                } elseif ( is_object( $post ) ) {
                    $posts[] = get_object_vars( $post );
                } elseif ( is_array( $post ) ) {
                    $posts[] = $post;
                }
            }
        }

        return array(
            'posts'         => $posts,
            'found_posts'   => isset( $query->found_posts ) ? (int) $query->found_posts : count( $posts ),
            'post_count'    => isset( $query->post_count ) ? (int) $query->post_count : count( $posts ),
            'max_num_pages' => isset( $query->max_num_pages ) ? (int) $query->max_num_pages : 0,
        );
    }

    /**
     * Hydrate a cached payload into a full state array.
     *
     * @param array<string, mixed> $payload Cache payload.
     *
     * @return array<string, mixed>|null
     */
    private function hydrate_state_from_cache( array $payload ) {
        if ( empty( $payload['summary'] ) || ! is_array( $payload['summary'] ) ) {
            return null;
        }

        $state = array_merge( array(
            'rendered_pinned_ids'      => array(),
            'should_limit_display'     => false,
            'render_limit'             => 0,
            'regular_posts_needed'     => -1,
            'total_pinned_posts'       => 0,
            'total_regular_posts'      => 0,
            'effective_posts_per_page' => 0,
            'is_unlimited'             => false,
            'updated_seen_pinned_ids'  => array(),
            'unlimited_batch_size'     => 0,
            'should_enforce_unlimited' => false,
            'meta_query'               => array(),
            'meta_query_relation'      => 'AND',
            'content_adapters'         => array(),
        ), $payload['summary'] );

        if ( isset( $payload['pinned_query'] ) && is_array( $payload['pinned_query'] ) ) {
            $state['pinned_query'] = $this->hydrate_query_from_cache( $payload['pinned_query'] );
        } else {
            $state['pinned_query'] = null;
        }

        if ( isset( $payload['regular_query'] ) && is_array( $payload['regular_query'] ) ) {
            $state['regular_query'] = $this->hydrate_query_from_cache( $payload['regular_query'] );
        } else {
            $state['regular_query'] = null;
        }

        return $state;
    }

    /**
     * Rehydrate a cached query payload into a WP_Query instance.
     *
     * @param array<string, mixed> $payload
     *
     * @return WP_Query|null
     */
    private function hydrate_query_from_cache( array $payload ) {
        if ( empty( $payload['posts'] ) || ! is_array( $payload['posts'] ) ) {
            return null;
        }

        $posts = array();
        foreach ( $payload['posts'] as $post_data ) {
            if ( $post_data instanceof WP_Post ) {
                $posts[] = $post_data;
            } elseif ( is_array( $post_data ) ) {
                if ( class_exists( 'WP_Post' ) ) {
                    $posts[] = new WP_Post( (object) $post_data );
                } else {
                    $posts[] = (object) $post_data;
                }
            } elseif ( is_object( $post_data ) ) {
                $posts[] = $post_data;
            }
        }

        if ( empty( $posts ) ) {
            return null;
        }

        if ( method_exists( 'WP_Query', 'rewind_posts' ) ) {
            $query = new WP_Query();
            $query->posts = $posts;
            $query->post_count = count( $posts );
            $query->found_posts = isset( $payload['found_posts'] ) ? (int) $payload['found_posts'] : $query->post_count;
            $query->max_num_pages = isset( $payload['max_num_pages'] ) ? (int) $payload['max_num_pages'] : 0;
            $query->rewind_posts();
            return $query;
        }

        return new WP_Query( $posts );
    }

    /**
     * Build a deterministic cache key for the current context.
     *
     * @return string
     */
    private function get_cache_key() {
        $instance_id = isset( $this->options['instance_id'] ) ? (int) $this->options['instance_id'] : 0;
        if ( $instance_id <= 0 ) {
            return '';
        }

        $signature = array(
            'instance'  => $instance_id,
            'page'      => $this->args['paged'],
            'strategy'  => $this->args['pagination_strategy'],
            'filters'   => $this->collect_filter_signature(),
        );

        if ( 'sequential' === $this->args['pagination_strategy'] ) {
            $signature['seen'] = array_map( 'absint', (array) $this->args['seen_pinned_ids'] );
        }

        /**
         * Filters the signature used to generate the cache key.
         *
         * @param array<string, mixed> $signature Cache signature parts.
         * @param array<string, mixed> $options   Instance options.
         * @param array<string, mixed> $args      Runtime arguments.
         */
        $signature = apply_filters( 'my_articles_display_state_cache_signature', $signature, $this->options, $this->args );

        $namespace = $this->get_cache_namespace();
        if ( '' === $namespace ) {
            return '';
        }

        $encoded = wp_json_encode( $signature );
        if ( false === $encoded ) {
            $encoded = maybe_serialize( $signature );
        }

        return $namespace . '_state_' . md5( (string) $encoded );
    }

    /**
     * Collect the filter context that affects the display state.
     *
     * @return array<string, mixed>
     */
    private function collect_filter_signature() {
        $options = $this->options;

        $filters = array(
            'term'                 => isset( $options['term'] ) ? (string) $options['term'] : '',
            'taxonomy'             => isset( $options['taxonomy'] ) ? (string) $options['taxonomy'] : '',
            'search'               => isset( $options['search_query'] ) ? (string) $options['search_query'] : '',
            'requested_filters'    => isset( $options['requested_filters'] ) ? $options['requested_filters'] : array(),
            'meta_query'           => isset( $options['meta_query'] ) ? $options['meta_query'] : array(),
            'meta_query_relation'  => isset( $options['meta_query_relation'] ) ? (string) $options['meta_query_relation'] : 'AND',
            'sort'                 => isset( $options['sort'] ) ? (string) $options['sort'] : '',
            'orderby'              => isset( $options['orderby'] ) ? (string) $options['orderby'] : '',
            'order'                => isset( $options['order'] ) ? (string) $options['order'] : '',
            'requested_category'   => isset( $options['requested_category'] ) ? (string) $options['requested_category'] : '',
            'requested_tax_filters'=> isset( $options['requested_filters'] ) ? $options['requested_filters'] : array(),
        );

        return $this->normalize_signature_value( $filters );
    }

    /**
     * Normalize values for cache signatures.
     *
     * @param mixed $value Raw value.
     *
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
     * Retrieve the namespace used for cached states.
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
         * Filters the namespace used to store display state caches.
         *
         * @param string               $namespace Current namespace.
         * @param array<string, mixed> $options   Instance options.
         * @param array<string, mixed> $args      Runtime arguments.
         */
        $namespace = apply_filters( 'my_articles_display_state_cache_namespace', $namespace, $this->options, $this->args );

        $namespace = sanitize_key( (string) $namespace );
        if ( '' === $namespace ) {
            $namespace = 'default';
        }

        return $namespace;
    }

    /**
     * Cache TTL in seconds.
     *
     * @return int
     */
    private function get_cache_ttl() {
        $default_ttl = MINUTE_IN_SECONDS * 5;

        /**
         * Filters the cache TTL applied to display state payloads.
         *
         * @param int                  $default_ttl Default duration in seconds.
         * @param array<string, mixed> $options     Instance options.
         * @param array<string, mixed> $args        Runtime arguments.
         */
        return (int) apply_filters( 'my_articles_display_state_cache_ttl', $default_ttl, $this->options, $this->args );
    }
}
