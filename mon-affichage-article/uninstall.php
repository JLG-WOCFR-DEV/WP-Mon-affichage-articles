<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'my_articles_options' );
delete_option( 'my_articles_cache_namespace' );

if ( function_exists( 'wp_cache_delete' ) ) {
    my_articles_uninstall_purge_response_cache_group();
}

my_articles_uninstall_delete_response_transients();

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

/**
 * Purge all entries stored in the my_articles_response object cache group.
 */
if ( ! function_exists( 'my_articles_uninstall_purge_response_cache_group' ) ) {
function my_articles_uninstall_purge_response_cache_group() {
    if ( ! isset( $GLOBALS['wp_object_cache'] ) ) {
        return;
    }

    $cache_object = $GLOBALS['wp_object_cache'];

    if ( is_object( $cache_object ) && method_exists( $cache_object, 'delete_group' ) ) {
        $cache_object->delete_group( 'my_articles_response' );

        return;
    }

    if ( ! is_object( $cache_object ) || ! property_exists( $cache_object, 'cache' ) ) {
        return;
    }

    $groups = $cache_object->cache;

    if ( ! is_array( $groups ) || empty( $groups['my_articles_response'] ) ) {
        return;
    }

    $group_entries = $groups['my_articles_response'];

    if ( ! is_array( $group_entries ) ) {
        return;
    }

    foreach ( array_keys( $group_entries ) as $cache_key ) {
        wp_cache_delete( $cache_key, 'my_articles_response' );
    }
}
}

/**
 * Delete all transients created for the cached responses.
 */
if ( ! function_exists( 'my_articles_uninstall_delete_response_transients' ) ) {
function my_articles_uninstall_delete_response_transients() {
    global $wpdb;

    if ( isset( $wpdb ) && is_object( $wpdb ) ) {
        $like_fragment = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( 'my_articles_' ) : addcslashes( 'my_articles_', '_%\\' );
        $like_fragment .= '%';

        if ( method_exists( $wpdb, 'prepare' ) ) {
            $options_table = isset( $wpdb->options ) ? $wpdb->options : $wpdb->prefix . 'options';
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$options_table} WHERE option_name LIKE %s OR option_name LIKE %s",
                    '_transient_' . $like_fragment,
                    '_transient_timeout_' . $like_fragment
                )
            );

            if ( function_exists( 'is_multisite' ) && is_multisite() && isset( $wpdb->sitemeta ) ) {
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
                        '_site_transient_' . $like_fragment,
                        '_site_transient_timeout_' . $like_fragment
                    )
                );
            }
        }

        return;
    }

    if ( function_exists( 'delete_option' ) && function_exists( 'wp_load_alloptions' ) ) {
        $all_options = wp_load_alloptions();

        foreach ( array_keys( $all_options ) as $option_name ) {
            if ( strpos( $option_name, '_transient_my_articles_' ) === 0 || strpos( $option_name, '_transient_timeout_my_articles_' ) === 0 ) {
                delete_option( $option_name );
            }
        }
    }
}
}
