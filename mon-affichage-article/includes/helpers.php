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

if ( ! function_exists( 'my_articles_calculate_total_pages' ) ) {
    /**
     * Calculate the total number of pages required when pinned posts appear on the first page.
     *
     * The first page contains all pinned posts plus as many regular posts as needed to reach
     * the configured posts per page. Subsequent pages only contain regular posts.
     *
     * @param int $pinned_posts_found   Number of pinned posts displayed on the first page.
     * @param int $total_regular_posts  Number of available regular posts.
     * @param int $posts_per_page       Posts per page setting.
     *
     * @return array{
     *     total_pages:int,
     *     next_page:int,
     * }
     */
    function my_articles_calculate_total_pages( $pinned_posts_found, $total_regular_posts, $posts_per_page ) {
        $pinned_posts_found  = max( 0, (int) $pinned_posts_found );
        $total_regular_posts = max( 0, (int) $total_regular_posts );
        $posts_per_page      = max( 0, (int) $posts_per_page );

        $regular_first_page_capacity = $posts_per_page > 0
            ? max( 0, $posts_per_page - $pinned_posts_found )
            : 0;
        $regular_on_first_page = min( $total_regular_posts, $regular_first_page_capacity );
        $remaining_regular     = max( 0, $total_regular_posts - $regular_on_first_page );
        $additional_pages      = $posts_per_page > 0
            ? (int) ceil( $remaining_regular / $posts_per_page )
            : 0;

        $total_pages = ( $pinned_posts_found + $total_regular_posts ) > 0
            ? 1 + $additional_pages
            : 0;

        return [
            'total_pages' => $total_pages,
            'next_page'   => $total_pages > 1 ? 2 : 0,
        ];
    }
}
