<?php
// Fichier: includes/class-my-articles-shortcode.php

if ( ! defined( 'WPINC' ) ) {
    die;
}

class My_Articles_Shortcode {

    private static $instance;
    private static $lazysizes_enqueued = false;
    private static $normalized_options_cache = array();
    private static $matching_pinned_ids_cache = array();

    private static function build_normalized_options_cache_key( $raw_options, $context ) {
        return md5( maybe_serialize( array( 'options' => $raw_options, 'context' => $context ) ) );
    }

    private static function build_matching_pinned_cache_key( array $options ) {
        $relevant = array(
            'post_type'                 => $options['post_type'] ?? '',
            'pinned_posts'              => $options['pinned_posts'] ?? array(),
            'pinned_posts_ignore_filter' => $options['pinned_posts_ignore_filter'] ?? 0,
            'resolved_taxonomy'         => $options['resolved_taxonomy'] ?? '',
            'term'                      => $options['term'] ?? '',
            'exclude_post_ids'          => $options['exclude_post_ids'] ?? array(),
        );

        return md5( maybe_serialize( $relevant ) );
    }

    private static function append_active_tax_query( array $args, $resolved_taxonomy, $active_category ) {
        if ( '' === $resolved_taxonomy || '' === $active_category || 'all' === $active_category ) {
            return $args;
        }

        $tax_query   = array();
        $tax_query[] = array(
            'taxonomy' => $resolved_taxonomy,
            'field'    => 'slug',
            'terms'    => $active_category,
        );

        if ( isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
            $tax_query = array_merge( $args['tax_query'], $tax_query );
        }

        $args['tax_query'] = $tax_query;

        return $args;
    }

    private static function build_regular_query( array $options, array $overrides = array(), $active_category = null ) {
        $base_args = array(
            'post_type'           => $options['post_type'],
            'post_status'         => 'publish',
            'ignore_sticky_posts' => isset( $options['ignore_native_sticky'] ) ? (int) $options['ignore_native_sticky'] : 0,
        );

        if ( ! isset( $overrides['post__not_in'] ) && isset( $options['all_excluded_ids'] ) ) {
            $base_args['post__not_in'] = $options['all_excluded_ids'];
        }

        $query_args = array_merge( $base_args, $overrides );

        $resolved_taxonomy = $options['resolved_taxonomy'] ?? '';
        $active_category   = null === $active_category ? ( $options['term'] ?? '' ) : $active_category;

        $query_args = self::append_active_tax_query( $query_args, $resolved_taxonomy, $active_category );

        return new WP_Query( $query_args );
    }

    public static function get_matching_pinned_ids( array $options ) {
        $cache_key = self::build_matching_pinned_cache_key( $options );

        if ( isset( self::$matching_pinned_ids_cache[ $cache_key ] ) ) {
            return self::$matching_pinned_ids_cache[ $cache_key ];
        }

        $pinned_ids = isset( $options['pinned_posts'] ) ? (array) $options['pinned_posts'] : array();

        if ( empty( $pinned_ids ) ) {
            self::$matching_pinned_ids_cache[ $cache_key ] = array();
            return array();
        }

        $query_args = array(
            'post_type'              => $options['post_type'],
            'post_status'            => 'publish',
            'post__in'               => $pinned_ids,
            'orderby'                => 'post__in',
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'post__not_in'           => isset( $options['exclude_post_ids'] ) ? (array) $options['exclude_post_ids'] : array(),
        );

        if ( empty( $options['pinned_posts_ignore_filter'] ) ) {
            $query_args = self::append_active_tax_query(
                $query_args,
                $options['resolved_taxonomy'] ?? '',
                $options['term'] ?? ''
            );
        }

        $pinned_query = new WP_Query( $query_args );

        $matching_ids = array();

        if ( $pinned_query instanceof WP_Query && ! empty( $pinned_query->posts ) ) {
            $matching_ids = array_values( array_filter( array_map( 'absint', $pinned_query->posts ) ) );
        }

        wp_reset_postdata();

        self::$matching_pinned_ids_cache[ $cache_key ] = $matching_ids;

        return $matching_ids;
    }

    /**
     * Retrieves the list of allowed post statuses for a shortcode instance.
     *
     * @param int $instance_id The instance post ID.
     *
     * @return array<int, string> List of allowed statuses.
     */
    public static function get_allowed_instance_statuses( $instance_id = 0 ) {
        $default_statuses = array( 'publish' );
        $allowed_statuses = apply_filters( 'my_articles_allowed_instance_statuses', $default_statuses, $instance_id );

        if ( ! is_array( $allowed_statuses ) ) {
            $allowed_statuses = array( $allowed_statuses );
        }

        $normalized_statuses = array();

        foreach ( $allowed_statuses as $status ) {
            if ( is_string( $status ) && '' !== $status ) {
                $normalized_statuses[] = $status;
            }
        }

        if ( empty( $normalized_statuses ) ) {
            $normalized_statuses = $default_statuses;
        }

        return array_values( array_unique( $normalized_statuses ) );
    }

