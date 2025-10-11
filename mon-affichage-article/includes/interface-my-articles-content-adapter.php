<?php
/**
 * Content adapter interface to fetch external collections.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( ! interface_exists( 'My_Articles_Content_Adapter_Interface' ) ) {
    /**
     * Interface implemented by external content adapter providers.
     */
    interface My_Articles_Content_Adapter_Interface {
        /**
         * Retrieve items to inject into the rendered collection.
         *
         * Implementations may return `WP_Post` instances, raw HTML strings
         * or nested arrays matching the format consumed by
         * `My_Articles_Shortcode::collect_content_adapter_items()`.
         *
         * @param array<string, mixed> $options Normalized shortcode options.
         * @param array<string, mixed> $config  Adapter configuration provided by the editor.
         * @param array<string, mixed> $context Runtime context (instance id, render limit, etc.).
         *
         * @return mixed Adapter payload (WP_Post|array|WP_Query|string).
         */
        public function get_items( array $options, array $config = array(), array $context = array() );
    }
}
