<?php
/**
 * Article item partial.
 *
 * @var My_Articles_Shortcode $shortcode
 * @var array                 $options
 * @var bool                  $is_pinned
 * @var bool                  $wrap_slides
 */

if ( ! isset( $shortcode ) || ! is_object( $shortcode ) || ! method_exists( $shortcode, 'render_article_item' ) ) {
    return;
}

ob_start();
$shortcode->render_article_item( $options, ! empty( $is_pinned ) );
$item_markup = (string) ob_get_clean();

if ( '' === $item_markup ) {
    echo $item_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template renders trusted markup.
    return;
}

if ( ! empty( $wrap_slides ) ) {
    echo '<div class="swiper-slide">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $item_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    return;
}

echo $item_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
