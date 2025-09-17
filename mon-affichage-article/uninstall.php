<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'my_articles_options' );

$posts = get_posts(
    array(
        'post_type'      => 'mon_affichage',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    )
);

if ( ! empty( $posts ) ) {
    foreach ( $posts as $post_id ) {
        wp_delete_post( $post_id, true );
    }
}
