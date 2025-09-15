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
