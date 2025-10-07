<?php
/**
 * Helper functions for Mon Affichage Articles plugin.
 */

if ( ! function_exists( 'my_articles_sanitize_color' ) ) {
    /**
     * Sanitize a color value allowing HEX and RGBA formats.
     *
     * @param string $color   The color value to sanitize.
     * @param string $default The default value to return when the color is invalid.
     *
     * @return string Sanitized color or default value.
     */
    function my_articles_sanitize_color( $color, $default = '' ) {
        if ( is_string( $color ) ) {
            $color = trim( $color );
        } elseif ( is_numeric( $color ) ) {
            $color = (string) $color;
        } else {
            return $default;
        }

        if ( preg_match(
            '/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*((?:\d+(?:\.\d+)?|\.\d+))\s*\)$/',
            $color,
            $matches
        ) ) {
            list( , $red_component, $green_component, $blue_component, $alpha_component ) = $matches;

            $red   = (int) $red_component;
            $green = (int) $green_component;
            $blue  = (int) $blue_component;
            $alpha = (float) $alpha_component;

            if ( $red < 0 || $red > 255 || $green < 0 || $green > 255 || $blue < 0 || $blue > 255 ) {
                return $default;
            }

            if ( $alpha < 0 || $alpha > 1 ) {
                return $default;
            }

            return sprintf( 'rgba(%d, %d, %d, %s)', $red, $green, $blue, $alpha_component );
        }

        $hex_color = sanitize_hex_color( $color );
        if ( $hex_color ) {
            return $hex_color;
        }

        return $default;
    }
}

if ( ! function_exists( 'my_articles_normalize_internal_url' ) ) {
    /**
     * Sanitize a URL while ensuring it targets the current site domain.
     *
     * @param string $url       Raw URL that should be validated.
     * @param string $site_home Optional site URL used to validate host and scheme.
     *
     * @return string A sanitized URL when valid for the current site, an empty string otherwise.
     */
    function my_articles_normalize_internal_url( $url, $site_home = '' ) {
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

        if ( '' === $site_home ) {
            $site_home = home_url();
        }

        $site_parts = wp_parse_url( $site_home );
        if ( false === $site_parts ) {
            return '';
        }

        $site_host   = isset( $site_parts['host'] ) ? strtolower( (string) $site_parts['host'] ) : '';
        $site_scheme = isset( $site_parts['scheme'] ) ? strtolower( (string) $site_parts['scheme'] ) : '';

        $candidate_parts = wp_parse_url( $clean_url );
        if ( false === $candidate_parts ) {
            return '';
        }

        $candidate_host   = isset( $candidate_parts['host'] ) ? strtolower( (string) $candidate_parts['host'] ) : '';
        $candidate_scheme = isset( $candidate_parts['scheme'] ) ? strtolower( (string) $candidate_parts['scheme'] ) : '';

        if ( '' === $candidate_host && '' === $candidate_scheme && '' !== $site_host ) {
            $relative_path = isset( $candidate_parts['path'] ) ? (string) $candidate_parts['path'] : '';
            $query         = isset( $candidate_parts['query'] ) ? (string) $candidate_parts['query'] : '';

            $base_scheme = '' !== $site_scheme ? $site_scheme . '://' : '';
            $base_host   = isset( $site_parts['host'] ) ? (string) $site_parts['host'] : '';
            $base_port   = isset( $site_parts['port'] ) ? ':' . $site_parts['port'] : '';
            $base_path   = isset( $site_parts['path'] ) ? (string) $site_parts['path'] : '';

            if ( '' === $relative_path ) {
                $path = $base_path;
            } elseif ( '/' === substr( $relative_path, 0, 1 ) ) {
                $path = $relative_path;
            } else {
                if ( '' === $base_path ) {
                    $path = '/' . ltrim( $relative_path, '/' );
                } else {
                    $trimmed_base = '/' === substr( $base_path, -1 ) ? rtrim( $base_path, '/' ) : $base_path;
                    $path         = $trimmed_base . '/' . ltrim( $relative_path, '/' );
                }
            }

            if ( '' !== $base_scheme ) {
                $clean_url = $base_scheme . $base_host . $base_port;
            } elseif ( '' !== $base_host ) {
                $clean_url = '//' . $base_host . $base_port;
            } else {
                $clean_url = '';
            }

            if ( '' !== $path ) {
                if ( '/' !== substr( $path, 0, 1 ) ) {
                    $clean_url .= '/' . $path;
                } else {
                    $clean_url .= $path;
                }
            }

            if ( '' !== $query ) {
                $clean_url .= '?' . $query;
            }

            $candidate_parts = wp_parse_url( $clean_url );
            if ( false === $candidate_parts ) {
                return '';
            }

            $candidate_host   = isset( $candidate_parts['host'] ) ? strtolower( (string) $candidate_parts['host'] ) : '';
            $candidate_scheme = isset( $candidate_parts['scheme'] ) ? strtolower( (string) $candidate_parts['scheme'] ) : '';
        }

        if ( '' !== $site_host ) {
            if ( '' === $candidate_host || $candidate_host !== $site_host ) {
                return '';
            }
        }

        if ( '' !== $site_scheme ) {
            if ( '' !== $candidate_scheme && $candidate_scheme !== $site_scheme ) {
                return '';
            }
        }

        return $clean_url;
    }
}

