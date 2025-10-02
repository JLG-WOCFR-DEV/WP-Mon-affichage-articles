<?php
/**
 * Migration script to ensure existing modules use the “Personnalisé” design preset.
 *
 * Usage: wp eval-file scripts/migrations/assign-design-preset.php
 */

if ( ! defined( 'WPINC' ) ) {
    exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    fwrite( STDERR, "This script must be executed from WP-CLI using `wp eval-file`\n" );
    return;
}

$modules = get_posts(
    array(
        'post_type'      => 'mon_affichage',
        'post_status'    => 'any',
        'numberposts'    => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    )
);

if ( empty( $modules ) ) {
    WP_CLI::log( 'No mon_affichage posts found.' );
    return;
}

$updated = 0;

foreach ( $modules as $post_id ) {
    $settings = get_post_meta( $post_id, '_my_articles_settings', true );

    if ( ! is_array( $settings ) ) {
        continue;
    }

    if ( isset( $settings['design_preset'] ) && '' !== $settings['design_preset'] ) {
        continue;
    }

    $settings['design_preset'] = 'custom';
    update_post_meta( $post_id, '_my_articles_settings', $settings );
    $updated++;
}

WP_CLI::success( sprintf( 'Design preset set to "custom" for %d module(s).', $updated ) );