    public function build_display_state( array $options, array $args = array() ) {
        $defaults = array(
            'paged'                     => 1,
            'pagination_strategy'       => 'page',
            'seen_pinned_ids'           => array(),
            'enforce_unlimited_batch'   => false,
        );

        $args  = wp_parse_args( $args, $defaults );
        $paged = max( 1, (int) $args['paged'] );

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
                $effective_limit           = $batch_cap;
                $effective_posts_per_page  = $batch_cap;
                $should_limit_display      = true;
            } else {
                $effective_limit           = -1;
                $effective_posts_per_page  = 0;
                $should_limit_display      = false;
            }
        } else {
            $effective_limit          = max( 0, $posts_per_page );
            $effective_posts_per_page = $effective_limit;
            $should_limit_display     = $effective_limit > 0;
        }

        $matching_pinned_ids = self::get_matching_pinned_ids( $options );
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
                $remaining_pinned_ids = array_slice( $matching_pinned_ids, count( $seen_pinned_ids ) );
                $pinned_ids_for_request = array_slice( $remaining_pinned_ids, 0, $effective_limit );
            } else {
                $pinned_ids_for_request = array_values( array_diff( $matching_pinned_ids, $seen_pinned_ids ) );
            }

            if ( ! empty( $pinned_ids_for_request ) ) {
                $pinned_query = new WP_Query(
                    array(
                        'post_type'      => $options['post_type'],
                        'post_status'    => 'publish',
                        'post__in'       => $pinned_ids_for_request,
                        'orderby'        => 'post__in',
                        'posts_per_page' => count( $pinned_ids_for_request ),
                        'no_found_rows'  => true,
                    )
                );

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
                    $regular_query = self::build_regular_query(
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
                $regular_query = self::build_regular_query(
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
                $pinned_query = new WP_Query(
                    array(
                        'post_type'      => $options['post_type'],
                        'post_status'    => 'publish',
                        'post__in'       => $pinned_ids_for_request,
                        'orderby'        => 'post__in',
                        'posts_per_page' => count( $pinned_ids_for_request ),
                        'no_found_rows'  => true,
                        'post__not_in'   => isset( $options['exclude_post_ids'] ) ? (array) $options['exclude_post_ids'] : array(),
                    )
                );

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
                    $regular_query = self::build_regular_query(
                        $options,
                        array(
                            'posts_per_page' => $regular_posts_limit,
                            'offset'         => $regular_offset,
                        ),
                        $options['term'] ?? ''
                    );
                }
            } else {
                $regular_query = self::build_regular_query(
                    $options,
                    array(
                        'posts_per_page' => -1,
                    ),
                    $options['term'] ?? ''
                );
            }
        }

        $total_regular_posts = (int) ( $regular_query instanceof WP_Query ? $regular_query->found_posts : 0 );

        return array(
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
        );
    }

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'mon_affichage_articles', array( $this, 'render_shortcode' ) );
    }

    public static function get_default_options() {
        static $cached_defaults = null;

        if ( null !== $cached_defaults ) {
            return $cached_defaults;
        }

        $defaults = [
            'post_type' => 'post',
            'taxonomy' => '',
            'term' => '',
            'counting_behavior' => 'exact',
            'posts_per_page' => 10,
            'pagination_mode' => 'none',
            'show_category_filter' => 0,
            'filter_alignment' => 'right',
            'filter_categories' => array(),
            'pinned_posts' => array(),
            'pinned_border_color' => '#eab308',
            'pinned_posts_ignore_filter' => 0,
            'pinned_show_badge' => 0,
            'pinned_badge_text' => 'Épinglé',
            'pinned_badge_bg_color' => '#eab308',
            'pinned_badge_text_color' => '#ffffff',
            'exclude_posts' => '',
            'ignore_native_sticky' => 1,
            'enable_lazy_load' => 1,
            'enable_debug_mode' => 0,
            'display_mode' => 'grid',
            'columns_mobile' => 1, 'columns_tablet' => 2, 'columns_desktop' => 3, 'columns_ultrawide' => 4,
            'module_padding_left' => 0, 'module_padding_right' => 0,
            'gap_size' => 25, 'list_item_gap' => 25,
            'list_content_padding_top' => 0, 'list_content_padding_right' => 0,
            'list_content_padding_bottom' => 0, 'list_content_padding_left' => 0,
            'border_radius' => 12, 'title_font_size' => 16,
            'meta_font_size' => 14, 'show_category' => 1, 'show_author' => 1, 'show_date' => 1,
            'show_excerpt' => 0,
            'excerpt_length' => 25,
            'excerpt_more_text' => 'Lire la suite',
            'excerpt_font_size' => 14,
            'excerpt_color' => '#4b5563',
            'module_bg_color' => 'rgba(255,255,255,0)', 'vignette_bg_color' => '#ffffff',
            'title_wrapper_bg_color' => '#ffffff', 'title_color' => '#333333',
            'meta_color' => '#6b7280', 'meta_color_hover' => '#000000', 'pagination_color' => '#333333',
            'shadow_color' => 'rgba(0,0,0,0.07)', 'shadow_color_hover' => 'rgba(0,0,0,0.12)',
        ];

        $saved_options = get_option( 'my_articles_options', array() );

        if ( ! is_array( $saved_options ) ) {
            $saved_options = array();
        }

        $aliases = array(
            'desktop_columns'     => 'columns_desktop',
            'mobile_columns'      => 'columns_mobile',
            'module_margin_left'  => 'module_padding_left',
            'module_margin_right' => 'module_padding_right',
        );

        foreach ( $aliases as $stored_key => $option_key ) {
            if ( array_key_exists( $stored_key, $saved_options ) ) {
                $saved_options[ $option_key ] = $saved_options[ $stored_key ];
            }
        }

        if ( ! empty( $saved_options['default_category'] ) ) {
            $saved_options['taxonomy'] = 'category';
            $saved_options['term']     = sanitize_title( (string) $saved_options['default_category'] );
        }

        $saved_options = array_intersect_key( $saved_options, $defaults );

        $cached_defaults = wp_parse_args( $saved_options, $defaults );

        return $cached_defaults;
    }

    public static function normalize_instance_options( $raw_options, $context = array() ) {
        if ( ! is_array( $context ) ) {
            $context = array();
        }

        $external_requested_category = '';

        if ( array_key_exists( 'requested_category', $context ) ) {
            $raw_requested_category = $context['requested_category'];

            if ( is_scalar( $raw_requested_category ) ) {
                $external_requested_category = sanitize_title( (string) $raw_requested_category );
            }

            $context['requested_category'] = $external_requested_category;
        }

        if ( array_key_exists( 'allow_external_requested_category', $context ) ) {
            $context['allow_external_requested_category'] = ! empty( $context['allow_external_requested_category'] ) ? 1 : 0;
        }

        $cache_key = self::build_normalized_options_cache_key( $raw_options, $context );

        if ( isset( self::$normalized_options_cache[ $cache_key ] ) ) {
            return self::$normalized_options_cache[ $cache_key ];
        }

        $defaults = self::get_default_options();
        $options  = wp_parse_args( (array) $raw_options, $defaults );

        $allowed_display_modes = array( 'grid', 'list', 'slideshow' );
        $display_mode          = $options['display_mode'] ?? $defaults['display_mode'];
        if ( ! in_array( $display_mode, $allowed_display_modes, true ) ) {
            $display_mode = $defaults['display_mode'];
        }
        $options['display_mode'] = $display_mode;

        $options['post_type'] = my_articles_normalize_post_type( $options['post_type'] ?? '' );

        $taxonomy = isset( $options['taxonomy'] ) ? sanitize_text_field( $options['taxonomy'] ) : '';
        if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $options['post_type'], $taxonomy ) ) {
            $taxonomy = '';
        }
        $options['taxonomy'] = $taxonomy;

        $options['term'] = sanitize_title( $options['term'] ?? '' );

        $options['resolved_taxonomy'] = self::resolve_taxonomy( $options );

        $raw_posts_per_page = isset( $options['posts_per_page'] ) ? (int) $options['posts_per_page'] : (int) $defaults['posts_per_page'];
        $is_unlimited       = $raw_posts_per_page <= 0;
        $posts_per_page     = $is_unlimited ? -1 : $raw_posts_per_page;

        if ( ! $is_unlimited && ( $options['counting_behavior'] ?? $defaults['counting_behavior'] ) === 'auto_fill' && in_array( $options['display_mode'], array( 'grid', 'slideshow' ), true ) ) {
            $master_columns = isset( $options['columns_ultrawide'] ) ? (int) $options['columns_ultrawide'] : 0;
            if ( $master_columns > 0 ) {
                $rows_needed    = (int) ceil( $posts_per_page / $master_columns );
                $posts_per_page = $rows_needed * $master_columns;
            }
        }

        $options['posts_per_page'] = $posts_per_page;
        $options['is_unlimited']   = $is_unlimited;

        $unlimited_cap = (int) apply_filters( 'my_articles_unlimited_batch_size', 50, $options, $context );
        $options['unlimited_query_cap'] = max( 1, $unlimited_cap );

        if (
            $is_unlimited
            && ( $options['pagination_mode'] ?? 'none' ) === 'none'
            && 'slideshow' !== $options['display_mode']
        ) {
            $options['pagination_mode'] = 'load_more';
        }

        $ignore_native_sticky        = ! empty( $options['ignore_native_sticky'] ) ? (int) $options['ignore_native_sticky'] : 0;
        $options['ignore_native_sticky'] = $ignore_native_sticky;

        $options['pinned_posts_ignore_filter'] = ! empty( $options['pinned_posts_ignore_filter'] ) ? 1 : 0;

        $filter_categories = array();
        if ( ! empty( $options['filter_categories'] ) ) {
            if ( is_array( $options['filter_categories'] ) ) {
                $filter_categories = $options['filter_categories'];
            } else {
                $filter_categories = explode( ',', (string) $options['filter_categories'] );
            }

            $filter_categories = array_values( array_filter( array_map( 'absint', $filter_categories ) ) );
        }
        $options['filter_categories'] = $filter_categories;

        $pinned_ids = array();
        if ( ! empty( $options['pinned_posts'] ) && is_array( $options['pinned_posts'] ) ) {
            $pinned_ids = array_values(
                array_filter(
                    array_unique( array_map( 'absint', $options['pinned_posts'] ) ),
                    static function ( $post_id ) use ( $options ) {
                        return $post_id > 0 && get_post_type( $post_id ) === $options['post_type'];
                    }
                )
            );
        }
        $options['pinned_posts'] = $pinned_ids;

        $exclude_post_ids = array();
        if ( ! empty( $options['exclude_posts'] ) ) {
            $raw_exclude_ids = is_array( $options['exclude_posts'] ) ? $options['exclude_posts'] : explode( ',', $options['exclude_posts'] );
            $exclude_post_ids = array_values( array_filter( array_map( 'absint', $raw_exclude_ids ) ) );
        }
        $options['exclude_post_ids'] = $exclude_post_ids;

        $options['all_excluded_ids'] = array_values( array_unique( array_merge( $pinned_ids, $exclude_post_ids ) ) );

        $default_term = $options['term'];
        $requested_category = '';

        $has_external_requested_category = '' !== $external_requested_category;
        $allow_external_requested_category = ! empty( $context['allow_external_requested_category'] );

        if ( $has_external_requested_category ) {
            if (
                $allow_external_requested_category
                || ! empty( $options['show_category_filter'] )
                || ! empty( $filter_categories )
            ) {
                $requested_category = $external_requested_category;
            }
        }

        $force_collect_terms = ! empty( $context['force_collect_terms'] );

        $should_collect_terms = $force_collect_terms
            || ! empty( $options['show_category_filter'] )
            || '' !== $requested_category
            || ! empty( $filter_categories );

        $available_categories     = array();
        $available_category_slugs = array();

        if ( $should_collect_terms && ! empty( $options['resolved_taxonomy'] ) ) {
            $get_terms_args = [
                'taxonomy'   => $options['resolved_taxonomy'],
                'hide_empty' => true,
            ];

            if ( ! empty( $filter_categories ) ) {
                $get_terms_args['include'] = $filter_categories;
                $get_terms_args['orderby'] = 'include';
            }

            $terms = get_terms( $get_terms_args );

            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $available_categories     = $terms;
                $available_category_slugs = array_values( array_filter( wp_list_pluck( $terms, 'slug' ), 'strlen' ) );
            }
        }

        $options['available_categories']     = $available_categories;
        $options['available_category_slugs'] = $available_category_slugs;

        $allowed_filter_term_slugs = array();
        if ( ! empty( $filter_categories ) && ! empty( $available_category_slugs ) ) {
            $allowed_filter_term_slugs = $available_category_slugs;
        }
        $options['allowed_filter_term_slugs'] = $allowed_filter_term_slugs;

        $valid_category_slugs = array_unique(
            array_merge(
                array( '', 'all', $default_term ),
                $allowed_filter_term_slugs
            )
        );
        $options['valid_category_slugs'] = $valid_category_slugs;

        $is_requested_category_valid = true;

        if ( ! empty( $allowed_filter_term_slugs ) ) {
            $is_requested_category_valid = in_array( $requested_category, $valid_category_slugs, true );
        }

        $active_category = $default_term;

        if ( '' !== $requested_category ) {
            if ( 'all' === $requested_category ) {
                $active_category = 'all';
            } elseif ( in_array( $requested_category, $available_category_slugs, true ) ) {
                $active_category = $requested_category;
            } elseif ( empty( $available_category_slugs ) ) {
                $active_category = $requested_category;
            }
        }

        $options['term']                       = $active_category;
        $options['default_term']               = $default_term;
        $options['requested_category']         = $requested_category;
        $options['is_requested_category_valid'] = $is_requested_category_valid;

        self::$normalized_options_cache[ $cache_key ] = $options;

        return $options;
    }

    public function render_shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'id'        => 0,
                'overrides' => array(),
            ),
            $atts,
            'mon_affichage_articles'
        );
        $id   = absint( $atts['id'] );
        $overrides = array();

        if ( ! empty( $atts['overrides'] ) && is_array( $atts['overrides'] ) ) {
            $defaults = self::get_default_options();

            foreach ( $atts['overrides'] as $key => $value ) {
                if ( ! array_key_exists( $key, $defaults ) ) {
                    continue;
                }

                $default_value = $defaults[ $key ];

                if ( is_array( $default_value ) ) {
                    if ( is_array( $value ) ) {
                        $overrides[ $key ] = $value;
                    }
                    continue;
                }

                if ( is_int( $default_value ) ) {
                    if ( is_bool( $value ) ) {
                        $overrides[ $key ] = $value ? 1 : 0;
                    } else {
                        $overrides[ $key ] = (int) $value;
                    }
                    continue;
                }

                if ( is_float( $default_value ) ) {
                    $overrides[ $key ] = (float) $value;
                    continue;
                }

                $overrides[ $key ] = (string) $value;
            }
        }

        if ( ! $id || 'mon_affichage' !== get_post_type( $id ) ) {
            return '';
        }

        $post_status      = get_post_status( $id );
        $allowed_statuses = self::get_allowed_instance_statuses( $id );

        if ( empty( $post_status ) || ! in_array( $post_status, $allowed_statuses, true ) ) {
            return '';
        }

        $options_meta = get_post_meta( $id, '_my_articles_settings', true );
        if ( ! is_array( $options_meta ) ) {
            $options_meta = array();
        }

        if ( ! empty( $overrides ) ) {
            $options_meta = array_merge( $options_meta, $overrides );
        }

        $has_filter_categories = false;
        if ( isset( $options_meta['filter_categories'] ) ) {
            $raw_filter_categories = $options_meta['filter_categories'];

            if ( is_string( $raw_filter_categories ) ) {
                $raw_filter_categories = explode( ',', $raw_filter_categories );
            }

            if ( is_array( $raw_filter_categories ) ) {
                $normalized_filter_categories = array_values( array_filter( array_map( 'absint', $raw_filter_categories ) ) );
                $has_filter_categories       = ! empty( $normalized_filter_categories );
            }
        }

        $allows_requested_category = ! empty( $options_meta['show_category_filter'] ) || $has_filter_categories;

        $category_query_var   = 'my_articles_cat_' . $id;
        $requested_category   = '';

        if ( $allows_requested_category && isset( $_GET[ $category_query_var ] ) ) {
            $raw_requested_category = wp_unslash( $_GET[ $category_query_var ] );

            if ( is_scalar( $raw_requested_category ) ) {
                $requested_category = sanitize_title( (string) $raw_requested_category );
            } elseif ( is_array( $raw_requested_category ) ) {
                foreach ( $raw_requested_category as $raw_requested_category_value ) {
                    if ( is_scalar( $raw_requested_category_value ) ) {
                        $requested_category = sanitize_title( (string) $raw_requested_category_value );
                        break;
                    }
                }
            }
        }

        $normalize_context = array(
            'allow_external_requested_category' => $allows_requested_category,
        );

        if ( '' !== $requested_category ) {
            $normalize_context['requested_category'] = $requested_category;
        }

        $options = self::normalize_instance_options(
            $options_meta,
            $normalize_context
        );

        $resolved_taxonomy = $options['resolved_taxonomy'];
        $available_categories = $options['available_categories'];

        if ( !empty($options['show_category_filter']) ) {
            wp_enqueue_script('my-articles-filter', MY_ARTICLES_PLUGIN_URL . 'assets/js/filter.js', ['jquery'], MY_ARTICLES_VERSION, true);
            wp_localize_script(
                'my-articles-filter',
                'myArticlesFilter',
                [
                    'ajax_url'  => admin_url('admin-ajax.php'),
                    'nonce'     => wp_create_nonce('my_articles_filter_nonce'),
                    'errorText' => __( 'Erreur AJAX.', 'mon-articles' ),
                ]
            );
        }

        if ( $options['pagination_mode'] === 'load_more' ) {
            wp_enqueue_script('my-articles-load-more', MY_ARTICLES_PLUGIN_URL . 'assets/js/load-more.js', ['jquery'], MY_ARTICLES_VERSION, true);
            wp_localize_script(
                'my-articles-load-more',
                'myArticlesLoadMore',
                [
                    'ajax_url'     => admin_url('admin-ajax.php'),
                    'nonce'        => wp_create_nonce('my_articles_load_more_nonce'),
                    'loadingText'  => __( 'Chargement...', 'mon-articles' ),
                    'loadMoreText' => esc_html__( 'Charger plus', 'mon-articles' ),
                    'errorText'    => __( 'Erreur AJAX.', 'mon-articles' ),
                ]
            );
        }

        if ( $options['pagination_mode'] === 'numbered' ) {
            wp_enqueue_script('my-articles-scroll-fix', MY_ARTICLES_PLUGIN_URL . 'assets/js/scroll-fix.js', ['jquery'], MY_ARTICLES_VERSION, true);
        }

        if ( !empty($options['enable_lazy_load']) && !self::$lazysizes_enqueued ) {
            wp_enqueue_script('lazysizes');
            self::$lazysizes_enqueued = true;
        }

        $paged_var = 'paged_' . $id;
        $paged = isset($_GET[$paged_var]) ? absint( wp_unslash( $_GET[$paged_var] ) ) : 1;

        $all_excluded_ids = isset( $options['all_excluded_ids'] ) ? (array) $options['all_excluded_ids'] : array();

        $state = $this->build_display_state(
            $options,
            array(
                'paged'                   => $paged,
                'pagination_strategy'     => 'page',
                'enforce_unlimited_batch' => ( ! empty( $options['is_unlimited'] ) && 'slideshow' !== $options['display_mode'] ),
            )
        );

        $pinned_query        = $state['pinned_query'];
        $articles_query      = $state['regular_query'];
        $total_matching_pinned = $state['total_pinned_posts'];
        $first_page_projected_pinned = $total_matching_pinned;
        $should_limit_display = $state['should_limit_display'];
        $render_limit         = $state['render_limit'];
        $regular_posts_needed = $state['regular_posts_needed'];
        $is_unlimited         = ! empty( $state['is_unlimited'] );
        $effective_posts_per_page = $state['effective_posts_per_page'];

        if ( 'slideshow' === $options['display_mode'] ) {
            $should_limit_display = false;
        }
        
        if ($options['display_mode'] === 'slideshow') { $this->enqueue_swiper_scripts($options, $id); }

        wp_enqueue_style('my-articles-styles');

        $default_min_card_width = 220;
        $options['min_card_width'] = max(1, (int) apply_filters('my_articles_min_card_width', $default_min_card_width, $options, $id));

        if ( in_array( $options['display_mode'], array( 'grid', 'list', 'slideshow' ), true ) ) {
            wp_enqueue_script( 'my-articles-responsive-layout' );
        }

        ob_start();
        $this->render_inline_styles($options, $id);

        $wrapper_class = 'my-articles-wrapper my-articles-' . esc_attr($options['display_mode']);

        $columns_mobile    = max( 1, (int) $options['columns_mobile'] );
        $columns_tablet    = max( 1, (int) $options['columns_tablet'] );
        $columns_desktop   = max( 1, (int) $options['columns_desktop'] );
        $columns_ultrawide = max( 1, (int) $options['columns_ultrawide'] );
        $min_card_width    = max( 1, (int) $options['min_card_width'] );

        $wrapper_attributes = array(
            'id'                   => 'my-articles-wrapper-' . $id,
            'class'                => $wrapper_class,
            'data-instance-id'     => $id,
            'data-cols-mobile'     => $columns_mobile,
            'data-cols-tablet'     => $columns_tablet,
            'data-cols-desktop'    => $columns_desktop,
            'data-cols-ultrawide'  => $columns_ultrawide,
            'data-min-card-width'  => $min_card_width,
        );

        $wrapper_attribute_strings = array();
        foreach ( $wrapper_attributes as $attribute => $value ) {
            $wrapper_attribute_strings[] = sprintf( '%s="%s"', esc_attr( $attribute ), esc_attr( (string) $value ) );
        }

        echo '<div ' . implode( ' ', $wrapper_attribute_strings ) . '>';

        if ( ! empty( $options['show_category_filter'] ) && ! empty( $resolved_taxonomy ) && ! empty( $available_categories ) ) {
            $alignment_class = 'filter-align-' . esc_attr( $options['filter_alignment'] );
            echo '<nav class="my-articles-filter-nav ' . $alignment_class . '"><ul>';
            $default_cat   = $options['term'] ?? '';
            $is_all_active = '' === $default_cat || 'all' === $default_cat;
            echo '<li class="' . ( $is_all_active ? 'active' : '' ) . '"><button type="button" data-category="all" aria-pressed="' . ( $is_all_active ? 'true' : 'false' ) . '">' . esc_html__( 'Tout', 'mon-articles' ) . '</button></li>';

            foreach ( $available_categories as $category ) {
                $is_active = ( $default_cat === $category->slug );
                echo '<li class="' . ( $is_active ? 'active' : '' ) . '"><button type="button" data-category="' . esc_attr( $category->slug ) . '" aria-pressed="' . ( $is_active ? 'true' : 'false' ) . '">' . esc_html( $category->name ) . '</button></li>';
            }

            echo '</ul></nav>';
        }
        $displayed_pinned_ids = array();

        $posts_per_page_for_render    = $render_limit > 0 ? $render_limit : $effective_posts_per_page;
        $posts_per_page_for_slideshow = $effective_posts_per_page;

        if ( $is_unlimited && 0 === $posts_per_page_for_render ) {
            $posts_per_page_for_render = 0;
        }

        if ( $is_unlimited && 0 === $posts_per_page_for_slideshow ) {
            $posts_per_page_for_slideshow = 0;
        }

        if ($options['display_mode'] === 'slideshow') {
            $this->render_slideshow($pinned_query, $articles_query, $options, $posts_per_page_for_slideshow);
        } else if ($options['display_mode'] === 'list') {
            $displayed_pinned_ids = $this->render_list($pinned_query, $articles_query, $options, $posts_per_page_for_render);
            if ( ! is_array( $displayed_pinned_ids ) ) {
                $displayed_pinned_ids = array();
            }
        } else {
            $displayed_pinned_ids = $this->render_grid($pinned_query, $articles_query, $options, $posts_per_page_for_render);
            if ( ! is_array( $displayed_pinned_ids ) ) {
                $displayed_pinned_ids = array();
            }
        }

        if ( $paged === 1 ) {
            $first_page_projected_pinned = count( $displayed_pinned_ids );
            if ( 0 === $total_matching_pinned && ! empty( $displayed_pinned_ids ) ) {
                $total_matching_pinned = count( $displayed_pinned_ids );
            }
        }

        if ($options['display_mode'] === 'grid' || $options['display_mode'] === 'list') {
            $total_regular_posts = (int) $state['total_regular_posts'];

            if ( 0 === $total_regular_posts && ! ( $articles_query instanceof WP_Query ) ) {
                $count_query_args = [
                    'post_type' => $options['post_type'],
                    'post_status' => 'publish',
                    'posts_per_page' => 1,
                    'post__not_in' => $all_excluded_ids,
                    'ignore_sticky_posts' => (int) $options['ignore_native_sticky'],
                    'fields' => 'ids',
                ];

                if ( '' !== $resolved_taxonomy && '' !== $options['term'] && 'all' !== $options['term'] ) {
                    $count_query_args['tax_query'] = [[
                        'taxonomy' => $resolved_taxonomy,
                        'field'    => 'slug',
                        'terms'    => $options['term'],
                    ]];
                }

                $count_query = new WP_Query($count_query_args);
                $total_regular_posts = (int) $count_query->found_posts;
            }

            $pagination_totals = my_articles_calculate_total_pages(
                $total_matching_pinned,
                $total_regular_posts,
                $effective_posts_per_page
            );
            $total_pages = $pagination_totals['total_pages'];

            if ($options['pagination_mode'] === 'load_more') {
                if ( $total_pages > 1 && $paged < $total_pages) {
                    $next_page = min( $paged + 1, $total_pages );
                    $load_more_pinned_ids = ! empty( $displayed_pinned_ids ) ? array_map( 'absint', $displayed_pinned_ids ) : array();
                    echo '<div class="my-articles-load-more-container"><button class="my-articles-load-more-btn" data-instance-id="' . esc_attr($id) . '" data-paged="' . esc_attr( $next_page ) . '" data-total-pages="' . esc_attr($total_pages) . '" data-pinned-ids="' . esc_attr(implode(',', $load_more_pinned_ids)) . '" data-category="' . esc_attr($options['term']) . '">' . esc_html__( 'Charger plus', 'mon-articles' ) . '</button></div>';
                }
            } elseif ($options['pagination_mode'] === 'numbered') {
                $pagination_query_args = array();
                if ( '' !== $options['term'] ) {
                    $pagination_query_args[ $category_query_var ] = $options['term'];
                }
                $this->render_numbered_pagination($total_pages, $paged, $paged_var, $pagination_query_args);
            }
        }
        
        if ( ! empty( $options['enable_debug_mode'] ) ) {
            echo '<div style="background: #fff; border: 2px solid red; padding: 15px; margin: 20px 0; text-align: left; color: #000; font-family: monospace; line-height: 1.6; clear: both;">';
            echo '<h4 style="margin: 0 0 10px 0;">-- DEBUG MODE --</h4>';
            echo '<ul>';
            echo '<li>Réglage "Lazy Load" activé : <strong>' . ( ! empty( $options['enable_lazy_load'] ) ? 'Oui' : 'Non' ) . '</strong></li>';
            echo '<li>Statut du script lazysizes : <strong id="lazysizes-status-' . esc_attr( $id ) . '" style="color: red;">En attente...</strong></li>';
            echo '</ul>';
            echo '</div>';

            wp_enqueue_script( 'my-articles-debug-helper' );

            $status_span_id = 'lazysizes-status-' . $id;
            $debug_script   = sprintf(
                "document.addEventListener('DOMContentLoaded',function(){var statusSpan=document.getElementById(%s);if(!statusSpan){return;}setTimeout(function(){if(window.lazySizes){statusSpan.textContent=%s;statusSpan.style.color='green';}else{statusSpan.textContent=%s;}},500);});",
                wp_json_encode( $status_span_id ),
                wp_json_encode( '✅ Chargé et actif !' ),
                wp_json_encode( '❌ ERREUR : Non trouvé !' )
            );

            wp_add_inline_script( 'my-articles-debug-helper', $debug_script );
        }
        
        echo '</div>';
        
        wp_reset_postdata();
        return ob_get_clean();
    }
    
    private function render_articles_in_container( $pinned_query, $regular_query, $options, $posts_per_page, $container_class ) {
        $has_rendered_posts   = false;
        $render_limit         = max( 0, (int) $posts_per_page );
        $should_limit         = $render_limit > 0;
        $rendered_count       = 0;
        $displayed_pinned_ids = array();

        echo '<div class="' . esc_attr( $container_class ) . '">';

        if ( $pinned_query instanceof WP_Query && $pinned_query->have_posts() ) {
            while ( $pinned_query->have_posts() && ( ! $should_limit || $rendered_count < $render_limit ) ) {
                $pinned_query->the_post();
                $this->render_article_item( $options, true );
                $has_rendered_posts   = true;
                $rendered_count++;
                $pinned_id = absint( get_the_ID() );
                if ( $pinned_id > 0 ) {
                    $displayed_pinned_ids[] = $pinned_id;
                }
            }
        }

        if ( $regular_query instanceof WP_Query && $regular_query->have_posts() ) {
            while ( $regular_query->have_posts() && ( ! $should_limit || $rendered_count < $render_limit ) ) {
                $regular_query->the_post();
                $this->render_article_item( $options, false );
                $has_rendered_posts = true;
                $rendered_count++;
            }
        }

        echo '</div>';

        if ( ! $has_rendered_posts ) {
            $this->render_empty_state_message();
        }

        if ( $pinned_query instanceof WP_Query || $regular_query instanceof WP_Query ) {
            wp_reset_postdata();
        }

        return $displayed_pinned_ids;
    }

    private function render_list($pinned_query, $regular_query, $options, $posts_per_page) {
        return $this->render_articles_in_container( $pinned_query, $regular_query, $options, $posts_per_page, 'my-articles-list-content' );
    }

    private function render_grid($pinned_query, $regular_query, $options, $posts_per_page) {
        return $this->render_articles_in_container( $pinned_query, $regular_query, $options, $posts_per_page, 'my-articles-grid-content' );
    }

    private function render_slideshow($pinned_query, $regular_query, $options, $posts_per_page) {
        $is_unlimited       = (int) $posts_per_page <= 0;
        $total_posts_needed = $is_unlimited ? PHP_INT_MAX : (int) $posts_per_page;
        echo '<div class="swiper-container"><div class="swiper-wrapper">';
        $post_count = 0;

        if ( $pinned_query && $pinned_query->have_posts() ) {
            while ( $pinned_query->have_posts() && $post_count < $total_posts_needed ) {
                $pinned_query->the_post();
                echo '<div class="swiper-slide">';
                $this->render_article_item( $options, true );
                echo '</div>';
                $post_count++;
            }
        }

        if ( $regular_query && $regular_query->have_posts() ) {
            while ( $regular_query->have_posts() && $post_count < $total_posts_needed ) {
                $regular_query->the_post();
                echo '<div class="swiper-slide">';
                $this->render_article_item( $options, false );
                echo '</div>';
                $post_count++;
            }
        }

        if ( 0 === $post_count ) {
            $this->render_empty_state_message( true );
        }

        echo '</div><div class="swiper-pagination"></div><div class="swiper-button-next"></div><div class="swiper-button-prev"></div></div>';

        if ( $pinned_query instanceof WP_Query || $regular_query instanceof WP_Query ) {
            wp_reset_postdata();
        }
    }

    public function get_empty_state_html() {
        return '<p style="text-align: center; width: 100%; padding: 20px;">' . esc_html__( 'Aucun article trouvé dans cette catégorie.', 'mon-articles' ) . '</p>';
    }

    public function get_empty_state_slide_html() {
        return '<div class="swiper-slide swiper-slide-empty">' . $this->get_empty_state_html() . '</div>';
    }

    private function render_empty_state_message( $wrap_for_swiper = false ) {
        if ( $wrap_for_swiper ) {
            echo $this->get_empty_state_slide_html();
            return;
        }

        echo $this->get_empty_state_html();
    }

    public function render_article_item($options, $is_pinned = false) {
        $item_classes = 'my-article-item';
        if ($is_pinned) { $item_classes .= ' is-pinned'; }
        $display_mode = $options['display_mode'] ?? 'grid';
        $taxonomy = $options['resolved_taxonomy'] ?? self::resolve_taxonomy( $options );
        $enable_lazy_load = !empty($options['enable_lazy_load']);
        $excerpt_more = __( '…', 'mon-articles' );
        ?>
        <article class="<?php echo esc_attr($item_classes); ?>">
            <?php
            if ($display_mode === 'list') {
                $this->render_article_common_block($options, $is_pinned, $taxonomy, $enable_lazy_load, 'article-content-wrapper', $excerpt_more);
            } else {
                $this->render_article_common_block($options, $is_pinned, $taxonomy, $enable_lazy_load, 'article-title-wrapper', '');
            }
            ?>
        </article>
        <?php
    }

    private function render_article_common_block($options, $is_pinned, $taxonomy, $enable_lazy_load, $wrapper_class, $excerpt_more) {
        $permalink     = get_permalink();
        $escaped_link  = esc_url( $permalink );
        $raw_title     = get_the_title();
        $title_attr    = esc_attr( $raw_title );
        $title_display = esc_html( $raw_title );
        $term_names    = array();

        if ( $options['show_category'] && ! empty( $taxonomy ) ) {
            $terms = get_the_terms( get_the_ID(), $taxonomy );

            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $term_names = array_map( 'sanitize_text_field', wp_list_pluck( $terms, 'name' ) );
            }
        }
        ?>
        <a href="<?php echo $escaped_link; ?>" class="my-article-link">
            <div class="article-thumbnail-wrapper">
                <?php if ($is_pinned && !empty($options['pinned_show_badge'])) : ?><span class="my-article-badge"><?php echo esc_html($options['pinned_badge_text']); ?></span><?php endif; ?>
                <?php if (has_post_thumbnail()):
                    $image_id = get_post_thumbnail_id();
                    $thumbnail_html = $this->get_article_thumbnail_html( $image_id, $title_attr, $enable_lazy_load );

                    if ( '' !== $thumbnail_html ) {
                        echo $thumbnail_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    } else {
                        the_post_thumbnail('large');
                    }
                else: ?>
                    <?php $fallback_placeholder = MY_ARTICLES_PLUGIN_URL . 'assets/images/placeholder.svg'; ?>
                    <img src="<?php echo esc_url($fallback_placeholder); ?>" alt="<?php esc_attr_e('Image non disponible', 'mon-articles'); ?>">
                <?php endif; ?>
            </div>
            <div class="<?php echo esc_attr($wrapper_class); ?>">
                <h2 class="article-title"><?php echo $title_display; ?></h2>
                <?php if ($options['show_category'] || $options['show_author'] || $options['show_date']) : ?>
                    <div class="article-meta">
                        <?php if ($options['show_category'] && !empty($taxonomy) && !empty($term_names)) : ?>
                            <span class="article-category"><?php echo esc_html( implode( ', ', $term_names ) ); ?></span>
                        <?php endif; ?>
                        <?php if ( $options['show_author'] ) : ?>
                            <span class="article-author"><?php printf('%s %s', esc_html__( 'par', 'mon-articles' ), esc_html( get_the_author() ) ); ?></span>
                        <?php endif; ?>
                        <?php if ($options['show_date']) : ?>
                            <span class="article-date"><?php echo esc_html(get_the_date()); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php
                if (!empty($options['show_excerpt'])) {
                    $excerpt_length  = isset($options['excerpt_length']) ? (int) $options['excerpt_length'] : 0;
                    $raw_excerpt     = get_the_excerpt();
                    $trimmed_excerpt = '';
                    $has_read_more   = ! empty($options['excerpt_more_text']);

                    if ($excerpt_length > 0) {
                        $trimmed_excerpt = wp_trim_words($raw_excerpt, $excerpt_length, $excerpt_more);
                    }

                    $has_excerpt_content = '' !== trim(strip_tags($trimmed_excerpt));

                    if ($has_excerpt_content || $has_read_more) {
                        ?>
                    <div class="my-article-excerpt">
                        <?php
                        if ($has_excerpt_content) {
                            echo wp_kses_post($trimmed_excerpt);
                        }

                        if ($has_read_more) {
                            ?>
                            <span class="my-article-read-more"><?php echo esc_html($options['excerpt_more_text']); ?></span>
                            <?php
                        }
                        ?>
                    </div>
                    <?php
                    }
                }
                ?>
            </div>
        </a>
        <?php
    }

    private function get_article_thumbnail_html( $image_id, $title_attr, $enable_lazy_load ) {
        $size            = 'large';
        $placeholder_src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

        if ( $enable_lazy_load ) {
            $image_data = wp_get_attachment_image_src( $image_id, $size );

            if ( empty( $image_data ) || empty( $image_data[0] ) ) {
                return '';
            }

            $image_src    = $image_data[0];
            $image_width  = isset( $image_data[1] ) ? (int) $image_data[1] : 0;
            $image_height = isset( $image_data[2] ) ? (int) $image_data[2] : 0;

            $image_srcset = wp_get_attachment_image_srcset( $image_id, $size );

            $attributes = array(
                'src'        => $placeholder_src,
                'class'      => 'attachment-large size-large wp-post-image lazyload',
                'alt'        => $title_attr,
                'data-sizes' => 'auto',
                'data-src'   => $image_src,
                'decoding'   => 'async',
                'loading'    => 'lazy',
            );

            if ( ! empty( $image_srcset ) ) {
                $attributes['data-srcset'] = $image_srcset;
            }

            if ( $image_width > 0 ) {
                $attributes['width'] = $image_width;
            }

            if ( $image_height > 0 ) {
                $attributes['height'] = $image_height;
            }

            $html = '<img';

            foreach ( $attributes as $attr_name => $attr_value ) {
                if ( '' === $attr_value && 0 !== $attr_value ) {
                    continue;
                }

                if ( 'data-src' === $attr_name ) {
                    $escaped_value = esc_url( $attr_value );
                } elseif ( 'data-srcset' === $attr_name ) {
                    $escaped_value = esc_attr( $attr_value );
                } elseif ( 'src' === $attr_name && 0 !== strncmp( $attr_value, 'data:', 5 ) ) {
                    $escaped_value = esc_url( $attr_value );
                } else {
                    $escaped_value = esc_attr( $attr_value );
                }

                $html .= ' ' . esc_attr( $attr_name ) . '="' . $escaped_value . '"';
            }

            $html .= ' />';

            return $html;
        }

        $attributes = array(
            'class'    => 'attachment-large size-large wp-post-image',
            'alt'      => $title_attr,
            'decoding' => 'async',
            'loading'  => 'eager',
        );

        $image_html = wp_get_attachment_image( $image_id, $size, false, $attributes );

        if ( ! $enable_lazy_load && is_string( $image_html ) && '' !== $image_html && false !== strpos( $image_html, 'loading="lazy"' ) ) {
            $image_html = str_replace( 'loading="lazy"', 'loading="eager"', $image_html );
        }

        return $image_html;
    }

    private function enqueue_swiper_scripts($options, $instance_id) {
        wp_enqueue_style('swiper-css');
        wp_enqueue_script('swiper-js');
        wp_enqueue_script('my-articles-swiper-init', MY_ARTICLES_PLUGIN_URL . 'assets/js/swiper-init.js', ['swiper-js', 'my-articles-responsive-layout'], MY_ARTICLES_VERSION, true);
        wp_localize_script('my-articles-swiper-init', 'myArticlesSwiperSettings_' . $instance_id, [ 'columns_mobile' => $options['columns_mobile'], 'columns_tablet' => $options['columns_tablet'], 'columns_desktop' => $options['columns_desktop'], 'columns_ultrawide' => $options['columns_ultrawide'], 'gap_size' => $options['gap_size'], 'container_selector' => '#my-articles-wrapper-' . $instance_id . ' .swiper-container' ]);
    }
    
    /**
     * Builds the HTML markup for numbered pagination links.
     *
     * @param int    $total_pages            Total number of pages available.
     * @param int    $paged                  Current page number.
     * @param string $paged_var              Query variable used for the pagination links.
     * @param array  $additional_query_args  Additional query arguments to preserve when generating links.
     * @param string $base_url               Base URL to use when generating the pagination links. When empty, the
     *                                       current request derived from $wp->request is used as a fallback.
     *
     * @return string HTML markup for the pagination component or an empty string if no pagination is needed.
     */
    public function get_numbered_pagination_html( $total_pages, $paged, $paged_var, $additional_query_args = array(), $base_url = '' ) {
        if ( $total_pages <= 1 ) {
            return '';
        }

        $site_home = home_url();

        $base_url = my_articles_normalize_internal_url( $base_url, $site_home );

        if ( '' === $base_url ) {
            global $wp;

            $request_path = '';
            if ( isset( $wp ) && is_object( $wp ) && isset( $wp->request ) ) {
                $request_path = $wp->request;
            }

            $fallback_base = home_url( add_query_arg( array(), $request_path ) );

            if ( ! empty( $_GET ) ) {
                $raw_query_args = wp_unslash( $_GET );
                if ( is_array( $raw_query_args ) ) {
                    $sanitized_query_args = map_deep( $raw_query_args, 'sanitize_text_field' );
                } else {
                    $sanitized_query_args = array();
                }

                if ( isset( $sanitized_query_args[ $paged_var ] ) ) {
                    unset( $sanitized_query_args[ $paged_var ] );
                }

                if ( ! empty( $sanitized_query_args ) ) {
                    $fallback_base = add_query_arg( $sanitized_query_args, $fallback_base );
                }
            }

            $base_url = my_articles_normalize_internal_url( $fallback_base, $site_home );
        }

        if ( '' === $base_url ) {
            return '';
        }

        $base_url = remove_query_arg( $paged_var, $base_url );

        $existing_args = array();
        $query_string  = wp_parse_url( $base_url, PHP_URL_QUERY );
        if ( $query_string ) {
            wp_parse_str( $query_string, $existing_args );
            $existing_args = map_deep( $existing_args, 'sanitize_text_field' );

            if ( ! empty( $existing_args ) ) {
                $base_url = remove_query_arg( array_keys( $existing_args ), $base_url );
            }
        }

        $clean_additional_args = array();
        if ( ! empty( $additional_query_args ) && is_array( $additional_query_args ) ) {
            foreach ( $additional_query_args as $key => $value ) {
                $clean_key = sanitize_key( $key );
                if ( '' === $clean_key ) {
                    continue;
                }

                if ( is_array( $value ) ) {
                    continue;
                }

                $value       = (string) $value;
                $clean_value = sanitize_text_field( $value );

                if ( '' === $clean_value && '0' !== $clean_value ) {
                    continue;
                }

                $clean_additional_args[ $clean_key ] = $clean_value;
            }
        }

        $query_args = array_merge( $existing_args, $clean_additional_args );

        $base_without_query = rtrim( $base_url, '?' );
        $format             = ( strpos( $base_without_query, '?' ) !== false ? '&' : '?' ) . $paged_var . '=%#%';

        $pagination_links = paginate_links(
            [
                'base'      => $base_without_query . '%_%',
                'format'    => $format,
                'add_args'  => ! empty( $query_args ) ? $query_args : false,
                'current'   => max( 1, (int) $paged ),
                'total'     => max( 1, (int) $total_pages ),
                'prev_text' => __( '&laquo; Précédent', 'mon-articles' ),
                'next_text' => __( 'Suivant &raquo;', 'mon-articles' ),
            ]
        );

        if ( empty( $pagination_links ) ) {
            return '';
        }

        return '<nav class="my-articles-pagination">' . $pagination_links . '</nav>';
    }

    private function render_numbered_pagination( $total_pages, $paged, $paged_var, $additional_query_args = array(), $base_url = '' ) {
        $pagination_html = $this->get_numbered_pagination_html( $total_pages, $paged, $paged_var, $additional_query_args, $base_url );

        if ( ! empty( $pagination_html ) ) {
            echo $pagination_html;
        }
    }

    private function render_inline_styles($options, $id) {
        $defaults = self::get_default_options();

        $min_card_width = 220;
        if ( isset( $options['min_card_width'] ) ) {
            $min_card_width = max( 1, (int) $options['min_card_width'] );
        }

        $columns_mobile   = max( 1, absint( $options['columns_mobile'] ?? $defaults['columns_mobile'] ) );
        $columns_tablet   = max( 1, absint( $options['columns_tablet'] ?? $defaults['columns_tablet'] ) );
        $columns_desktop  = max( 1, absint( $options['columns_desktop'] ?? $defaults['columns_desktop'] ) );
        $columns_ultrawide = max( 1, absint( $options['columns_ultrawide'] ?? $defaults['columns_ultrawide'] ) );

        $gap_size       = max( 0, absint( $options['gap_size'] ?? $defaults['gap_size'] ) );
        $list_item_gap  = max( 0, absint( $options['list_item_gap'] ?? $defaults['list_item_gap'] ) );
        $padding_top    = max( 0, absint( $options['list_content_padding_top'] ?? $defaults['list_content_padding_top'] ) );
        $padding_right  = max( 0, absint( $options['list_content_padding_right'] ?? $defaults['list_content_padding_right'] ) );
        $padding_bottom = max( 0, absint( $options['list_content_padding_bottom'] ?? $defaults['list_content_padding_bottom'] ) );
        $padding_left   = max( 0, absint( $options['list_content_padding_left'] ?? $defaults['list_content_padding_left'] ) );

        $border_radius       = max( 0, absint( $options['border_radius'] ?? $defaults['border_radius'] ) );
        $title_font_size     = max( 1, absint( $options['title_font_size'] ?? $defaults['title_font_size'] ) );
        $meta_font_size      = max( 1, absint( $options['meta_font_size'] ?? $defaults['meta_font_size'] ) );
        $excerpt_font_size   = max( 1, absint( $options['excerpt_font_size'] ?? $defaults['excerpt_font_size'] ) );
        $module_padding_left  = max( 0, absint( $options['module_padding_left'] ?? $defaults['module_padding_left'] ) );
        $module_padding_right = max( 0, absint( $options['module_padding_right'] ?? $defaults['module_padding_right'] ) );

        $title_color          = my_articles_sanitize_color( $options['title_color'] ?? '', $defaults['title_color'] );
        $meta_color           = my_articles_sanitize_color( $options['meta_color'] ?? '', $defaults['meta_color'] );
        $meta_color_hover     = my_articles_sanitize_color( $options['meta_color_hover'] ?? '', $defaults['meta_color_hover'] );
        $excerpt_color        = my_articles_sanitize_color( $options['excerpt_color'] ?? '', $defaults['excerpt_color'] );
        $pagination_color     = my_articles_sanitize_color( $options['pagination_color'] ?? '', $defaults['pagination_color'] );
        $shadow_color         = my_articles_sanitize_color( $options['shadow_color'] ?? '', $defaults['shadow_color'] );
        $shadow_color_hover   = my_articles_sanitize_color( $options['shadow_color_hover'] ?? '', $defaults['shadow_color_hover'] );
        $pinned_border_color  = my_articles_sanitize_color( $options['pinned_border_color'] ?? '', $defaults['pinned_border_color'] );
        $pinned_badge_bg      = my_articles_sanitize_color( $options['pinned_badge_bg_color'] ?? '', $defaults['pinned_badge_bg_color'] );
        $pinned_badge_text    = my_articles_sanitize_color( $options['pinned_badge_text_color'] ?? '', $defaults['pinned_badge_text_color'] );
        $module_bg_color      = my_articles_sanitize_color( $options['module_bg_color'] ?? '', $defaults['module_bg_color'] );
        $vignette_bg_color    = my_articles_sanitize_color( $options['vignette_bg_color'] ?? '', $defaults['vignette_bg_color'] );
        $title_wrapper_bg     = my_articles_sanitize_color( $options['title_wrapper_bg_color'] ?? '', $defaults['title_wrapper_bg_color'] );

        $dynamic_css = "
        #my-articles-wrapper-{$id} {
            --my-articles-cols-mobile: {$columns_mobile};
            --my-articles-cols-tablet: {$columns_tablet};
            --my-articles-cols-desktop: {$columns_desktop};
            --my-articles-cols-ultrawide: {$columns_ultrawide};
            --my-articles-min-card-width: {$min_card_width}px;
            --my-articles-gap: {$gap_size}px;
            --my-articles-list-gap: {$list_item_gap}px;
            --my-articles-list-padding-top: {$padding_top}px;
            --my-articles-list-padding-right: {$padding_right}px;
            --my-articles-list-padding-bottom: {$padding_bottom}px;
            --my-articles-list-padding-left: {$padding_left}px;
            --my-articles-border-radius: {$border_radius}px;
            --my-articles-title-color: {$title_color};
            --my-articles-title-font-size: {$title_font_size}px;
            --my-articles-meta-color: {$meta_color};
            --my-articles-meta-hover-color: {$meta_color_hover};
            --my-articles-meta-font-size: {$meta_font_size}px;
            --my-articles-excerpt-font-size: {$excerpt_font_size}px;
            --my-articles-excerpt-color: {$excerpt_color};
            --my-articles-pagination-color: {$pagination_color};
            --my-articles-shadow-color: {$shadow_color};
            --my-articles-shadow-color-hover: {$shadow_color_hover};
            --my-articles-pinned-border-color: {$pinned_border_color};
            --my-articles-badge-bg-color: {$pinned_badge_bg};
            --my-articles-badge-text-color: {$pinned_badge_text};
            background-color: {$module_bg_color};
            padding-left: {$module_padding_left}px;
            padding-right: {$module_padding_right}px;
        }
        #my-articles-wrapper-{$id} .my-article-item { background-color: {$vignette_bg_color}; }
        #my-articles-wrapper-{$id}.my-articles-grid .my-article-item .article-title-wrapper,
        #my-articles-wrapper-{$id}.my-articles-slideshow .my-article-item .article-title-wrapper,
        #my-articles-wrapper-{$id}.my-articles-list .my-article-item .article-content-wrapper { background-color: {$title_wrapper_bg}; }
        ";

        wp_add_inline_style( 'my-articles-styles', $dynamic_css );
    }

    public static function resolve_taxonomy( $options ) {
        $post_type = my_articles_normalize_post_type( $options['post_type'] ?? 'post' );

        if ( ! empty( $options['taxonomy'] ) && taxonomy_exists( $options['taxonomy'] ) && is_object_in_taxonomy( $post_type, $options['taxonomy'] ) ) {
            return $options['taxonomy'];
        }

        if ( 'post' === $post_type && taxonomy_exists( 'category' ) && is_object_in_taxonomy( 'post', 'category' ) ) {
            return 'category';
        }

        return '';
    }
}