if ( ! function_exists( 'my_articles_get_selectable_post_types' ) ) {
    /**
     * Retrieve the list of selectable post types for the plugin.
     *
     * Attachments are intentionally excluded because they do not represent
     * regular content entries and would lead to unexpected behaviour in the
     * module rendering.
     *
     * @return array<string, WP_Post_Type> Associative array of post type objects keyed by their name.
     */
    function my_articles_get_selectable_post_types() {
        $post_types = get_post_types( [ 'public' => true ], 'objects' );

        if ( ! is_array( $post_types ) ) {
            $post_types = array();
        }

        unset( $post_types['attachment'] );

        return $post_types;
    }
}

if ( ! function_exists( 'my_articles_get_cache_tracked_post_types' ) ) {
    /**
     * Retrieve the list of post types that should trigger a cache invalidation.
     *
     * Extensions can filter the list via the `my_articles_cache_tracked_post_types`
     * hook in order to register their custom post types.
     *
     * @return string[] Array of sanitized post type identifiers.
     */
    function my_articles_get_cache_tracked_post_types() {
        $available_post_types = my_articles_get_selectable_post_types();

        $default_post_types = array_keys( $available_post_types );

        if ( empty( $default_post_types ) ) {
            $default_post_types = array( 'post' );
        }

        $default_post_types[] = 'mon_affichage';
        $default_post_types   = array_values( array_unique( $default_post_types ) );

        /**
         * Filter the list of post types that should invalidate the cache namespace.
         *
         * @param string[] $post_types List of post types tracked for cache invalidation.
         */
        $tracked_post_types = apply_filters( 'my_articles_cache_tracked_post_types', $default_post_types );

        if ( ! is_array( $tracked_post_types ) ) {
            $tracked_post_types = $default_post_types;
        }

        $sanitized_post_types = array();

        foreach ( $tracked_post_types as $post_type ) {
            $post_type = sanitize_key( $post_type );

            if ( '' === $post_type ) {
                continue;
            }

            $sanitized_post_types[ $post_type ] = $post_type;
        }

        return array_values( $sanitized_post_types );
    }
}

if ( ! function_exists( 'my_articles_normalize_post_type' ) ) {
    /**
     * Ensure a post type is valid for the plugin and provide a safe fallback.
     *
     * @param string $post_type Raw post type value.
     *
     * @return string A valid post type value supported by the plugin.
     */
    function my_articles_normalize_post_type( $post_type ) {
        $post_type = sanitize_key( (string) $post_type );

        $available_post_types = my_articles_get_selectable_post_types();

        if ( isset( $available_post_types[ $post_type ] ) ) {
            return $post_type;
        }

        if ( isset( $available_post_types['post'] ) ) {
            return 'post';
        }

        $available_post_type_keys = array_keys( $available_post_types );
        if ( ! empty( $available_post_type_keys ) ) {
            return (string) reset( $available_post_type_keys );
        }

        return post_type_exists( 'post' ) ? 'post' : $post_type;
    }
}

