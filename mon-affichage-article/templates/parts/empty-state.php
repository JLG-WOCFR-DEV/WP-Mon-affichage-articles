<?php
/**
 * Empty state partial.
 *
 * @var My_Articles_Shortcode $shortcode
 * @var array                 $options
 * @var bool                  $wrap_slides
 */

if ( ! isset( $shortcode ) || ! is_object( $shortcode ) ) {
    return;
}

if ( ! empty( $wrap_slides ) && method_exists( $shortcode, 'get_empty_state_slide_html' ) ) {
    echo $shortcode->get_empty_state_slide_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    return;
}

if ( method_exists( $shortcode, 'get_empty_state_html' ) ) {
    echo $shortcode->get_empty_state_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
