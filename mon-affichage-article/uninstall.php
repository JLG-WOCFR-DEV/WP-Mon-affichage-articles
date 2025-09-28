<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'my_articles_options' );

$query_args = array(
    'post_type'      => 'mon_affichage',
    'posts_per_page' => 100,
    'post_status'    => 'any',
    'fields'         => 'ids',
    'no_found_rows'  => true,
);

while ( true ) {
    $posts_query = new WP_Query( $query_args );
    $posts       = $posts_query->posts;

    if ( empty( $posts ) ) {
        wp_reset_postdata();
        break;
    }

    foreach ( $posts as $post_id ) {
        wp_delete_post( $post_id, true );
    }

    wp_reset_postdata();
}