if ( ! function_exists( 'my_articles_calculate_total_pages' ) ) {
    /**
     * Calculate the total number of pages required when pinned posts appear on the first page.
     *
     * The first page contains as many pinned posts as possible and fills the remaining slots
     * with regular posts. Any leftover pinned posts are carried over before regular posts on
     * subsequent pages.
     *
     * @param int   $total_pinned_posts  Total number of pinned posts matching the query.
     * @param int   $total_regular_posts Number of available regular posts.
     * @param int   $posts_per_page      Posts per page setting.
     * @param array $context             Optional context to enrich the calculation. Supported keys:
     *                                   - `current_page` (int)    : Page for which the calculation is made.
     *                                   - `override_page_size` (int) : Force a specific page size for the
     *                                                                  computation.
     *                                   - `unlimited_page_size` (int) : Page size to use when the listing
     *                                                                   is configured as “unlimited”.
     *                                   - `analytics_page_size` (int) : Page size used to project analytics
     *                                                                   metrics (fallback to
     *                                                                   `unlimited_page_size`).
     *
     * @return array{
     *     total_pages:int,
     *     next_page:int,
     *     meta:array{
     *         requested_page:int,
     *         current_page:int,
     *         is_unbounded:bool,
     *         page_size:int,
     *         effective_page_size:int,
     *         total_items:int,
     *         total_pinned:int,
     *         total_regular:int,
     *         remaining_items:int,
     *         remaining_pinned:int,
     *         remaining_regular:int,
     *         remaining_pages:int,
     *         has_more_items:bool,
     *         first_page:array{capacity:int,pinned:int,regular:int},
     *         projected_page_size:int,
     *         projected_total_pages:int,
     *         projected_remaining_pages:int,
     *         projected_remaining_items:int,
     *     }
     * }
     */
    function my_articles_calculate_total_pages( $total_pinned_posts, $total_regular_posts, $posts_per_page, $context = array() ) {
        $total_pinned_posts  = max( 0, (int) $total_pinned_posts );
        $total_regular_posts = max( 0, (int) $total_regular_posts );
        $posts_per_page      = (int) $posts_per_page;
        $context             = is_array( $context ) ? $context : array();

        $total_items = $total_pinned_posts + $total_regular_posts;

        $override_page_size  = isset( $context['override_page_size'] ) ? max( 0, (int) $context['override_page_size'] ) : null;
        $unlimited_page_size = isset( $context['unlimited_page_size'] ) ? max( 0, (int) $context['unlimited_page_size'] ) : 0;
        $analytics_page_size = isset( $context['analytics_page_size'] ) ? max( 0, (int) $context['analytics_page_size'] ) : 0;

        if ( null !== $override_page_size ) {
            $posts_per_page = $override_page_size;
        }

        $raw_current_page = isset( $context['current_page'] ) ? (int) $context['current_page'] : 1;
        if ( $raw_current_page < 1 ) {
            $raw_current_page = 1;
        }

        $is_unbounded = $posts_per_page <= 0;
        $page_size    = $is_unbounded ? 0 : $posts_per_page;

        $meta = array(
            'requested_page'          => $raw_current_page,
            'current_page'            => 0,
            'is_unbounded'            => $is_unbounded,
            'page_size'               => max( 0, $posts_per_page ),
            'effective_page_size'     => $page_size,
            'total_items'             => $total_items,
            'total_pinned'            => $total_pinned_posts,
            'total_regular'           => $total_regular_posts,
            'remaining_items'         => 0,
            'remaining_pinned'        => 0,
            'remaining_regular'       => 0,
            'remaining_pages'         => 0,
            'has_more_items'          => false,
            'first_page'              => array(
                'capacity' => $page_size > 0 ? $page_size : $total_items,
                'pinned'   => 0,
                'regular'  => 0,
            ),
            'projected_page_size'      => 0,
            'projected_total_pages'    => 0,
            'projected_remaining_pages'=> 0,
            'projected_remaining_items'=> 0,
        );

        if ( 0 === $total_items ) {
            $result = array(
                'total_pages' => 0,
                'next_page'   => 0,
                'meta'        => $meta,
            );

            return apply_filters( 'my_articles_calculate_total_pages', $result, $total_pinned_posts, $total_regular_posts, $posts_per_page, $context );
        }

        if ( $page_size > 0 ) {
            $pinned_on_first_page = min( $total_pinned_posts, $page_size );
        } else {
            $pinned_on_first_page = $total_pinned_posts;
        }

        $regular_first_page_capacity = $page_size > 0 ? max( 0, $page_size - $pinned_on_first_page ) : $total_regular_posts;
        $regular_on_first_page       = min( $total_regular_posts, $regular_first_page_capacity );

        $remaining_pinned_posts  = max( 0, $total_pinned_posts - $pinned_on_first_page );
        $remaining_regular_posts = max( 0, $total_regular_posts - $regular_on_first_page );
        $remaining_after_first   = $remaining_pinned_posts + $remaining_regular_posts;

        if ( $page_size > 0 ) {
            $additional_pages = (int) ceil( $remaining_after_first / $page_size );
            $total_pages      = 1 + ( $remaining_after_first > 0 ? $additional_pages : 0 );
        } else {
            $total_pages = 0;
        }

        $resolved_current_page = $raw_current_page;
        if ( $total_pages > 0 ) {
            $resolved_current_page = min( $resolved_current_page, $total_pages );
        } elseif ( $total_items > 0 ) {
            $resolved_current_page = 1;
        }

        $pages_after_first = $resolved_current_page > 1 ? $resolved_current_page - 1 : 0;

        if ( $page_size > 0 ) {
            $remaining_pinned_after_current  = $remaining_pinned_posts;
            $remaining_regular_after_current = $remaining_regular_posts;

            if ( $pages_after_first > 0 ) {
                $pinned_consumed_post_first = min( $remaining_pinned_posts, $pages_after_first * $page_size );
                $remaining_pinned_after_current = max( 0, $remaining_pinned_posts - $pinned_consumed_post_first );

                $regular_capacity_after_pinned = max( 0, ( $pages_after_first * $page_size ) - $pinned_consumed_post_first );
                $regular_consumed_post_first   = min( $remaining_regular_posts, $regular_capacity_after_pinned );
                $remaining_regular_after_current = max( 0, $remaining_regular_posts - $regular_consumed_post_first );
            }
        } else {
            $remaining_pinned_after_current  = 0;
            $remaining_regular_after_current = 0;
        }

        $remaining_items_after_current = $remaining_pinned_after_current + $remaining_regular_after_current;
        $has_more_items                = $remaining_items_after_current > 0;
        $remaining_pages               = ( $total_pages > 0 ) ? max( 0, $total_pages - $resolved_current_page ) : 0;

        if ( $analytics_page_size <= 0 ) {
            $analytics_page_size = $unlimited_page_size;
        }

        if ( $analytics_page_size <= 0 ) {
            $analytics_page_size = $page_size;
        }

        if ( $analytics_page_size > 0 ) {
            $projected_total_pages = (int) ceil( $total_items / $analytics_page_size );
            $projection_current_page = $resolved_current_page > 0 ? $resolved_current_page : 1;

            if ( $projected_total_pages > 0 ) {
                $projection_current_page = min( $projection_current_page, $projected_total_pages );
            } else {
                $projection_current_page = 0;
            }

            $projected_consumed_items = min( $total_items, $projection_current_page * $analytics_page_size );
            $projected_remaining_items = max( 0, $total_items - $projected_consumed_items );
            $projected_remaining_pages = ( $projected_total_pages > 0 ) ? max( 0, $projected_total_pages - $projection_current_page ) : 0;
        } else {
            $projected_total_pages   = $total_pages;
            $projected_remaining_pages = $remaining_pages;
            $projected_remaining_items = $remaining_items_after_current;
        }

        $next_page = 0;
        if ( $page_size > 0 && $total_pages > 0 && $resolved_current_page < $total_pages ) {
            $next_page = min( $resolved_current_page + 1, $total_pages );
        }

        $meta['current_page']             = $resolved_current_page;
        $meta['remaining_items']          = $remaining_items_after_current;
        $meta['remaining_pinned']         = $remaining_pinned_after_current;
        $meta['remaining_regular']        = $remaining_regular_after_current;
        $meta['remaining_pages']          = $remaining_pages;
        $meta['has_more_items']           = $has_more_items;
        $meta['first_page']['pinned']     = $pinned_on_first_page;
        $meta['first_page']['regular']    = $regular_on_first_page;
        $meta['projected_page_size']      = $analytics_page_size;
        $meta['projected_total_pages']    = $projected_total_pages;
        $meta['projected_remaining_pages']= $projected_remaining_pages;
        $meta['projected_remaining_items']= $projected_remaining_items;

        $result = array(
            'total_pages' => $total_pages,
            'next_page'   => $next_page,
            'meta'        => $meta,
        );

        return apply_filters( 'my_articles_calculate_total_pages', $result, $total_pinned_posts, $total_regular_posts, $posts_per_page, $context );
    }
}

if ( ! function_exists( 'my_articles_get_instrumentation_settings' ) ) {
    /**
     * Retrieve instrumentation settings saved in the plugin options.
     *
     * @return array{enabled:bool,channel:string} Normalized instrumentation configuration.
     */
    function my_articles_get_instrumentation_settings() {
        $options = (array) get_option( 'my_articles_options', array() );

        $enabled = ! empty( $options['instrumentation_enabled'] );
        $allowed_channels = array( 'console', 'dataLayer', 'fetch' );

        $channel = isset( $options['instrumentation_channel'] ) ? (string) $options['instrumentation_channel'] : 'console';

        if ( ! in_array( $channel, $allowed_channels, true ) ) {
            $channel = 'console';
        }

        return array(
            'enabled' => $enabled,
            'channel' => $channel,
        );
    }
}

if ( ! function_exists( 'my_articles_is_instrumentation_enabled' ) ) {
    /**
     * Determine whether instrumentation is enabled globally for the plugin.
     *
     * @return bool
     */
    function my_articles_is_instrumentation_enabled() {
        $settings = my_articles_get_instrumentation_settings();

        return ! empty( $settings['enabled'] );
    }
}
