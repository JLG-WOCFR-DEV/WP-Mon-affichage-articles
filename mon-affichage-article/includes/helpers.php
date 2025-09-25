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

if ( ! function_exists( 'my_articles_sanitize_posts_per_page' ) ) {
    /**
     * Sanitize a posts per page value while allowing the unlimited flag.
     *
     * The metabox and the settings page both interpret the value "0" as an
     * instruction to display an unlimited number of posts. The sanitizer needs
     * to keep that value intact instead of falling back to the default.
     *
     * @param mixed $value   Raw value coming from user input.
     * @param int   $default Default value to use when no input is provided.
     *
     * @return int A non-negative integer (0 preserves the unlimited behaviour).
     */
    function my_articles_sanitize_posts_per_page( $value, $default = 10 ) {
        if ( '' === $value || null === $value ) {
            return max( 0, (int) $default );
        }

        return max( 0, absint( $value ) );
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

        $site_host = wp_parse_url( $site_home, PHP_URL_HOST );
        $site_host = is_string( $site_host ) ? strtolower( $site_host ) : '';

        $site_scheme = wp_parse_url( $site_home, PHP_URL_SCHEME );
        $site_scheme = is_string( $site_scheme ) ? strtolower( $site_scheme ) : '';

        if ( '' !== $site_host ) {
            $candidate_host = wp_parse_url( $clean_url, PHP_URL_HOST );
            $candidate_host = is_string( $candidate_host ) ? strtolower( $candidate_host ) : '';

            if ( '' === $candidate_host || $candidate_host !== $site_host ) {
                return '';
            }
        }

        if ( '' !== $site_scheme ) {
            $candidate_scheme = wp_parse_url( $clean_url, PHP_URL_SCHEME );
            $candidate_scheme = is_string( $candidate_scheme ) ? strtolower( $candidate_scheme ) : '';

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
     * @param int $total_pinned_posts   Total number of pinned posts matching the query.
     * @param int $total_regular_posts  Number of available regular posts.
     * @param int $posts_per_page       Posts per page setting.
     *
     * @return array{
     *     total_pages:int,
     *     next_page:int,
     * }
     */
    function my_articles_calculate_total_pages( $total_pinned_posts, $total_regular_posts, $posts_per_page ) {
        $total_pinned_posts  = max( 0, (int) $total_pinned_posts );
        $total_regular_posts = max( 0, (int) $total_regular_posts );
        $posts_per_page      = max( 0, (int) $posts_per_page );

        $total_items = $total_pinned_posts + $total_regular_posts;

        if ( 0 === $total_items ) {
            return [
                'total_pages' => 0,
                'next_page'   => 0,
            ];
        }

        if ( 0 === $posts_per_page ) {
            return [
                'total_pages' => 1,
                'next_page'   => 0,
            ];
        }

        $pinned_on_first_page   = min( $total_pinned_posts, $posts_per_page );
        $remaining_pinned_posts = max( 0, $total_pinned_posts - $pinned_on_first_page );

        $regular_first_page_capacity = max( 0, $posts_per_page - $pinned_on_first_page );
        $regular_on_first_page       = min( $total_regular_posts, $regular_first_page_capacity );
        $remaining_regular_posts     = max( 0, $total_regular_posts - $regular_on_first_page );

        $remaining_items  = $remaining_pinned_posts + $remaining_regular_posts;
        $additional_pages = (int) ceil( $remaining_items / $posts_per_page );

        $total_pages = 1 + ( $remaining_items > 0 ? $additional_pages : 0 );

        return [
            'total_pages' => $total_pages,
            'next_page'   => $total_pages > 1 ? 2 : 0,
        ];
    }
}
