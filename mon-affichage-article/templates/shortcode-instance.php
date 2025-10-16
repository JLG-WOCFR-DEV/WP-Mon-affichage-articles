<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wrapper_attribute_string = isset( $wrapper_attribute_string ) ? (string) $wrapper_attribute_string : '';
$search_form_html         = isset( $search_form_html ) ? (string) $search_form_html : '';
$filter_nav_html          = isset( $filter_nav_html ) ? (string) $filter_nav_html : '';
$results_html             = isset( $results_html ) ? (string) $results_html : '';
$pagination_html          = isset( $pagination_html ) ? (string) $pagination_html : '';
$debug_html               = isset( $debug_html ) ? (string) $debug_html : '';
?>
<div <?php echo $wrapper_attribute_string; ?>>
    <?php if ( '' !== $search_form_html ) : ?>
        <?php echo $search_form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php endif; ?>

    <?php if ( '' !== $filter_nav_html ) : ?>
        <?php echo $filter_nav_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php endif; ?>

    <?php echo $results_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <?php if ( '' !== $pagination_html ) : ?>
        <?php echo $pagination_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php endif; ?>

    <?php if ( '' !== $debug_html ) : ?>
        <?php echo $debug_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php endif; ?>
</div>
