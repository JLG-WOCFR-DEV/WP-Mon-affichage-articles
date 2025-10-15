<?php
// Fichier: includes/class-my-articles-shortcode.php

if ( ! defined( 'WPINC' ) ) {
    die;
}

class My_Articles_Shortcode {

    private static $instance;
    private static $lazysizes_enqueued = false;
    private static $lazyload_fallback_added = false;
    private static $normalized_options_cache = array();
    private static $matching_pinned_ids_cache = array();
    private static $design_presets = null;
    private static $thumbnail_aspect_ratio_choices = null;
    private static $last_render_summary = array();

    /**
     * @var My_Articles_Shortcode_Data_Preparer
     */
    private $data_preparer;

    public static function get_thumbnail_aspect_ratio_choices() {
        if ( null === self::$thumbnail_aspect_ratio_choices ) {
            self::$thumbnail_aspect_ratio_choices = array(
                '1'    => __( 'Carré (1 :1)', 'mon-articles' ),
                '4/3'  => __( 'Classique (4 :3)', 'mon-articles' ),
                '3/2'  => __( 'Photo (3 :2)', 'mon-articles' ),
                '16/9' => __( 'Panoramique (16 :9)', 'mon-articles' ),
            );
        }

        return self::$thumbnail_aspect_ratio_choices;
    }

    public static function get_allowed_thumbnail_aspect_ratios() {
        return array_keys( self::get_thumbnail_aspect_ratio_choices() );
    }

    public static function get_default_thumbnail_aspect_ratio() {
        return '16/9';
    }

    public static function get_design_presets() {
        if ( null !== self::$design_presets ) {
            return self::$design_presets;
        }

        $presets = self::load_design_presets_from_manifest();

        self::$design_presets = apply_filters( 'my_articles_design_presets', $presets );

        if ( ! is_array( self::$design_presets ) ) {
            self::$design_presets = $presets;
        }

        return self::$design_presets;
    }

    public static function get_design_presets_manifest_path() {
        if ( class_exists( 'My_Articles_Preset_Registry' ) ) {
            return My_Articles_Preset_Registry::get_instance()->get_index_path();
        }

        $base_dir = defined( 'MY_ARTICLES_PLUGIN_DIR' ) ? MY_ARTICLES_PLUGIN_DIR : dirname( __FILE__, 2 ) . '/';

        if ( function_exists( 'trailingslashit' ) ) {
            return trailingslashit( $base_dir ) . 'config/design-presets/index.json';
        }

        return rtrim( $base_dir, '/\\' ) . '/config/design-presets/index.json';
    }

    public static function flush_design_presets_cache() {
        self::$design_presets = null;

        if ( class_exists( 'My_Articles_Preset_Registry' ) ) {
            My_Articles_Preset_Registry::get_instance()->flush_cache();
        }
    }

    public static function get_last_render_summary() {
        return self::$last_render_summary;
    }

    private static function ensure_lazyload_fallback_script() {
        if ( self::$lazyload_fallback_added ) {
            return;
        }

        if ( ! function_exists( 'wp_add_inline_script' ) ) {
            return;
        }

        if ( function_exists( 'wp_script_is' ) && ! wp_script_is( 'lazysizes', 'registered' ) && ! wp_script_is( 'lazysizes', 'enqueued' ) ) {
            return;
        }

        $fallback_script = <<<'JS'
(function(){
    function applyFallback(img){
        if(!img || img.classList.contains('lazyloaded')){
            return;
        }

        var dataSrc = img.getAttribute('data-src');
        var dataSrcset = img.getAttribute('data-srcset');

        if(dataSrc){
            img.setAttribute('src', dataSrc);
        }

        if(dataSrcset){
            img.setAttribute('srcset', dataSrcset);
        }

        var dataSizes = img.getAttribute('data-sizes');

        if(dataSizes && !img.getAttribute('sizes')){
            img.setAttribute('sizes', dataSizes);
        }

        img.classList.remove('lazyload');
        img.classList.add('lazyloaded');

        img.removeAttribute('data-src');
        img.removeAttribute('data-srcset');
        img.removeAttribute('data-sizes');
    }

    function runFallback(){
        if(window.lazySizes){
            return;
        }

        var images = document.querySelectorAll('img.lazyload[data-src]');

        if(!images.length){
            return;
        }

        if(typeof images.forEach === 'function'){
            images.forEach(applyFallback);
        }else{
            Array.prototype.forEach.call(images, applyFallback);
        }
    }

    function scheduleFallback(){
        window.setTimeout(runFallback, 400);
    }

    if(document.readyState === 'complete'){
        scheduleFallback();
    }else{
        window.addEventListener('load', scheduleFallback);
    }
})();
JS;

        wp_add_inline_script( 'lazysizes', $fallback_script, 'after' );
        self::$lazyload_fallback_added = true;
    }

    private static function load_design_presets_from_manifest() {
        $fallback = self::get_fallback_design_presets();

        if ( class_exists( 'My_Articles_Preset_Registry' ) ) {
            $registry = My_Articles_Preset_Registry::get_instance();
            $presets  = $registry->get_presets_for_shortcode();

            if ( is_array( $presets ) && ! empty( $presets ) ) {
                return $presets;
            }
        }

        return $fallback;
    }

    private static function get_fallback_design_presets() {
        return array(
            'custom' => array(
                'label'       => __( 'Personnalisé', 'mon-articles' ),
                'locked'      => false,
                'values'      => array(),
                'description' => __( 'Conservez vos propres réglages de couleurs et d’espacements.', 'mon-articles' ),
                'tags'        => array(
                    __( 'Libre', 'mon-articles' ),
                    __( 'Personnalisé', 'mon-articles' ),
                ),
            ),
        );
    }

    public static function get_design_preset( $preset_id ) {
        $presets = self::get_design_presets();

        if ( isset( $presets[ $preset_id ] ) ) {
            return $presets[ $preset_id ];
        }

        return null;
    }

    public static function get_design_preset_values( $preset_id ) {
        $preset = self::get_design_preset( $preset_id );

        if ( ! is_array( $preset ) ) {
            return array();
        }

        if ( isset( $preset['values'] ) && is_array( $preset['values'] ) ) {
            return $preset['values'];
        }

        return array();
    }

    private static function build_normalized_options_cache_key( $raw_options, $context ) {
        return md5( maybe_serialize( array( 'options' => $raw_options, 'context' => $context ) ) );
    }

    private static function build_matching_pinned_cache_key( array $options ) {
        $relevant = array(
            'post_type'                 => $options['post_type'] ?? '',
            'pinned_posts'              => $options['pinned_posts'] ?? array(),
            'pinned_posts_ignore_filter' => $options['pinned_posts_ignore_filter'] ?? 0,
            'resolved_taxonomy'         => $options['resolved_taxonomy'] ?? '',
            'term'                      => $options['term'] ?? '',
            'exclude_post_ids'          => $options['exclude_post_ids'] ?? array(),
            'search_query'              => $options['search_query'] ?? '',
            'active_tax_filters'        => array_map( array( __CLASS__, 'build_filter_key' ), $options['active_tax_filters'] ?? array() ),
        );

        return md5( maybe_serialize( $relevant ) );
    }

    private static function build_filter_key( $filter ) {
        if ( ! is_array( $filter ) ) {
            return '';
        }

        $taxonomy = isset( $filter['taxonomy'] ) ? sanitize_key( (string) $filter['taxonomy'] ) : '';
        $slug     = isset( $filter['slug'] ) ? sanitize_title( (string) $filter['slug'] ) : '';

        if ( '' === $taxonomy || '' === $slug ) {
            return '';
        }

        return $taxonomy . '|' . $slug;
    }

    public static function sanitize_filter_pairs( $raw_filters, $post_type = '' ) {
        if ( is_string( $raw_filters ) ) {
            $decoded = json_decode( $raw_filters, true );

            if ( is_array( $decoded ) ) {
                $raw_filters = $decoded;
            }
        }

        if ( ! is_array( $raw_filters ) ) {
            return array();
        }

        $post_type = my_articles_normalize_post_type( $post_type );
        $is_valid_taxonomy_for_post_type = static function( $taxonomy ) use ( $post_type ) {
            if ( '' === $taxonomy ) {
                return false;
            }

            if ( ! taxonomy_exists( $taxonomy ) ) {
                return false;
            }

            if ( '' === $post_type ) {
                return true;
            }

            return is_object_in_taxonomy( $post_type, $taxonomy );
        };

        $sanitized = array();

        foreach ( $raw_filters as $filter ) {
            if ( is_string( $filter ) ) {
                $parts = explode( ':', $filter, 2 );

                if ( 2 !== count( $parts ) ) {
                    $parts = explode( '|', $filter, 2 );
                }

                if ( 2 === count( $parts ) ) {
                    $filter = array(
                        'taxonomy' => $parts[0],
                        'slug'     => $parts[1],
                    );
                } else {
                    $filter = array();
                }
            }

            if ( ! is_array( $filter ) ) {
                continue;
            }

            $taxonomy = isset( $filter['taxonomy'] ) ? sanitize_key( (string) $filter['taxonomy'] ) : '';

            $slugs = array();

            if ( isset( $filter['slug'] ) ) {
                $slugs[] = sanitize_title( (string) $filter['slug'] );
            }

            if ( isset( $filter['slugs'] ) && is_array( $filter['slugs'] ) ) {
                foreach ( $filter['slugs'] as $candidate_slug ) {
                    if ( is_scalar( $candidate_slug ) ) {
                        $slugs[] = sanitize_title( (string) $candidate_slug );
                    }
                }
            }

            if ( isset( $filter['terms'] ) && is_array( $filter['terms'] ) ) {
                foreach ( $filter['terms'] as $candidate_slug ) {
                    if ( is_scalar( $candidate_slug ) ) {
                        $slugs[] = sanitize_title( (string) $candidate_slug );
                    }
                }
            }

            $slugs = array_values( array_filter( array_unique( $slugs ) ) );

            if ( '' === $taxonomy || empty( $slugs ) ) {
                continue;
            }

            if ( ! $is_valid_taxonomy_for_post_type( $taxonomy ) ) {
                continue;
            }

            foreach ( $slugs as $slug ) {
                if ( '' === $slug || 'all' === $slug ) {
                    continue;
                }

                $key = $taxonomy . '|' . $slug;

                $sanitized[ $key ] = array(
                    'taxonomy' => $taxonomy,
                    'slug'     => $slug,
                );
            }
        }

        return array_values( $sanitized );
    }

    public static function get_content_adapter_definitions() {
        $registry = array();

        if ( function_exists( 'my_articles_get_registered_content_adapters' ) ) {
            $registered = my_articles_get_registered_content_adapters();
            if ( is_array( $registered ) ) {
                $registry = $registered;
            }
        }

        $filtered = array();

        if ( function_exists( 'apply_filters' ) ) {
            $filtered = apply_filters( 'my_articles_content_adapters', array() );
        }

        if ( is_array( $filtered ) && ! empty( $filtered ) ) {
            $registry = array_merge( $registry, $filtered );
        }

        $normalized = array();

        foreach ( $registry as $adapter_id => $definition ) {
            $resolved_id = '';

            if ( is_string( $adapter_id ) && '' !== $adapter_id ) {
                $resolved_id = sanitize_key( $adapter_id );
            } elseif ( is_array( $definition ) && isset( $definition['id'] ) ) {
                $resolved_id = sanitize_key( (string) $definition['id'] );
            }

            if ( '' === $resolved_id ) {
                continue;
            }

            $class_name = '';
            if ( is_array( $definition ) ) {
                $callback = $definition['callback'] ?? null;
                $label    = isset( $definition['label'] ) && is_string( $definition['label'] ) ? $definition['label'] : $resolved_id;
                $description = isset( $definition['description'] ) && is_string( $definition['description'] ) ? $definition['description'] : '';
                $schema = isset( $definition['schema'] ) && is_array( $definition['schema'] ) ? $definition['schema'] : array();

                if ( isset( $definition['class'] ) && is_string( $definition['class'] ) ) {
                    $candidate_class = ltrim( $definition['class'], '\\' );
                    if ( class_exists( $candidate_class ) && interface_exists( 'My_Articles_Content_Adapter_Interface' ) && is_subclass_of( $candidate_class, 'My_Articles_Content_Adapter_Interface' ) ) {
                        $class_name = $candidate_class;
                    }
                }
            } elseif ( is_callable( $definition ) ) {
                $callback   = $definition;
                $label      = $resolved_id;
                $description = '';
                $schema      = array();
            } else {
                continue;
            }

            if ( '' === $class_name && ! is_callable( $callback ) ) {
                continue;
            }

            $normalized[ $resolved_id ] = array(
                'id'          => $resolved_id,
                'label'       => is_string( $label ) ? $label : $resolved_id,
                'description' => is_string( $description ) ? $description : '',
                'callback'    => $callback,
                'class'       => $class_name,
                'schema'      => $schema,
            );
        }

        return $normalized;
    }

    public static function get_content_adapter_definitions_for_admin() {
        $definitions = self::get_content_adapter_definitions();
        $export = array();

        foreach ( $definitions as $definition ) {
            $export[] = array(
                'id'          => $definition['id'],
                'label'       => $definition['label'],
                'description' => $definition['description'],
            );
        }

        return $export;
    }

    public static function sanitize_content_adapters( $raw_adapters ) {
        if ( is_string( $raw_adapters ) ) {
            $decoded = json_decode( $raw_adapters, true );
            if ( is_array( $decoded ) ) {
                $raw_adapters = $decoded;
            }
        }

        if ( ! is_array( $raw_adapters ) ) {
            return array();
        }

        $definitions = self::get_content_adapter_definitions();
        if ( empty( $definitions ) ) {
            return array();
        }

        $normalized = array();

        foreach ( $raw_adapters as $entry ) {
            if ( is_string( $entry ) ) {
                $decoded_entry = json_decode( $entry, true );
                if ( is_array( $decoded_entry ) ) {
                    $entry = $decoded_entry;
                }
            }

            if ( ! is_array( $entry ) ) {
                continue;
            }

            $adapter_id = '';
            if ( isset( $entry['id'] ) ) {
                $adapter_id = sanitize_key( (string) $entry['id'] );
            } elseif ( isset( $entry['adapter'] ) ) {
                $adapter_id = sanitize_key( (string) $entry['adapter'] );
            }

            if ( '' === $adapter_id || ! isset( $definitions[ $adapter_id ] ) ) {
                continue;
            }

            $config = array();
            if ( isset( $entry['config'] ) ) {
                if ( is_string( $entry['config'] ) && '' !== trim( $entry['config'] ) ) {
                    $decoded_config = json_decode( $entry['config'], true );
                    if ( is_array( $decoded_config ) ) {
                        $config = $decoded_config;
                    }
                } elseif ( is_array( $entry['config'] ) ) {
                    $config = $entry['config'];
                }
            }

            $normalized[] = array(
                'id'     => $adapter_id,
                'config' => self::sanitize_adapter_config( $config ),
            );
        }

        return $normalized;
    }

    private static function sanitize_adapter_config( $config ) {
        if ( ! is_array( $config ) ) {
            return array();
        }

        $sanitized = array();

        foreach ( $config as $key => $value ) {
            if ( is_string( $key ) ) {
                $normalized_key = sanitize_key( $key );
            } else {
                $normalized_key = $key;
            }

            if ( '' === $normalized_key && 0 !== $normalized_key ) {
                continue;
            }

            if ( is_array( $value ) ) {
                $sanitized[ $normalized_key ] = self::sanitize_adapter_config( $value );
                continue;
            }

            if ( is_bool( $value ) ) {
                $sanitized[ $normalized_key ] = $value ? 1 : 0;
                continue;
            }

            if ( is_int( $value ) || is_float( $value ) ) {
                $sanitized[ $normalized_key ] = $value + 0;
                continue;
            }

            if ( is_scalar( $value ) ) {
                $sanitized[ $normalized_key ] = sanitize_text_field( (string) $value );
            }
        }

        return $sanitized;
    }

    public static function collect_content_adapter_items( array $options, array $context = array() ) {
        $adapters = isset( $options['content_adapters'] ) && is_array( $options['content_adapters'] )
            ? $options['content_adapters']
            : array();

        if ( empty( $adapters ) ) {
            return array();
        }

        $registry = self::get_content_adapter_definitions();
        if ( empty( $registry ) ) {
            return array();
        }

        $seen_ids = array();
        if ( isset( $options['all_excluded_ids'] ) && is_array( $options['all_excluded_ids'] ) ) {
            $seen_ids = array_merge( $seen_ids, array_map( 'absint', $options['all_excluded_ids'] ) );
        }

        if ( isset( $options['exclude_post_ids'] ) && is_array( $options['exclude_post_ids'] ) ) {
            $seen_ids = array_merge( $seen_ids, array_map( 'absint', $options['exclude_post_ids'] ) );
        }

        if ( isset( $options['pinned_posts'] ) && is_array( $options['pinned_posts'] ) ) {
            $seen_ids = array_merge( $seen_ids, array_map( 'absint', $options['pinned_posts'] ) );
        }

        if ( isset( $context['rendered_pinned_ids'] ) && is_array( $context['rendered_pinned_ids'] ) ) {
            $seen_ids = array_merge( $seen_ids, array_map( 'absint', $context['rendered_pinned_ids'] ) );
        }

        $seen_ids = array_values( array_unique( array_filter( $seen_ids ) ) );

        $items = array();

        foreach ( $adapters as $entry ) {
            if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
                continue;
            }

            $adapter_id = sanitize_key( (string) $entry['id'] );

            if ( '' === $adapter_id || ! isset( $registry[ $adapter_id ] ) ) {
                continue;
            }

            $definition = $registry[ $adapter_id ];
            $callback   = isset( $definition['callback'] ) ? $definition['callback'] : null;
            $class_name = isset( $definition['class'] ) ? $definition['class'] : '';

            $config = isset( $entry['config'] ) && is_array( $entry['config'] ) ? $entry['config'] : array();

            try {
                if ( is_string( $class_name ) && '' !== $class_name && class_exists( $class_name ) && interface_exists( 'My_Articles_Content_Adapter_Interface' ) && is_subclass_of( $class_name, 'My_Articles_Content_Adapter_Interface' ) ) {
                    $adapter_instance = new $class_name();
                    $result = $adapter_instance->get_items( $options, $config, $context );
                } elseif ( is_callable( $callback ) ) {
                    $result = call_user_func( $callback, $options, $config, $context );
                } else {
                    continue;
                }
            } catch ( \Throwable $exception ) {
                continue;
            }

            $items = array_merge( $items, self::normalize_adapter_item_collection( $result, $seen_ids ) );
        }

        if ( empty( $items ) ) {
            return array();
        }

        return $items;
    }

    private static function normalize_adapter_item_collection( $raw_items, array &$seen_ids = array() ) {
        $normalized = array();

        if ( $raw_items instanceof WP_Query ) {
            $raw_items = $raw_items->posts;
        }

        if ( $raw_items instanceof WP_Post ) {
            $raw_items = array( $raw_items );
        }

        if ( is_array( $raw_items ) ) {
            foreach ( $raw_items as $item ) {
                $normalized = array_merge( $normalized, self::normalize_adapter_item_collection( $item, $seen_ids ) );
            }

            return $normalized;
        }

        if ( is_object( $raw_items ) && $raw_items instanceof WP_Post ) {
            $post_id = absint( $raw_items->ID );

            if ( $post_id > 0 && ! in_array( $post_id, $seen_ids, true ) ) {
                $seen_ids[] = $post_id;
                $normalized[] = array(
                    'type' => 'post',
                    'post' => $raw_items,
                );
            }

            return $normalized;
        }

        if ( is_scalar( $raw_items ) ) {
            $content = (string) $raw_items;

            if ( '' !== trim( $content ) ) {
                $normalized[] = array(
                    'type'  => 'html',
                    'html'  => wp_kses_post( $content ),
                );
            }

            return $normalized;
        }

        if ( is_object( $raw_items ) && isset( $raw_items->html ) && is_scalar( $raw_items->html ) ) {
            $normalized[] = array(
                'type' => 'html',
                'html' => wp_kses_post( (string) $raw_items->html ),
            );
        }

        return $normalized;
    }

    public static function append_active_tax_query( array $args, $resolved_taxonomy, $active_category, array $active_filters = array() ) {
        $clauses = array();

        if ( '' !== $resolved_taxonomy && '' !== $active_category && 'all' !== $active_category ) {
            $clauses[] = array(
                'taxonomy' => $resolved_taxonomy,
                'field'    => 'slug',
                'terms'    => $active_category,
            );
        }

        if ( ! empty( $active_filters ) ) {
            $seen_keys = array();

            foreach ( $active_filters as $filter ) {
                if ( ! is_array( $filter ) ) {
                    continue;
                }

                $taxonomy = isset( $filter['taxonomy'] ) ? sanitize_key( (string) $filter['taxonomy'] ) : '';
                $slug     = isset( $filter['slug'] ) ? sanitize_title( (string) $filter['slug'] ) : '';

                if ( '' === $taxonomy || '' === $slug ) {
                    continue;
                }

                $filter_key = $taxonomy . '|' . $slug;

                if ( isset( $seen_keys[ $filter_key ] ) ) {
                    continue;
                }

                $seen_keys[ $filter_key ] = true;

                if ( $taxonomy === $resolved_taxonomy && $slug === $active_category ) {
                    continue;
                }

                $clauses[] = array(
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => $slug,
                );
            }
        }

        $existing_tax_query = array();
        $relation           = 'AND';

        if ( isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
            foreach ( $args['tax_query'] as $key => $clause ) {
                if ( 'relation' === $key && is_string( $clause ) ) {
                    $relation = $clause;
                    continue;
                }

                if ( is_array( $clause ) ) {
                    $existing_tax_query[] = $clause;
                }
            }
        }

        $all_clauses = array_merge( $existing_tax_query, $clauses );

        if ( empty( $all_clauses ) ) {
            unset( $args['tax_query'] );
            return $args;
        }

        $args['tax_query'] = array_merge(
            array( 'relation' => $relation ),
            $all_clauses
        );

        return $args;
    }

    private static function merge_tax_query_clauses( array $args, array $clauses ) {
        if ( empty( $clauses ) ) {
            return $args;
        }

        $existing = array();
        $relation = 'AND';

        if ( isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
            foreach ( $args['tax_query'] as $key => $clause ) {
                if ( 'relation' === $key && is_string( $clause ) ) {
                    $relation = $clause;
                    continue;
                }

                if ( is_array( $clause ) ) {
                    $existing[] = $clause;
                }
            }
        }

        $args['tax_query'] = array_merge(
            array( 'relation' => $relation ),
            $existing,
            $clauses
        );

        return $args;
    }

    private static function merge_meta_query_clauses( array $args, array $meta_query ) {
        if ( empty( $meta_query ) || ! is_array( $meta_query ) ) {
            return $args;
        }

        $new_relation = 'AND';
        $clauses      = array();

        foreach ( $meta_query as $key => $clause ) {
            if ( 'relation' === $key && is_string( $clause ) ) {
                $candidate = strtoupper( $clause );
                if ( in_array( $candidate, array( 'AND', 'OR' ), true ) ) {
                    $new_relation = $candidate;
                }
                continue;
            }

            if ( is_array( $clause ) && ! empty( $clause['key'] ) ) {
                $clauses[] = $clause;
            }
        }

        if ( empty( $clauses ) ) {
            return $args;
        }

        $existing           = array();
        $existing_relation   = 'AND';

        if ( isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) {
            foreach ( $args['meta_query'] as $key => $clause ) {
                if ( 'relation' === $key && is_string( $clause ) ) {
                    $candidate = strtoupper( $clause );
                    if ( in_array( $candidate, array( 'AND', 'OR' ), true ) ) {
                        $existing_relation = $candidate;
                    }
                    continue;
                }

                if ( is_array( $clause ) ) {
                    $existing[] = $clause;
                }
            }
        }

        $relation = $new_relation ?: $existing_relation;

        $args['meta_query'] = array_merge(
            array( 'relation' => $relation ),
            $existing,
            $clauses
        );

        return $args;
    }

    private static function apply_primary_taxonomy_terms( array $args, array $options ) {
        if ( empty( $options['primary_taxonomy_terms'] ) || ! is_array( $options['primary_taxonomy_terms'] ) ) {
            return $args;
        }

        $grouped = array();

        foreach ( $options['primary_taxonomy_terms'] as $pair ) {
            if ( ! is_array( $pair ) ) {
                continue;
            }

            $taxonomy = isset( $pair['taxonomy'] ) ? sanitize_key( (string) $pair['taxonomy'] ) : '';
            $slug     = isset( $pair['slug'] ) ? sanitize_title( (string) $pair['slug'] ) : '';

            if ( '' === $taxonomy || '' === $slug ) {
                continue;
            }

            if ( ! isset( $grouped[ $taxonomy ] ) ) {
                $grouped[ $taxonomy ] = array();
            }

            $grouped[ $taxonomy ][] = $slug;
        }

        if ( empty( $grouped ) ) {
            return $args;
        }

        $clauses = array();

        foreach ( $grouped as $taxonomy => $slugs ) {
            $slugs = array_values( array_unique( array_filter( $slugs ) ) );

            if ( empty( $slugs ) ) {
                continue;
            }

            $clauses[] = array(
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => $slugs,
            );
        }

        if ( empty( $clauses ) ) {
            return $args;
        }

        return self::merge_tax_query_clauses( $args, $clauses );
    }

    private static function build_regular_query( array $options, array $overrides = array(), $active_category = null ) {
        $base_args = array(
            'post_type'           => $options['post_type'],
            'post_status'         => 'publish',
            'ignore_sticky_posts' => isset( $options['ignore_native_sticky'] ) ? (int) $options['ignore_native_sticky'] : 0,
        );

        if ( ! isset( $overrides['post__not_in'] ) && isset( $options['all_excluded_ids'] ) ) {
            $base_args['post__not_in'] = $options['all_excluded_ids'];
        }

        $query_args = array_merge( $base_args, $overrides );
        $query_args = self::apply_primary_taxonomy_terms( $query_args, $options );

        if ( ! isset( $query_args['orderby'] ) && ! empty( $options['orderby'] ) ) {
            $query_args['orderby'] = $options['orderby'];
        }

        if ( ! isset( $query_args['order'] ) && ! empty( $options['order'] ) ) {
            $query_args['order'] = $options['order'];
        }

        if ( ! isset( $query_args['s'] ) && ! empty( $options['search_query'] ) ) {
            $query_args['s'] = $options['search_query'];
        }

        if (
            ! isset( $query_args['meta_key'] )
            && isset( $query_args['orderby'] )
            && 'meta_value' === $query_args['orderby']
            && ! empty( $options['meta_key'] )
        ) {
            $query_args['meta_key'] = $options['meta_key'];
        }

        $resolved_taxonomy = $options['resolved_taxonomy'] ?? '';
        $active_category   = null === $active_category ? ( $options['term'] ?? '' ) : $active_category;

        $active_filters = isset( $options['active_tax_filters'] ) && is_array( $options['active_tax_filters'] )
            ? $options['active_tax_filters']
            : array();

        $query_args = self::append_active_tax_query( $query_args, $resolved_taxonomy, $active_category, $active_filters );

        if ( ! empty( $options['meta_query'] ) && is_array( $options['meta_query'] ) ) {
            $query_args = self::merge_meta_query_clauses( $query_args, $options['meta_query'] );
        }

        return new WP_Query( $query_args );
    }

    public static function get_matching_pinned_ids( array $options ) {
        $cache_key = self::build_matching_pinned_cache_key( $options );

        if ( isset( self::$matching_pinned_ids_cache[ $cache_key ] ) ) {
            return self::$matching_pinned_ids_cache[ $cache_key ];
        }

        $pinned_ids = isset( $options['pinned_posts'] ) ? (array) $options['pinned_posts'] : array();

        if ( empty( $pinned_ids ) ) {
            self::$matching_pinned_ids_cache[ $cache_key ] = array();
            return array();
        }

        $query_args = array(
            'post_type'              => $options['post_type'],
            'post_status'            => 'publish',
            'post__in'               => $pinned_ids,
            'orderby'                => 'post__in',
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'post__not_in'           => isset( $options['exclude_post_ids'] ) ? (array) $options['exclude_post_ids'] : array(),
        );

        if ( ! empty( $options['search_query'] ) ) {
            $query_args['s'] = $options['search_query'];
        }

        if ( ! empty( $options['meta_query'] ) && is_array( $options['meta_query'] ) ) {
            $query_args = self::merge_meta_query_clauses( $query_args, $options['meta_query'] );
        }

        if ( empty( $options['pinned_posts_ignore_filter'] ) ) {
            $query_args = self::append_active_tax_query(
                $query_args,
                $options['resolved_taxonomy'] ?? '',
                $options['term'] ?? '',
                isset( $options['active_tax_filters'] ) && is_array( $options['active_tax_filters'] )
                    ? $options['active_tax_filters']
                    : array()
            );
        }

        $pinned_query = new WP_Query( $query_args );

        $matching_ids = array();

        if ( $pinned_query instanceof WP_Query && ! empty( $pinned_query->posts ) ) {
            $matching_ids = array_values( array_filter( array_map( 'absint', $pinned_query->posts ) ) );
        }

        wp_reset_postdata();

        self::$matching_pinned_ids_cache[ $cache_key ] = $matching_ids;

        return $matching_ids;
    }

    /**
     * Retrieves the list of allowed post statuses for a shortcode instance.
     *
     * @param int $instance_id The instance post ID.
     *
     * @return array<int, string> List of allowed statuses.
     */
    public static function get_allowed_instance_statuses( $instance_id = 0 ) {
        $default_statuses = array( 'publish' );
        $allowed_statuses = apply_filters( 'my_articles_allowed_instance_statuses', $default_statuses, $instance_id );

        if ( ! is_array( $allowed_statuses ) ) {
            $allowed_statuses = array( $allowed_statuses );
        }

        $normalized_statuses = array();

        foreach ( $allowed_statuses as $status ) {
            if ( is_string( $status ) && '' !== $status ) {
                $normalized_statuses[] = $status;
            }
        }

        if ( empty( $normalized_statuses ) ) {
            $normalized_statuses = $default_statuses;
        }

        return array_values( array_unique( $normalized_statuses ) );
    }

    public function build_display_state( array $options, array $args = array() ) {
        $defaults = array(
            'paged'                     => 1,
            'pagination_strategy'       => 'page',
            'seen_pinned_ids'           => array(),
            'enforce_unlimited_batch'   => false,
        );

        $args  = wp_parse_args( $args, $defaults );
        $paged = max( 1, (int) $args['paged'] );

        $posts_per_page = isset( $options['posts_per_page'] ) ? (int) $options['posts_per_page'] : 0;
        $is_unlimited   = ! empty( $options['is_unlimited'] );
        $batch_cap      = isset( $options['unlimited_query_cap'] ) ? (int) $options['unlimited_query_cap'] : 0;

        if ( $batch_cap <= 0 ) {
            $batch_cap = (int) apply_filters( 'my_articles_unlimited_batch_size', 50, $options, $args );
            $batch_cap = max( 1, $batch_cap );
        }

        $should_enforce_unlimited = ! empty( $args['enforce_unlimited_batch'] );

        if ( 'slideshow' === ( $options['display_mode'] ?? '' ) ) {
            $should_enforce_unlimited = true;
        }

        if ( $is_unlimited ) {
            if ( $should_enforce_unlimited ) {
                $effective_limit           = $batch_cap;
                $effective_posts_per_page  = $batch_cap;
                $should_limit_display      = true;
            } else {
                $effective_limit           = -1;
                $effective_posts_per_page  = 0;
                $should_limit_display      = false;
            }
        } else {
            $effective_limit          = max( 0, $posts_per_page );
            $effective_posts_per_page = $effective_limit;
            $should_limit_display     = $effective_limit > 0;
        }

        $matching_pinned_ids = self::get_matching_pinned_ids( $options );
        $total_matching_pinned = count( $matching_pinned_ids );

        $pinned_query         = null;
        $regular_query        = null;
        $rendered_pinned_ids  = array();
        $updated_seen_pinned  = array();
        $regular_posts_needed = -1;
        $regular_posts_limit  = -1;
        $regular_offset       = 0;
        $max_items_before_current_page = 0;

        if ( $effective_limit > 0 ) {
            $max_items_before_current_page = max( 0, ( $paged - 1 ) * $effective_limit );
        }

        if ( 'sequential' === $args['pagination_strategy'] ) {
            $seen_pinned_ids = array_map( 'absint', (array) $args['seen_pinned_ids'] );
            $seen_pinned_ids = array_values( array_intersect( $matching_pinned_ids, $seen_pinned_ids ) );

            if ( $effective_limit > 0 ) {
                $remaining_pinned_ids = array_slice( $matching_pinned_ids, count( $seen_pinned_ids ) );
                $pinned_ids_for_request = array_slice( $remaining_pinned_ids, 0, $effective_limit );
            } else {
                $pinned_ids_for_request = array_values( array_diff( $matching_pinned_ids, $seen_pinned_ids ) );
            }

            if ( ! empty( $pinned_ids_for_request ) ) {
                $pinned_query_args = array(
                    'post_type'      => $options['post_type'],
                    'post_status'    => 'publish',
                    'post__in'       => $pinned_ids_for_request,
                    'orderby'        => 'post__in',
                    'posts_per_page' => count( $pinned_ids_for_request ),
                    'no_found_rows'  => true,
                );

                if ( ! empty( $options['meta_query'] ) && is_array( $options['meta_query'] ) ) {
                    $pinned_query_args = self::merge_meta_query_clauses( $pinned_query_args, $options['meta_query'] );
                }

                $pinned_query = new WP_Query( $pinned_query_args );

                if ( $pinned_query->have_posts() ) {
                    $rendered_pinned_ids = array_map( 'absint', wp_list_pluck( $pinned_query->posts, 'ID' ) );
                }
            }

            if ( empty( $rendered_pinned_ids ) && ! empty( $pinned_ids_for_request ) ) {
                $rendered_pinned_ids = array_map( 'absint', $pinned_ids_for_request );
            }

            $updated_seen_pinned = array_values( array_unique( array_merge( $seen_pinned_ids, $rendered_pinned_ids ) ) );

            if ( $effective_limit > 0 ) {
                $regular_posts_already_displayed = max( 0, $max_items_before_current_page - count( $seen_pinned_ids ) );
                $regular_posts_limit             = max( 0, $effective_limit - count( $rendered_pinned_ids ) );
                $regular_offset                  = $regular_posts_already_displayed;
                $regular_posts_needed            = $regular_posts_limit;
            }

            $regular_excluded_ids = array_unique(
                array_merge(
                    $updated_seen_pinned,
                    isset( $options['exclude_post_ids'] ) ? (array) $options['exclude_post_ids'] : array(),
                    $matching_pinned_ids
                )
            );

            if ( $effective_limit > 0 ) {
                if ( $regular_posts_limit > 0 ) {
                    $regular_query = self::build_regular_query(
                        $options,
                        array(
                            'posts_per_page' => $regular_posts_limit,
                            'post__not_in'   => $regular_excluded_ids,
                            'offset'         => $regular_offset,
                        ),
                        $options['term'] ?? ''
                    );
                }
            } else {
                $regular_query = self::build_regular_query(
                    $options,
                    array(
                        'posts_per_page' => -1,
                        'post__not_in'   => $regular_excluded_ids,
                    ),
                    $options['term'] ?? ''
                );
            }
        } else {
            if ( $effective_limit > 0 ) {
                $pinned_offset          = min( $total_matching_pinned, $max_items_before_current_page );
                $pinned_ids_for_request = array_slice( $matching_pinned_ids, $pinned_offset, $effective_limit );
                $regular_offset         = max( 0, $max_items_before_current_page - $pinned_offset );
            } else {
                $pinned_ids_for_request = $matching_pinned_ids;
                $regular_offset         = 0;
            }

            if ( ! empty( $pinned_ids_for_request ) ) {
                $pinned_query_args = array(
                    'post_type'      => $options['post_type'],
                    'post_status'    => 'publish',
                    'post__in'       => $pinned_ids_for_request,
                    'orderby'        => 'post__in',
                    'posts_per_page' => count( $pinned_ids_for_request ),
                    'no_found_rows'  => true,
                    'post__not_in'   => isset( $options['exclude_post_ids'] ) ? (array) $options['exclude_post_ids'] : array(),
                );

                if ( ! empty( $options['meta_query'] ) && is_array( $options['meta_query'] ) ) {
                    $pinned_query_args = self::merge_meta_query_clauses( $pinned_query_args, $options['meta_query'] );
                }

                $pinned_query = new WP_Query( $pinned_query_args );

                if ( $pinned_query->have_posts() ) {
                    $rendered_pinned_ids = array_map( 'absint', wp_list_pluck( $pinned_query->posts, 'ID' ) );
                }
            }

            if ( empty( $rendered_pinned_ids ) && ! empty( $pinned_ids_for_request ) ) {
                $rendered_pinned_ids = array_map( 'absint', $pinned_ids_for_request );
            }

            if ( $effective_limit > 0 ) {
                $projected_pinned_display = min( $effective_limit, count( $rendered_pinned_ids ) );
                if ( empty( $rendered_pinned_ids ) && ! empty( $pinned_ids_for_request ) ) {
                    $projected_pinned_display = min( $effective_limit, count( $pinned_ids_for_request ) );
                }

                $regular_posts_needed = max( 0, $effective_limit - $projected_pinned_display );
                $regular_posts_limit  = $regular_posts_needed;
            }

            if ( $effective_limit > 0 ) {
                if ( $regular_posts_limit > 0 ) {
                    $regular_query = self::build_regular_query(
                        $options,
                        array(
                            'posts_per_page' => $regular_posts_limit,
                            'offset'         => $regular_offset,
                        ),
                        $options['term'] ?? ''
                    );
                }
            } else {
                $regular_query = self::build_regular_query(
                    $options,
                    array(
                        'posts_per_page' => -1,
                    ),
                    $options['term'] ?? ''
                );
            }
        }

        $total_regular_posts = (int) ( $regular_query instanceof WP_Query ? $regular_query->found_posts : 0 );

        return array(
            'pinned_query'                => $pinned_query,
            'regular_query'               => $regular_query,
            'rendered_pinned_ids'         => $rendered_pinned_ids,
            'should_limit_display'        => $should_limit_display,
            'render_limit'                => $effective_limit > 0 ? $effective_limit : 0,
            'regular_posts_needed'        => $regular_posts_needed,
            'total_pinned_posts'          => $total_matching_pinned,
            'total_regular_posts'         => $total_regular_posts,
            'effective_posts_per_page'    => $effective_posts_per_page,
            'is_unlimited'                => $is_unlimited,
            'updated_seen_pinned_ids'     => $updated_seen_pinned,
            'unlimited_batch_size'        => $batch_cap,
            'should_enforce_unlimited'    => (bool) $should_enforce_unlimited,
            'meta_query'                  => isset( $options['meta_query'] ) && is_array( $options['meta_query'] )
                ? $options['meta_query']
                : array(),
            'meta_query_relation'         => isset( $options['meta_query_relation'] ) && is_string( $options['meta_query_relation'] )
                ? $options['meta_query_relation']
                : 'AND',
            'content_adapters'            => isset( $options['content_adapters'] ) && is_array( $options['content_adapters'] )
                ? $options['content_adapters']
                : array(),
        );
    }

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        $this->data_preparer = new My_Articles_Shortcode_Data_Preparer( $this );
        add_shortcode( 'mon_affichage_articles', array( $this, 'render_shortcode' ) );
    }

    /**
     * Returns the service responsible for preparing normalized shortcode data.
     *
     * @return My_Articles_Shortcode_Data_Preparer
     */
    public function get_data_preparer() {
        if ( ! $this->data_preparer instanceof My_Articles_Shortcode_Data_Preparer ) {
            $this->data_preparer = new My_Articles_Shortcode_Data_Preparer( $this );
        }

        return $this->data_preparer;
    }

    public static function get_default_options() {
        static $cached_defaults = null;

        if ( null !== $cached_defaults ) {
            return $cached_defaults;
        }

        $defaults = [
            'post_type' => 'post',
            'taxonomy' => '',
            'term' => '',
            'tax_filters' => array(),
            'primary_taxonomy_terms' => array(),
            'content_adapters' => array(),
            'meta_query' => array(),
            'meta_query_relation' => 'AND',
            'counting_behavior' => 'exact',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'sort' => 'date',
            'order' => 'DESC',
            'meta_key' => '',
            'pagination_mode' => 'none',
            'load_more_auto' => 0,
            'design_preset' => 'custom',
            'design_preset_locked' => 0,
            'enable_keyword_search' => 0,
            'show_category_filter' => 0,
            'filter_alignment' => 'right',
            'filter_categories' => array(),
            'aria_label' => '',
            'category_filter_aria_label' => '',
            'search_query' => '',
            'pinned_posts' => array(),
            'pinned_border_color' => '#eab308',
            'pinned_posts_ignore_filter' => 0,
            'pinned_show_badge' => 0,
            'pinned_badge_text' => 'Épinglé',
            'pinned_badge_bg_color' => '#eab308',
            'pinned_badge_text_color' => '#ffffff',
            'exclude_posts' => '',
            'ignore_native_sticky' => 1,
            'enable_lazy_load' => 1,
            'enable_debug_mode' => 0,
            'display_mode' => 'grid',
            'thumbnail_aspect_ratio' => self::get_default_thumbnail_aspect_ratio(),
            'columns_mobile' => 1, 'columns_tablet' => 2, 'columns_desktop' => 3, 'columns_ultrawide' => 4,
            'module_padding_top' => 0, 'module_padding_right' => 0,
            'module_padding_bottom' => 0, 'module_padding_left' => 0,
            'gap_size' => 25, 'list_item_gap' => 25,
            'list_content_padding_top' => 0, 'list_content_padding_right' => 0,
            'list_content_padding_bottom' => 0, 'list_content_padding_left' => 0,
            'border_radius' => 12, 'title_font_size' => 16,
            'meta_font_size' => 14, 'show_category' => 1, 'show_author' => 1, 'show_date' => 1,
            'show_excerpt' => 0,
            'excerpt_length' => 25,
            'excerpt_more_text' => 'Lire la suite',
            'excerpt_font_size' => 14,
            'excerpt_color' => '#4b5563',
            'module_bg_color' => 'rgba(255,255,255,0)', 'vignette_bg_color' => '#ffffff',
            'title_wrapper_bg_color' => '#ffffff', 'title_color' => '#333333',
            'meta_color' => '#6b7280', 'meta_color_hover' => '#000000', 'pagination_color' => '#333333',
            'shadow_color' => 'rgba(0,0,0,0.07)', 'shadow_color_hover' => 'rgba(0,0,0,0.12)',
            'hover_lift_desktop' => 1, 'hover_neon_pulse' => 0,
            'slideshow_loop' => 1,
            'slideshow_autoplay' => 0,
            'slideshow_delay' => 5000,
            'slideshow_pause_on_interaction' => 1,
            'slideshow_pause_on_mouse_enter' => 1,
            'slideshow_respect_reduced_motion' => 1,
            'slideshow_show_navigation' => 1,
            'slideshow_show_pagination' => 1,
        ];

        $saved_options = get_option( 'my_articles_options', array() );

        if ( ! is_array( $saved_options ) ) {
            $saved_options = array();
        }

        $aliases = array(
            'desktop_columns'     => 'columns_desktop',
            'mobile_columns'      => 'columns_mobile',
            'module_margin_top'    => 'module_padding_top',
            'module_margin_left'   => 'module_padding_left',
            'module_margin_bottom' => 'module_padding_bottom',
            'module_margin_right'  => 'module_padding_right',
        );

        foreach ( $aliases as $stored_key => $option_key ) {
            if ( array_key_exists( $stored_key, $saved_options ) ) {
                $saved_options[ $option_key ] = $saved_options[ $stored_key ];
            }
        }

        if ( ! empty( $saved_options['default_category'] ) ) {
            $saved_options['taxonomy'] = 'category';
            $saved_options['term']     = sanitize_title( (string) $saved_options['default_category'] );
        }

        $saved_options = array_intersect_key( $saved_options, $defaults );

        $cached_defaults = wp_parse_args( $saved_options, $defaults );

        $allowed_sort_defaults = array( 'date', 'title', 'menu_order', 'meta_value', 'comment_count', 'post__in' );

        if ( empty( $cached_defaults['sort'] ) || ! in_array( $cached_defaults['sort'], $allowed_sort_defaults, true ) ) {
            $cached_defaults['sort'] = $cached_defaults['orderby'];
        }

        return $cached_defaults;
    }

    public static function normalize_instance_options( $raw_options, $context = array() ) {
        if ( ! is_array( $context ) ) {
            $context = array();
        }

        $raw_options = is_array( $raw_options ) ? $raw_options : array();

        $external_requested_category = '';

        if ( array_key_exists( 'requested_category', $context ) ) {
            $raw_requested_category = $context['requested_category'];

            if ( is_scalar( $raw_requested_category ) ) {
                $external_requested_category = sanitize_title( (string) $raw_requested_category );
            }

            $context['requested_category'] = $external_requested_category;
        }

        if ( array_key_exists( 'allow_external_requested_category', $context ) ) {
            $context['allow_external_requested_category'] = ! empty( $context['allow_external_requested_category'] ) ? 1 : 0;
        }

        $cache_key = self::build_normalized_options_cache_key( $raw_options, $context );

        if ( isset( self::$normalized_options_cache[ $cache_key ] ) ) {
            return self::$normalized_options_cache[ $cache_key ];
        }

        $defaults = self::get_default_options();

        $requested_design_preset = $defaults['design_preset'];

        if ( isset( $raw_options['design_preset'] ) && is_scalar( $raw_options['design_preset'] ) ) {
            $requested_design_preset = sanitize_key( (string) $raw_options['design_preset'] );
        }

        $design_presets = self::get_design_presets();

        if ( ! isset( $design_presets[ $requested_design_preset ] ) ) {
            $requested_design_preset = 'custom';
        }

        $raw_options['design_preset'] = $requested_design_preset;

        $preset_definition = self::get_design_preset( $requested_design_preset );
        $preset_values     = self::get_design_preset_values( $requested_design_preset );
        $is_preset_locked  = false;

        if ( is_array( $preset_definition ) ) {
            $is_preset_locked = ! empty( $preset_definition['locked'] );
        }

        $options = wp_parse_args( $raw_options, $defaults );

        $options['load_more_auto'] = ! empty( $options['load_more_auto'] ) ? 1 : 0;

        if ( $options['pagination_mode'] !== 'load_more' ) {
            $options['load_more_auto'] = 0;
        }

        $options['enable_keyword_search'] = ! empty( $options['enable_keyword_search'] ) ? 1 : 0;

        $search_query = '';

        if ( $options['enable_keyword_search'] ) {
            $requested_search = null;

            if ( array_key_exists( 'requested_search', $context ) ) {
                $requested_search = $context['requested_search'];
            } elseif ( isset( $options['search_query'] ) ) {
                $requested_search = $options['search_query'];
            }

            if ( is_scalar( $requested_search ) ) {
                $raw_search = (string) $requested_search;

                if ( function_exists( 'wp_unslash' ) ) {
                    $raw_search = wp_unslash( $raw_search );
                }

                $raw_search = sanitize_text_field( $raw_search );
                $raw_search = trim( $raw_search );

                if ( '' !== $raw_search ) {
                    $max_length = (int) apply_filters( 'my_articles_search_query_length', 160, $options, $context );

                    if ( $max_length <= 0 ) {
                        $max_length = 160;
                    }

                    if ( function_exists( 'mb_substr' ) ) {
                        $search_query = mb_substr( $raw_search, 0, $max_length );
                    } else {
                        $search_query = substr( $raw_search, 0, $max_length );
                    }
                }
            }
        }

        $options['search_query'] = $search_query;

        if ( ! empty( $preset_values ) ) {
            if ( $is_preset_locked ) {
                $options = array_merge( $options, $preset_values );
            } else {
                foreach ( $preset_values as $preset_key => $preset_value ) {
                    if ( array_key_exists( $preset_key, $raw_options ) ) {
                        continue;
                    }

                    $options[ $preset_key ] = $preset_value;
                }
            }
        }

        $options['design_preset'] = $requested_design_preset;
        $options['design_preset_locked'] = $is_preset_locked ? 1 : 0;

        $allowed_thumbnail_ratios = self::get_allowed_thumbnail_aspect_ratios();
        $default_thumbnail_ratio = self::get_default_thumbnail_aspect_ratio();
        $thumbnail_ratio         = isset( $options['thumbnail_aspect_ratio'] ) ? (string) $options['thumbnail_aspect_ratio'] : $default_thumbnail_ratio;

        if ( ! in_array( $thumbnail_ratio, $allowed_thumbnail_ratios, true ) ) {
            $thumbnail_ratio = $default_thumbnail_ratio;
        }

        $options['thumbnail_aspect_ratio'] = $thumbnail_ratio;

        $aria_label = '';
        if ( isset( $options['aria_label'] ) && is_string( $options['aria_label'] ) ) {
            $aria_label = trim( sanitize_text_field( $options['aria_label'] ) );
        }
        $options['aria_label'] = $aria_label;

        $category_filter_aria_label = '';
        if ( isset( $options['category_filter_aria_label'] ) && is_string( $options['category_filter_aria_label'] ) ) {
            $category_filter_aria_label = trim( sanitize_text_field( $options['category_filter_aria_label'] ) );
        }
        if ( '' === $category_filter_aria_label && '' !== $aria_label ) {
            /* translators: %s: module accessible label. */
            $category_filter_aria_label = sprintf( __( 'Filtre des catégories pour %s', 'mon-articles' ), $aria_label );
        }
        $options['category_filter_aria_label'] = $category_filter_aria_label;

        $allowed_display_modes = array( 'grid', 'list', 'slideshow' );
        $display_mode          = $options['display_mode'] ?? $defaults['display_mode'];
        if ( ! in_array( $display_mode, $allowed_display_modes, true ) ) {
            $display_mode = $defaults['display_mode'];
        }
        $options['display_mode'] = $display_mode;

        $options['post_type'] = my_articles_normalize_post_type( $options['post_type'] ?? '' );

        $taxonomy = isset( $options['taxonomy'] ) ? sanitize_text_field( $options['taxonomy'] ) : '';
        if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $options['post_type'], $taxonomy ) ) {
            $taxonomy = '';
        }
        $options['taxonomy'] = $taxonomy;

        $options['term'] = sanitize_title( $options['term'] ?? '' );

        $allowed_orderby = array( 'date', 'title', 'menu_order', 'meta_value', 'comment_count', 'post__in' );
        $requested_orderby = isset( $options['orderby'] ) ? (string) $options['orderby'] : $defaults['orderby'];
        if ( ! in_array( $requested_orderby, $allowed_orderby, true ) ) {
            $requested_orderby = $defaults['orderby'];
        }

        $allowed_sort_values = $allowed_orderby;
        $query_sort          = $requested_orderby;
        $display_sort        = $requested_orderby;

        if ( isset( $options['sort'] ) && is_scalar( $options['sort'] ) ) {
            $candidate_sort = sanitize_key( (string) $options['sort'] );

            if ( '' !== $candidate_sort ) {
                $display_sort = $candidate_sort;

                if ( in_array( $candidate_sort, $allowed_sort_values, true ) ) {
                    $query_sort = $candidate_sort;
                }
            }
        }

        if ( array_key_exists( 'requested_sort', $context ) ) {
            $context_sort = $context['requested_sort'];

            if ( is_scalar( $context_sort ) ) {
                $context_sort = sanitize_key( (string) $context_sort );

                if ( '' !== $context_sort ) {
                    $display_sort = $context_sort;

                    if ( in_array( $context_sort, $allowed_sort_values, true ) ) {
                        $query_sort = $context_sort;
                    }
                }
            }
        }

        $meta_key = '';
        if ( isset( $options['meta_key'] ) && is_scalar( $options['meta_key'] ) ) {
            $meta_key = trim( sanitize_text_field( (string) $options['meta_key'] ) );
        }

        if ( 'meta_value' === $query_sort ) {
            if ( '' === $meta_key ) {
                $requested_orderby = $defaults['orderby'];
                $query_sort        = $defaults['sort'];
            }
        } else {
            $meta_key = '';
        }

        if ( in_array( $query_sort, $allowed_orderby, true ) ) {
            $requested_orderby = $query_sort;
        }

        $options['orderby']  = $requested_orderby;
        $options['sort']      = $display_sort;
        $options['meta_key'] = $meta_key;

        $order = isset( $options['order'] ) ? strtoupper( (string) $options['order'] ) : $defaults['order'];
        if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
            $order = $defaults['order'];
        }
        $options['order'] = $order;

        $options['resolved_taxonomy'] = self::resolve_taxonomy( $options );

        $raw_posts_per_page = isset( $options['posts_per_page'] ) ? (int) $options['posts_per_page'] : (int) $defaults['posts_per_page'];
        $is_unlimited       = $raw_posts_per_page <= 0;
        $posts_per_page     = $is_unlimited ? -1 : $raw_posts_per_page;

        if ( ! $is_unlimited && ( $options['counting_behavior'] ?? $defaults['counting_behavior'] ) === 'auto_fill' && in_array( $options['display_mode'], array( 'grid', 'slideshow' ), true ) ) {
            $master_columns = isset( $options['columns_ultrawide'] ) ? (int) $options['columns_ultrawide'] : 0;
            if ( $master_columns > 0 ) {
                $rows_needed    = (int) ceil( $posts_per_page / $master_columns );
                $posts_per_page = $rows_needed * $master_columns;
            }
        }

        $options['posts_per_page'] = $posts_per_page;
        $options['is_unlimited']   = $is_unlimited;

        $unlimited_cap = (int) apply_filters( 'my_articles_unlimited_batch_size', 50, $options, $context );
        $options['unlimited_query_cap'] = max( 1, $unlimited_cap );

        if (
            $is_unlimited
            && ( $options['pagination_mode'] ?? 'none' ) === 'none'
            && 'slideshow' !== $options['display_mode']
        ) {
            $options['pagination_mode'] = 'load_more';
        }

        $ignore_native_sticky        = ! empty( $options['ignore_native_sticky'] ) ? (int) $options['ignore_native_sticky'] : 0;
        $options['ignore_native_sticky'] = $ignore_native_sticky;

        $options['pinned_posts_ignore_filter'] = ! empty( $options['pinned_posts_ignore_filter'] ) ? 1 : 0;

        $filter_categories = array();
        if ( ! empty( $options['filter_categories'] ) ) {
            if ( is_array( $options['filter_categories'] ) ) {
                $filter_categories = $options['filter_categories'];
            } else {
                $filter_categories = explode( ',', (string) $options['filter_categories'] );
            }

            $filter_categories = array_values( array_filter( array_map( 'absint', $filter_categories ) ) );
        }
        $options['filter_categories'] = $filter_categories;

        $options['primary_taxonomy_terms'] = self::sanitize_filter_pairs(
            $options['primary_taxonomy_terms'] ?? array(),
            $options['post_type']
        );

        $available_tax_filters = self::sanitize_filter_pairs( $options['tax_filters'] ?? array(), $options['post_type'] );
        $options['tax_filters']      = $available_tax_filters;

        $requested_tax_filters = array();

        if ( array_key_exists( 'requested_filters', $context ) ) {
            $requested_tax_filters = self::sanitize_filter_pairs( $context['requested_filters'], $options['post_type'] );
        }

        $options['content_adapters'] = self::sanitize_content_adapters( $options['content_adapters'] ?? array() );

        $meta_query_source = array();
        if ( array_key_exists( 'meta_query_raw', $raw_options ) ) {
            $meta_query_source = $raw_options['meta_query_raw'];
        } elseif ( array_key_exists( 'meta_query', $raw_options ) ) {
            $meta_query_source = $raw_options['meta_query'];
        }

        $meta_query = My_Articles_Settings_Sanitizer::sanitize_meta_queries(
            array(
                'relation' => isset( $raw_options['meta_query_relation'] ) ? $raw_options['meta_query_relation'] : ( $options['meta_query_relation'] ?? 'AND' ),
                'clauses'  => $meta_query_source,
            )
        );

        $options['meta_query'] = $meta_query;
        $options['meta_query_relation'] = isset( $meta_query['relation'] ) ? $meta_query['relation'] : 'AND';

        $active_tax_filters = array();

        if ( ! empty( $available_tax_filters ) ) {
            $allowed_map = array();
            foreach ( $available_tax_filters as $filter ) {
                $key = self::build_filter_key( $filter );

                if ( '' !== $key ) {
                    $allowed_map[ $key ] = $filter;
                }
            }

            if ( empty( $requested_tax_filters ) ) {
                $active_tax_filters = $available_tax_filters;
            } else {
                foreach ( $requested_tax_filters as $filter ) {
                    $key = self::build_filter_key( $filter );

                    if ( '' !== $key && isset( $allowed_map[ $key ] ) ) {
                        $active_tax_filters[ $key ] = $allowed_map[ $key ];
                    }
                }

                if ( empty( $active_tax_filters ) ) {
                    $active_tax_filters = $available_tax_filters;
                } else {
                    $active_tax_filters = array_values( $active_tax_filters );
                }
            }
        } else {
            $active_tax_filters = $requested_tax_filters;
        }

        $options['active_tax_filters']      = $active_tax_filters;
        $options['active_tax_filter_keys']  = array_values( array_filter( array_map( array( __CLASS__, 'build_filter_key' ), $active_tax_filters ) ) );

        $pinned_ids = array();
        if ( ! empty( $options['pinned_posts'] ) && is_array( $options['pinned_posts'] ) ) {
            $pinned_ids = array_values(
                array_filter(
                    array_unique( array_map( 'absint', $options['pinned_posts'] ) ),
                    static function ( $post_id ) use ( $options ) {
                        return $post_id > 0 && get_post_type( $post_id ) === $options['post_type'];
                    }
                )
            );
        }
        $options['pinned_posts'] = $pinned_ids;

        $exclude_post_ids = array();
        if ( ! empty( $options['exclude_posts'] ) ) {
            $raw_exclude_ids = is_array( $options['exclude_posts'] ) ? $options['exclude_posts'] : explode( ',', $options['exclude_posts'] );
            $exclude_post_ids = array_values( array_filter( array_map( 'absint', $raw_exclude_ids ) ) );
        }
        $options['exclude_post_ids'] = $exclude_post_ids;

        $options['all_excluded_ids'] = array_values( array_unique( array_merge( $pinned_ids, $exclude_post_ids ) ) );

        $options['slideshow_loop'] = ! empty( $options['slideshow_loop'] ) ? 1 : 0;
        $options['slideshow_autoplay'] = ! empty( $options['slideshow_autoplay'] ) ? 1 : 0;
        $options['slideshow_pause_on_interaction'] = ! empty( $options['slideshow_pause_on_interaction'] ) ? 1 : 0;
        $options['slideshow_pause_on_mouse_enter'] = ! empty( $options['slideshow_pause_on_mouse_enter'] ) ? 1 : 0;
        $options['slideshow_respect_reduced_motion'] = ! empty( $options['slideshow_respect_reduced_motion'] ) ? 1 : 0;
        $options['slideshow_show_navigation'] = ! empty( $options['slideshow_show_navigation'] ) ? 1 : 0;
        $options['slideshow_show_pagination'] = ! empty( $options['slideshow_show_pagination'] ) ? 1 : 0;

        $slideshow_delay = isset( $options['slideshow_delay'] ) ? (int) $options['slideshow_delay'] : (int) $defaults['slideshow_delay'];
        if ( $slideshow_delay < 0 ) {
            $slideshow_delay = 0;
        }
        if ( $slideshow_delay > 0 && $slideshow_delay < 1000 ) {
            $slideshow_delay = 1000;
        }
        if ( $slideshow_delay > 20000 ) {
            $slideshow_delay = 20000;
        }
        if ( 0 === $slideshow_delay ) {
            $slideshow_delay = (int) $defaults['slideshow_delay'];
        }
        $options['slideshow_delay'] = $slideshow_delay;

        $default_term = $options['term'];
        $requested_category = '';

        $has_external_requested_category = '' !== $external_requested_category;
        $allow_external_requested_category = ! empty( $context['allow_external_requested_category'] );

        if ( $has_external_requested_category ) {
            if (
                $allow_external_requested_category
                || ! empty( $options['show_category_filter'] )
                || ! empty( $filter_categories )
            ) {
                $requested_category = $external_requested_category;
            }
        }

        $force_collect_terms = ! empty( $context['force_collect_terms'] );

        $should_collect_terms = $force_collect_terms
            || ! empty( $options['show_category_filter'] )
            || '' !== $requested_category
            || ! empty( $filter_categories );

        $available_categories     = array();
        $available_category_slugs = array();

        if ( $should_collect_terms && ! empty( $options['resolved_taxonomy'] ) ) {
            $get_terms_args = [
                'taxonomy'   => $options['resolved_taxonomy'],
                'hide_empty' => true,
            ];

            if ( ! empty( $filter_categories ) ) {
                $get_terms_args['include'] = $filter_categories;
                $get_terms_args['orderby'] = 'include';
            }

            $terms = get_terms( $get_terms_args );

            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $available_categories     = $terms;
                $available_category_slugs = array_values( array_filter( wp_list_pluck( $terms, 'slug' ), 'strlen' ) );
            }
        }

        $options['available_categories']     = $available_categories;
        $options['available_category_slugs'] = $available_category_slugs;

        $allowed_filter_term_slugs = array();
        if ( ! empty( $filter_categories ) && ! empty( $available_category_slugs ) ) {
            $allowed_filter_term_slugs = $available_category_slugs;
        }
        $options['allowed_filter_term_slugs'] = $allowed_filter_term_slugs;

        $valid_category_slugs = array_unique(
            array_merge(
                array( '', 'all', $default_term ),
                $allowed_filter_term_slugs
            )
        );
        $options['valid_category_slugs'] = $valid_category_slugs;

        $is_requested_category_valid = true;

        if ( ! empty( $allowed_filter_term_slugs ) ) {
            $is_requested_category_valid = in_array( $requested_category, $valid_category_slugs, true );
        }

        $active_category = $default_term;

        if ( '' !== $requested_category ) {
            if ( 'all' === $requested_category ) {
                $active_category = 'all';
            } elseif ( in_array( $requested_category, $available_category_slugs, true ) ) {
                $active_category = $requested_category;
            } elseif ( empty( $available_category_slugs ) ) {
                $active_category = $requested_category;
            }
        }

        $options['term']                       = $active_category;
        $options['default_term']               = $default_term;
        $options['requested_category']         = $requested_category;
        $options['is_requested_category_valid'] = $is_requested_category_valid;

        $options['module_padding_top']    = min( 200, max( 0, absint( $options['module_padding_top'] ?? $defaults['module_padding_top'] ) ) );
        $options['module_padding_right']  = min( 200, max( 0, absint( $options['module_padding_right'] ?? $defaults['module_padding_right'] ) ) );
        $options['module_padding_bottom'] = min( 200, max( 0, absint( $options['module_padding_bottom'] ?? $defaults['module_padding_bottom'] ) ) );
        $options['module_padding_left']   = min( 200, max( 0, absint( $options['module_padding_left'] ?? $defaults['module_padding_left'] ) ) );

        self::$normalized_options_cache[ $cache_key ] = $options;

        return $options;
    }

    public function render_shortcode( $atts ) {
        self::$last_render_summary = array();
        $atts = shortcode_atts(
            array(
                'id'        => 0,
                'overrides' => array(),
            ),
            $atts,
            'mon_affichage_articles'
        );
        $id   = absint( $atts['id'] );
        $overrides = array();

        if ( ! empty( $atts['overrides'] ) && is_array( $atts['overrides'] ) ) {
            $defaults = self::get_default_options();

            foreach ( $atts['overrides'] as $key => $value ) {
                if ( ! array_key_exists( $key, $defaults ) ) {
                    continue;
                }

                $default_value = $defaults[ $key ];

                if ( is_array( $default_value ) ) {
                    if ( is_array( $value ) ) {
                        $overrides[ $key ] = $value;
                    }
                    continue;
                }

                if ( is_int( $default_value ) ) {
                    if ( is_bool( $value ) ) {
                        $overrides[ $key ] = $value ? 1 : 0;
                    } else {
                        $overrides[ $key ] = (int) $value;
                    }
                    continue;
                }

                if ( is_float( $default_value ) ) {
                    $overrides[ $key ] = (float) $value;
                    continue;
                }

                $overrides[ $key ] = (string) $value;
            }
        }

        if ( ! $id || 'mon_affichage' !== get_post_type( $id ) ) {
            return '';
        }

        $post_status      = get_post_status( $id );
        $allowed_statuses = self::get_allowed_instance_statuses( $id );

        if ( empty( $post_status ) || ! in_array( $post_status, $allowed_statuses, true ) ) {
            return '';
        }

        $preparation = $this->get_data_preparer()->prepare( $id, $overrides );

        if ( is_wp_error( $preparation ) ) {
            return '';
        }

        $options            = isset( $preparation['options'] ) ? $preparation['options'] : array();
        $requested_values   = isset( $preparation['requested'] ) && is_array( $preparation['requested'] )
            ? $preparation['requested']
            : array();
        $request_query_vars = isset( $preparation['request_query_vars'] ) && is_array( $preparation['request_query_vars'] )
            ? $preparation['request_query_vars']
            : array();

        $requested_category = isset( $requested_values['category'] ) ? $requested_values['category'] : '';
        $requested_search   = isset( $requested_values['search'] ) ? $requested_values['search'] : '';
        $requested_sort     = isset( $requested_values['sort'] ) ? $requested_values['sort'] : '';
        $requested_page     = isset( $requested_values['page'] ) ? (int) $requested_values['page'] : 1;

        $category_query_var = isset( $request_query_vars['category'] ) ? $request_query_vars['category'] : 'my_articles_cat_' . $id;
        $search_query_var   = isset( $request_query_vars['search'] ) ? $request_query_vars['search'] : 'my_articles_search_' . $id;
        $sort_query_var     = isset( $request_query_vars['sort'] ) ? $request_query_vars['sort'] : 'my_articles_sort_' . $id;
        $paged_var          = isset( $request_query_vars['paged'] ) ? $request_query_vars['paged'] : 'paged_' . $id;

        $available_categories = isset( $options['available_categories'] ) ? $options['available_categories'] : array();

        $resolved_aria_label = '';
        if ( isset( $options['aria_label'] ) && is_string( $options['aria_label'] ) ) {
            $resolved_aria_label = trim( $options['aria_label'] );
        }

        if ( '' === $resolved_aria_label ) {
            $fallback_label = trim( wp_strip_all_tags( get_the_title( $id ) ) );

            if ( '' === $fallback_label ) {
                /* translators: %d: module (post) ID. */
                $fallback_label = sprintf( __( "Module d'articles %d", 'mon-articles' ), $id );
            }

            $resolved_aria_label = $fallback_label;
        }

        $script_payloads = isset( $preparation['script_data'] ) && is_array( $preparation['script_data'] )
            ? $preparation['script_data']
            : array();

        if ( ! empty( $options['show_category_filter'] ) || ! empty( $options['enable_keyword_search'] ) ) {
            wp_enqueue_script( 'my-articles-filter' );
        }

        if ( isset( $options['pagination_mode'] ) && 'load_more' === $options['pagination_mode'] ) {
            wp_enqueue_script( 'my-articles-load-more' );
        }

        if ( ! empty( $script_payloads ) ) {
            $enqueue = My_Articles_Enqueue::get_instance();

            foreach ( $script_payloads as $payload ) {
                if ( empty( $payload['handle'] ) || empty( $payload['object'] ) || empty( $payload['data'] ) ) {
                    continue;
                }

                $enqueue->register_script_data(
                    $payload['handle'],
                    $payload['object'],
                    (array) $payload['data']
                );
            }
        }

        if ( isset( $options['pagination_mode'] ) && 'numbered' === $options['pagination_mode'] ) {
            wp_enqueue_script( 'my-articles-scroll-fix' );
        }

        if ( ! empty( $options['enable_lazy_load'] ) ) {
            if ( ! self::$lazysizes_enqueued ) {
                wp_enqueue_script( 'lazysizes' );
                self::$lazysizes_enqueued = true;
            }

            self::ensure_lazyload_fallback_script();
        }

        if ( $requested_page < 1 ) {
            $requested_page = 1;
        }

        $paged = $requested_page;

        $all_excluded_ids = isset( $options['all_excluded_ids'] ) ? (array) $options['all_excluded_ids'] : array();

        $state = $this->build_display_state(
            $options,
            array(
                'paged'                   => $paged,
                'pagination_strategy'     => 'page',
                'enforce_unlimited_batch' => ( ! empty( $options['is_unlimited'] ) && 'slideshow' !== $options['display_mode'] ),
            )
        );

        $pinned_query           = $state['pinned_query'];
        $articles_query         = $state['regular_query'];
        $total_matching_pinned  = $state['total_pinned_posts'];
        $total_regular_posts    = (int) $state['total_regular_posts'];
        $initial_total_results  = max( 0, (int) $total_matching_pinned ) + max( 0, $total_regular_posts );
        $search_suggestions     = $this->build_search_suggestions( $options, $available_categories, $pinned_query, $articles_query );
        $result_count_label     = $this->format_result_count_label( $initial_total_results );
        $first_page_projected_pinned = $total_matching_pinned;
        $should_limit_display = $state['should_limit_display'];
        $render_limit         = $state['render_limit'];
        $regular_posts_needed = $state['regular_posts_needed'];
        $is_unlimited         = ! empty( $state['is_unlimited'] );
        $effective_posts_per_page = $state['effective_posts_per_page'];

        if ( 'slideshow' === $options['display_mode'] ) {
            $should_limit_display = false;
        }
        
        if ($options['display_mode'] === 'slideshow') { $this->enqueue_swiper_scripts($options, $id); }

        wp_enqueue_style('my-articles-styles');

        $default_min_card_width = 220;
        $options['min_card_width'] = max(1, (int) apply_filters('my_articles_min_card_width', $default_min_card_width, $options, $id));

        if ( in_array( $options['display_mode'], array( 'grid', 'list', 'slideshow' ), true ) ) {
            wp_enqueue_script( 'my-articles-responsive-layout' );
        }

        $summary_metrics = array(
            'total_results'          => (int) $initial_total_results,
            'total_pinned_available' => (int) $total_matching_pinned,
            'rendered_pinned'        => count( (array) $state['rendered_pinned_ids'] ),
            'total_regular'          => (int) $total_regular_posts,
            'per_page'               => (int) $effective_posts_per_page,
            'render_limit'           => (int) $render_limit,
            'is_unlimited'           => (bool) $is_unlimited,
            'should_limit_display'   => (bool) $should_limit_display,
            'unlimited_batch_size'   => (int) $state['unlimited_batch_size'],
            'regular_posts_needed'   => (int) $regular_posts_needed,
            'filters_available'      => is_array( $available_categories ) ? count( $available_categories ) : 0,
            'active_filters'         => isset( $options['active_tax_filters'] ) && is_array( $options['active_tax_filters'] )
                ? count( $options['active_tax_filters'] )
                : 0,
            'current_page'           => (int) $paged,
        );

        $summary_options = array(
            'display_mode'          => sanitize_key( $options['display_mode'] ),
            'pagination_mode'       => sanitize_key( $options['pagination_mode'] ),
            'load_more_auto'        => ! empty( $options['load_more_auto'] ),
            'show_category_filter'  => ! empty( $options['show_category_filter'] ),
            'enable_keyword_search' => ! empty( $options['enable_keyword_search'] ),
        );

        self::$last_render_summary = array(
            'instance_id' => $id,
            'metrics'     => $summary_metrics,
            'options'     => $summary_options,
        );

        ob_start();
        $inline_styles = $this->render_inline_styles( $options, $id );

        $wrapper_class = 'my-articles-wrapper my-articles-' . esc_attr($options['display_mode']);

        if ( ! empty( $options['hover_lift_desktop'] ) ) {
            $wrapper_class .= ' my-articles-has-hover-lift';
        }

        if ( ! empty( $options['hover_neon_pulse'] ) ) {
            $wrapper_class .= ' my-articles-has-neon-pulse';
        }

        $columns_mobile    = max( 1, (int) $options['columns_mobile'] );
        $columns_tablet    = max( 1, (int) $options['columns_tablet'] );
        $columns_desktop   = max( 1, (int) $options['columns_desktop'] );
        $columns_ultrawide = max( 1, (int) $options['columns_ultrawide'] );
        $min_card_width    = max( 1, (int) $options['min_card_width'] );

        $active_filters_json = wp_json_encode( $options['active_tax_filters'] ?? array() );
        if ( false === $active_filters_json ) {
            $active_filters_json = '[]';
        }

        $results_region_id = 'my-articles-results-' . $id;
        $wrapper_attributes = array(
            'id'                   => 'my-articles-wrapper-' . $id,
            'class'                => $wrapper_class,
            'data-instance-id'     => $id,
            'data-cols-mobile'     => $columns_mobile,
            'data-cols-tablet'     => $columns_tablet,
            'data-cols-desktop'    => $columns_desktop,
            'data-cols-ultrawide'  => $columns_ultrawide,
            'data-min-card-width'  => $min_card_width,
            'data-search-enabled'  => ! empty( $options['enable_keyword_search'] ) ? 'true' : 'false',
            'data-search-query'    => $options['search_query'],
            'data-search-param'    => $search_query_var,
            'data-sort'            => $options['sort'],
            'data-sort-param'      => $sort_query_var,
            'data-filters'         => $active_filters_json,
            'data-total-results'   => $initial_total_results,
            'role'                 => 'region',
            'aria-live'            => 'polite',
            'aria-label'           => $resolved_aria_label,
            'aria-busy'            => 'false',
            'data-results-target'  => $results_region_id,
        );

        if ( '' !== $inline_styles ) {
            $wrapper_attributes['style'] = $inline_styles;
        }

        $wrapper_attribute_strings = array();
        foreach ( $wrapper_attributes as $attribute => $value ) {
            $wrapper_attribute_strings[] = sprintf( '%s="%s"', esc_attr( $attribute ), esc_attr( (string) $value ) );
        }

        echo '<div ' . implode( ' ', $wrapper_attribute_strings ) . '>';

        if ( ! empty( $options['enable_keyword_search'] ) ) {
            $search_form_classes = 'my-articles-search-form';

            if ( '' !== $options['search_query'] ) {
                $search_form_classes .= ' has-value';
            }

            $search_label        = __( 'Rechercher des articles', 'mon-articles' );
            $search_placeholder  = __( 'Rechercher par mots-clés…', 'mon-articles' );
            $search_submit_text  = __( 'Rechercher', 'mon-articles' );
            $search_clear_label  = __( 'Effacer la recherche', 'mon-articles' );
            $search_input_id     = 'my-articles-search-input-' . $id;
            $search_form_id     = 'my-articles-search-form-' . $id;
            $search_count_id    = 'my-articles-search-count-' . $id;
            $search_datalist_id = '';

            if ( ! empty( $search_suggestions ) ) {
                $search_datalist_id = 'my-articles-search-suggestions-' . $id;
            }

            echo '<form id="' . esc_attr( $search_form_id ) . '" class="' . esc_attr( $search_form_classes ) . '" role="search" aria-label="' . esc_attr( $search_label ) . '" data-instance-id="' . esc_attr( $id ) . '" data-search-param="' . esc_attr( $search_query_var ) . '" data-current-search="' . esc_attr( $options['search_query'] ) . '">';
            echo '<div class="my-articles-search-inner">';
            echo '<label class="my-articles-search-label screen-reader-text" for="' . esc_attr( $search_input_id ) . '">' . esc_html( $search_label ) . '</label>';
            echo '<div class="my-articles-search-controls">';
            echo '<span class="my-articles-search-icon" aria-hidden="true">' . $this->get_search_icon_svg() . '</span>';
            echo '<input type="search" id="' . esc_attr( $search_input_id ) . '" class="my-articles-search-input" name="my-articles-search" value="' . esc_attr( $options['search_query'] ) . '" placeholder="' . esc_attr( $search_placeholder ) . '" autocomplete="off"' . ( $search_datalist_id ? ' list="' . esc_attr( $search_datalist_id ) . '"' : '' ) . ' aria-describedby="' . esc_attr( $search_count_id ) . '" />';
            echo '<button type="submit" class="my-articles-search-submit"><span class="my-articles-search-submit-label">' . esc_html( $search_submit_text ) . '</span><span class="my-articles-search-spinner" aria-hidden="true"></span></button>';
            echo '<button type="button" class="my-articles-search-clear" aria-label="' . esc_attr( $search_clear_label ) . '"><span aria-hidden="true">&times;</span><span class="screen-reader-text">' . esc_html( $search_clear_label ) . '</span></button>';
            echo '</div>';
            echo '<div class="my-articles-search-meta">';
            echo '<output id="' . esc_attr( $search_count_id ) . '" class="my-articles-search-count" role="status" aria-live="polite" aria-atomic="true" data-count="' . esc_attr( $initial_total_results ) . '" for="' . esc_attr( $search_input_id ) . '">' . esc_html( $result_count_label ) . '</output>';
            echo '</div>';

            if ( ! empty( $search_suggestions ) ) {
                echo '<div class="my-articles-search-suggestions" role="list" aria-label="' . esc_attr__( 'Suggestions de recherche', 'mon-articles' ) . '">';
                foreach ( $search_suggestions as $suggestion ) {
                    echo '<button type="button" class="my-articles-search-suggestion" role="listitem" data-suggestion="' . esc_attr( $suggestion ) . '"><span>' . esc_html( $suggestion ) . '</span></button>';
                }
                echo '</div>';
            }

            echo '</div>';

            if ( $search_datalist_id ) {
                echo '<datalist id="' . esc_attr( $search_datalist_id ) . '">';
                foreach ( $search_suggestions as $suggestion ) {
                    echo '<option value="' . esc_attr( $suggestion ) . '"></option>';
                }
                echo '</datalist>';
            }

            echo '</form>';
        }

        $active_tab_id = '';

        if ( ! empty( $options['show_category_filter'] ) && ! empty( $resolved_taxonomy ) && ! empty( $available_categories ) ) {
            $alignment_class = 'filter-align-' . esc_attr( $options['filter_alignment'] );
            $nav_attributes = array(
                'class'      => 'my-articles-filter-nav ' . $alignment_class,
                'aria-label' => $options['category_filter_aria_label'],
            );

            $nav_attribute_strings = array();
            foreach ( $nav_attributes as $attribute => $value ) {
                if ( '' === $value ) {
                    continue;
                }

                $nav_attribute_strings[] = sprintf( '%s="%s"', esc_attr( $attribute ), esc_attr( (string) $value ) );
            }

            $tablist_id = 'my-articles-tabs-' . $id;
            echo '<nav ' . implode( ' ', $nav_attribute_strings ) . '><ul role="tablist" id="' . esc_attr( $tablist_id ) . '">';
            $default_cat   = $options['term'] ?? '';
            $is_all_active = '' === $default_cat || 'all' === $default_cat;

            $all_tab_id = 'my-articles-tab-' . $id . '-all';
            if ( $is_all_active ) {
                $active_tab_id = $all_tab_id;
            }

            echo '<li class="' . ( $is_all_active ? 'active' : '' ) . '" role="presentation">';
            echo '<button type="button" role="tab" id="' . esc_attr( $all_tab_id ) . '" data-category="all" aria-controls="' . esc_attr( $results_region_id ) . '" aria-selected="' . ( $is_all_active ? 'true' : 'false' ) . '" tabindex="' . ( $is_all_active ? '0' : '-1' ) . '">' . esc_html__( 'Tout', 'mon-articles' ) . '</button>';
            echo '</li>';

            foreach ( $available_categories as $category ) {
                $is_active = ( $default_cat === $category->slug );
                $tab_id    = 'my-articles-tab-' . $id . '-' . $category->slug;

                if ( $is_active ) {
                    $active_tab_id = $tab_id;
                }

                echo '<li class="' . ( $is_active ? 'active' : '' ) . '" role="presentation">';
                echo '<button type="button" role="tab" id="' . esc_attr( $tab_id ) . '" data-category="' . esc_attr( $category->slug ) . '" aria-controls="' . esc_attr( $results_region_id ) . '" aria-selected="' . ( $is_active ? 'true' : 'false' ) . '" tabindex="' . ( $is_active ? '0' : '-1' ) . '">' . esc_html( $category->name ) . '</button>';
                echo '</li>';
            }

            echo '</ul></nav>';
        }
        $displayed_pinned_ids = array();

        $posts_per_page_for_render    = $render_limit > 0 ? $render_limit : $effective_posts_per_page;
        $posts_per_page_for_slideshow = $effective_posts_per_page;

        if ( $is_unlimited && 0 === $posts_per_page_for_render ) {
            $posts_per_page_for_render = 0;
        }

        if ( $is_unlimited && 0 === $posts_per_page_for_slideshow ) {
            $posts_per_page_for_slideshow = 0;
        }

        $results_attributes = array(
            'id'                  => $results_region_id,
            'class'               => 'my-articles-results',
            'role'                => 'tabpanel',
            'data-my-articles-role' => 'results',
            'aria-live'           => 'polite',
            'aria-busy'           => 'false',
        );

        if ( ! empty( $active_tab_id ) ) {
            $results_attributes['aria-labelledby'] = $active_tab_id;
        }

        echo '<div ' . implode( ' ', array_map( function ( $attribute, $value ) {
            return sprintf( '%s="%s"', esc_attr( $attribute ), esc_attr( (string) $value ) );
        }, array_keys( $results_attributes ), $results_attributes ) ) . '>';

        $adapter_items = self::collect_content_adapter_items(
            $options,
            array(
                'instance_id'          => $id,
                'render_limit'         => $render_limit,
                'display_mode'         => $options['display_mode'] ?? '',
                'rendered_pinned_ids'  => $state['rendered_pinned_ids'] ?? array(),
            )
        );

        if ( $options['display_mode'] === 'slideshow' ) {
            $this->render_slideshow( $pinned_query, $articles_query, $options, $posts_per_page_for_slideshow, $results_region_id, $adapter_items, $id );
        } elseif ( $options['display_mode'] === 'list' ) {
            $displayed_pinned_ids = $this->render_list( $pinned_query, $articles_query, $options, $posts_per_page_for_render, $results_region_id, $adapter_items );
            if ( ! is_array( $displayed_pinned_ids ) ) {
                $displayed_pinned_ids = array();
            }
        } else {
            $displayed_pinned_ids = $this->render_grid( $pinned_query, $articles_query, $options, $posts_per_page_for_render, $results_region_id, $adapter_items );
            if ( ! is_array( $displayed_pinned_ids ) ) {
                $displayed_pinned_ids = array();
            }
        }

        echo '</div>';

        if ( $paged === 1 ) {
            $first_page_projected_pinned = count( $displayed_pinned_ids );
            if ( 0 === $total_matching_pinned && ! empty( $displayed_pinned_ids ) ) {
                $total_matching_pinned = count( $displayed_pinned_ids );
            }
        }

        if ($options['display_mode'] === 'grid' || $options['display_mode'] === 'list') {
            $total_regular_posts = (int) $state['total_regular_posts'];

            if ( 0 === $total_regular_posts && ! ( $articles_query instanceof WP_Query ) ) {
                $count_query_args = [
                    'post_type' => $options['post_type'],
                    'post_status' => 'publish',
                    'posts_per_page' => 1,
                    'post__not_in' => $all_excluded_ids,
                    'ignore_sticky_posts' => (int) $options['ignore_native_sticky'],
                    'fields' => 'ids',
                ];

                if ( '' !== $options['search_query'] ) {
                    $count_query_args['s'] = $options['search_query'];
                }

                if ( ! empty( $options['meta_query'] ) && is_array( $options['meta_query'] ) ) {
                    $count_query_args = self::merge_meta_query_clauses( $count_query_args, $options['meta_query'] );
                }

                if ( '' !== $resolved_taxonomy && '' !== $options['term'] && 'all' !== $options['term'] ) {
                    $count_query_args['tax_query'] = [[
                        'taxonomy' => $resolved_taxonomy,
                        'field'    => 'slug',
                        'terms'    => $options['term'],
                    ]];
                }

                $count_query = new WP_Query($count_query_args);
                $total_regular_posts = (int) $count_query->found_posts;
            }

            $pagination_context = array(
                'current_page' => $paged,
            );

            if ( ! empty( $state['is_unlimited'] ) ) {
                $pagination_context['unlimited_page_size'] = $state['unlimited_batch_size'];
                $pagination_context['analytics_page_size'] = $state['unlimited_batch_size'];
            }

            $pagination_totals = my_articles_calculate_total_pages(
                $total_matching_pinned,
                $total_regular_posts,
                $effective_posts_per_page,
                $pagination_context
            );
            $total_pages = $pagination_totals['total_pages'];

            if ($options['pagination_mode'] === 'load_more') {
                if ( $total_pages > 1 && $paged < $total_pages) {
                    $next_page = min( $paged + 1, $total_pages );
                    $load_more_pinned_ids = ! empty( $displayed_pinned_ids ) ? array_map( 'absint', $displayed_pinned_ids ) : array();
                    echo '<div class="my-articles-load-more-container"><button class="my-articles-load-more-btn" data-instance-id="' . esc_attr($id) . '" data-paged="' . esc_attr( $next_page ) . '" data-total-pages="' . esc_attr($total_pages) . '" data-pinned-ids="' . esc_attr(implode(',', $load_more_pinned_ids)) . '" data-category="' . esc_attr($options['term']) . '" data-search="' . esc_attr( $options['search_query'] ) . '" data-sort="' . esc_attr( $options['sort'] ) . '" data-filters="' . esc_attr( $active_filters_json ) . '" data-auto-load="' . esc_attr( $options['load_more_auto'] ? '1' : '0' ) . '">' . esc_html__( 'Charger plus', 'mon-articles' ) . '</button></div>';
                }
            } elseif ($options['pagination_mode'] === 'numbered') {
                $pagination_query_args = array();
                if ( '' !== $options['term'] ) {
                    $pagination_query_args[ $category_query_var ] = $options['term'];
                }
                $this->render_numbered_pagination($total_pages, $paged, $paged_var, $pagination_query_args);
            }
        }
        
        if ( ! empty( $options['enable_debug_mode'] ) ) {
            echo '<div style="background: #fff; border: 2px solid red; padding: 15px; margin: 20px 0; text-align: left; color: #000; font-family: monospace; line-height: 1.6; clear: both;">';
            echo '<h4 style="margin: 0 0 10px 0;">-- DEBUG MODE --</h4>';
            echo '<ul>';
            echo '<li>Réglage "Lazy Load" activé : <strong>' . ( ! empty( $options['enable_lazy_load'] ) ? 'Oui' : 'Non' ) . '</strong></li>';
            echo '<li>Statut du script lazysizes : <strong id="lazysizes-status-' . esc_attr( $id ) . '" style="color: red;">En attente...</strong></li>';
            echo '</ul>';
            echo '</div>';

            wp_enqueue_script( 'my-articles-debug-helper' );

            $status_span_id = 'lazysizes-status-' . $id;
            $debug_script   = sprintf(
                "document.addEventListener('DOMContentLoaded',function(){var statusSpan=document.getElementById(%s);if(!statusSpan){return;}setTimeout(function(){if(window.lazySizes){statusSpan.textContent=%s;statusSpan.style.color='green';}else{statusSpan.textContent=%s;}},500);});",
                wp_json_encode( $status_span_id ),
                wp_json_encode( '✅ Chargé et actif !' ),
                wp_json_encode( '❌ ERREUR : Non trouvé !' )
            );

            wp_add_inline_script( 'my-articles-debug-helper', $debug_script );
        }
        
        echo '</div>';
        
        wp_reset_postdata();
        return ob_get_clean();
    }
    
    private function render_articles_in_container( $pinned_query, $regular_query, $options, $posts_per_page, $container_class, $results_region_id = '', array $adapter_items = array() ) {
        $has_rendered_posts   = false;
        $render_limit         = max( 0, (int) $posts_per_page );
        $should_limit         = $render_limit > 0;
        $rendered_count       = 0;
        $displayed_pinned_ids = array();

        $container_attributes = array(
            'class' => $container_class,
        );

        if ( $results_region_id ) {
            $container_attributes['data-controls'] = $results_region_id;
        }

        $container_attribute_strings = array();
        foreach ( $container_attributes as $attribute => $value ) {
            $container_attribute_strings[] = sprintf( '%s="%s"', esc_attr( $attribute ), esc_attr( (string) $value ) );
        }

        echo '<div ' . implode( ' ', $container_attribute_strings ) . '>';

        echo $this->get_skeleton_placeholder_markup( $container_class, $options, $render_limit );

        if ( $pinned_query instanceof WP_Query && $pinned_query->have_posts() ) {
            while ( $pinned_query->have_posts() && ( ! $should_limit || $rendered_count < $render_limit ) ) {
                $pinned_query->the_post();
                $this->render_article_item( $options, true );
                $has_rendered_posts   = true;
                $rendered_count++;
                $pinned_id = absint( get_the_ID() );
                if ( $pinned_id > 0 ) {
                    $displayed_pinned_ids[] = $pinned_id;
                }
            }
        }

        if ( $regular_query instanceof WP_Query && $regular_query->have_posts() ) {
            while ( $regular_query->have_posts() && ( ! $should_limit || $rendered_count < $render_limit ) ) {
                $regular_query->the_post();
                $this->render_article_item( $options, false );
                $has_rendered_posts = true;
                $rendered_count++;
            }
        }

        $adapter_post_processed = false;

        if ( ! empty( $adapter_items ) ) {
            foreach ( $adapter_items as $adapter_item ) {
                if ( $should_limit && $rendered_count >= $render_limit ) {
                    break;
                }

                if ( ! is_array( $adapter_item ) || empty( $adapter_item['type'] ) ) {
                    continue;
                }

                if ( 'post' === $adapter_item['type'] && isset( $adapter_item['post'] ) && $adapter_item['post'] instanceof WP_Post ) {
                    $adapter_post_processed = true;
                    $post = $adapter_item['post'];
                    setup_postdata( $post );
                    $this->render_article_item( $options, false );
                    $has_rendered_posts = true;
                    $rendered_count++;
                } elseif ( 'html' === $adapter_item['type'] && isset( $adapter_item['html'] ) ) {
                    echo $adapter_item['html'];
                    $has_rendered_posts = true;
                    $rendered_count++;
                }
            }

            if ( $adapter_post_processed ) {
                wp_reset_postdata();
            }
        }

        echo '</div>';

        if ( ! $has_rendered_posts ) {
            $this->render_empty_state_message();
        }

        if ( $pinned_query instanceof WP_Query || $regular_query instanceof WP_Query ) {
            wp_reset_postdata();
        }

        return $displayed_pinned_ids;
    }

    public function get_skeleton_placeholder_markup( $container_class, array $options, $render_limit ) {
        $layout = false !== strpos( $container_class, 'list' ) ? 'list' : 'grid';
        $placeholder_count = (int) $render_limit;

        if ( $placeholder_count <= 0 ) {
            $placeholder_count = isset( $options['posts_per_page'] ) ? (int) $options['posts_per_page'] : 0;
        }

        if ( $placeholder_count <= 0 ) {
            $placeholder_count = 6;
        }

        $placeholder_count = min( max( $placeholder_count, 3 ), 12 );

        ob_start();

        echo '<div class="my-articles-skeleton my-articles-skeleton--' . esc_attr( $layout ) . '" aria-hidden="true" role="presentation">';

        for ( $i = 0; $i < $placeholder_count; $i++ ) {
            echo '<div class="my-articles-skeleton__item">';
            echo '<div class="my-articles-skeleton__thumbnail"></div>';
            echo '<div class="my-articles-skeleton__body">';
            echo '<span class="my-articles-skeleton__line my-articles-skeleton__line--title"></span>';
            echo '<span class="my-articles-skeleton__line my-articles-skeleton__line--meta"></span>';
            echo '<span class="my-articles-skeleton__line"></span>';
            echo '<span class="my-articles-skeleton__line my-articles-skeleton__line--short"></span>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';

        return ob_get_clean();
    }

    private function render_list( $pinned_query, $regular_query, $options, $posts_per_page, $results_region_id = '', array $adapter_items = array() ) {
        return $this->render_articles_in_container( $pinned_query, $regular_query, $options, $posts_per_page, 'my-articles-list-content', $results_region_id, $adapter_items );
    }

    private function render_grid( $pinned_query, $regular_query, $options, $posts_per_page, $results_region_id = '', array $adapter_items = array() ) {
        return $this->render_articles_in_container( $pinned_query, $regular_query, $options, $posts_per_page, 'my-articles-grid-content', $results_region_id, $adapter_items );
    }

    private function render_slideshow( $pinned_query, $regular_query, $options, $posts_per_page, $results_region_id = '', array $adapter_items = array(), $instance_id = 0 ) {
        $is_unlimited       = (int) $posts_per_page <= 0;
        $total_posts_needed = $is_unlimited ? PHP_INT_MAX : (int) $posts_per_page;

        $carousel_label        = esc_attr( __( 'Carrousel des articles', 'mon-articles' ) );
        $pagination_label      = esc_attr( __( 'Pagination du carrousel', 'mon-articles' ) );
        $next_slide_label      = esc_attr( __( 'Aller à la diapositive suivante', 'mon-articles' ) );
        $previous_slide_label  = esc_attr( __( 'Revenir à la diapositive précédente', 'mon-articles' ) );

        $slider_id             = 'my-articles-slideshow-' . (int) $instance_id;
        $slide_role_description = esc_attr__( 'Diapositive', 'mon-articles' );

        echo '<div class="swiper-accessibility-wrapper" role="region" aria-roledescription="carousel" aria-label="' . $carousel_label . '">';
        $show_navigation  = ! empty( $options['slideshow_show_navigation'] );
        $show_pagination  = ! empty( $options['slideshow_show_pagination'] );

        echo '<div class="swiper-container" id="' . esc_attr( $slider_id ) . '" aria-live="polite"><div class="swiper-wrapper">';
        $post_count     = 0;
        $slide_position = 1;

        if ( $pinned_query && $pinned_query->have_posts() ) {
            while ( $pinned_query->have_posts() && $post_count < $total_posts_needed ) {
                $pinned_query->the_post();
                $is_active = 0 === $post_count;
                $this->render_accessible_slide_opening_tag( $is_active, $slide_position, $slide_role_description );
                $this->render_article_item( $options, true );
                echo '</div>';
                $post_count++;
                $slide_position++;
            }
        }

        if ( $regular_query && $regular_query->have_posts() ) {
            while ( $regular_query->have_posts() && $post_count < $total_posts_needed ) {
                $regular_query->the_post();
                $is_active = 0 === $post_count;
                $this->render_accessible_slide_opening_tag( $is_active, $slide_position, $slide_role_description );
                $this->render_article_item( $options, false );
                echo '</div>';
                $post_count++;
                $slide_position++;
            }
        }

        $adapter_post_processed = false;

        if ( ! empty( $adapter_items ) ) {
            foreach ( $adapter_items as $adapter_item ) {
                if ( $post_count >= $total_posts_needed ) {
                    break;
                }

                if ( ! is_array( $adapter_item ) || empty( $adapter_item['type'] ) ) {
                    continue;
                }

                if ( 'post' === $adapter_item['type'] && isset( $adapter_item['post'] ) && $adapter_item['post'] instanceof WP_Post ) {
                    $adapter_post_processed = true;
                    $post = $adapter_item['post'];
                    setup_postdata( $post );
                    $this->render_accessible_slide_opening_tag( 0 === $post_count, $slide_position, $slide_role_description );
                    $this->render_article_item( $options, false );
                    echo '</div>';
                    $post_count++;
                    $slide_position++;
                } elseif ( 'html' === $adapter_item['type'] && isset( $adapter_item['html'] ) ) {
                    $this->render_accessible_slide_opening_tag( 0 === $post_count, $slide_position, $slide_role_description, 'swiper-slide swiper-slide-external' );
                    echo $adapter_item['html'];
                    echo '</div>';
                    $post_count++;
                    $slide_position++;
                }
            }

            if ( $adapter_post_processed ) {
                wp_reset_postdata();
            }
        }

        if ( 0 === $post_count ) {
            $this->render_empty_state_message( true );
        }

        echo '</div>';

        if ( $show_pagination ) {
            echo '<div class="swiper-pagination" role="tablist" aria-label="' . $pagination_label . '"></div>';
        }

        $controls_attribute = '';
        if ( $results_region_id ) {
            $controls_attribute = ' aria-controls="' . esc_attr( $results_region_id ) . '"';
        }

        if ( $show_navigation ) {
            echo '<button type="button" class="swiper-button-next" aria-label="' . $next_slide_label . '"' . $controls_attribute . '></button>';
            echo '<button type="button" class="swiper-button-prev" aria-label="' . $previous_slide_label . '"' . $controls_attribute . '></button>';
        }
        echo '</div>';
        echo '</div>';

        if ( $pinned_query instanceof WP_Query || $regular_query instanceof WP_Query ) {
            wp_reset_postdata();
        }
    }

    private function render_accessible_slide_opening_tag( $is_active, $position, $role_description, $class_names = 'swiper-slide' ) {
        $class_names = trim( $class_names );

        $attributes = array(
            'class'                 => $class_names,
            'role'                  => 'group',
            'aria-roledescription'  => $role_description,
            'data-slide-position'    => max( 1, (int) $position ),
            'aria-hidden'           => $is_active ? 'false' : 'true',
        );

        if ( ! $is_active ) {
            $attributes['tabindex']             = '-1';
            $attributes['data-my-articles-inert'] = 'true';
        }

        $attribute_strings = array();
        foreach ( $attributes as $attribute => $value ) {
            if ( '' === $value ) {
                continue;
            }

            $attribute_strings[] = sprintf( '%s="%s"', esc_attr( $attribute ), esc_attr( (string) $value ) );
        }

        echo '<div ' . implode( ' ', $attribute_strings ) . '>';
    }

    public function get_empty_state_html() {
        return '<p style="text-align: center; width: 100%; padding: 20px;">' . esc_html__( 'Aucun article trouvé dans cette catégorie.', 'mon-articles' ) . '</p>';
    }

    public function get_empty_state_slide_html() {
        ob_start();
        $this->render_accessible_slide_opening_tag( true, 1, esc_attr__( 'Diapositive', 'mon-articles' ), 'swiper-slide swiper-slide-empty' );
        echo $this->get_empty_state_html();
        echo '</div>';

        return ob_get_clean();
    }

    private function render_empty_state_message( $wrap_for_swiper = false ) {
        if ( $wrap_for_swiper ) {
            ob_start();
            $this->render_accessible_slide_opening_tag( true, 1, esc_attr__( 'Diapositive', 'mon-articles' ), 'swiper-slide swiper-slide-empty' );
            echo $this->get_empty_state_html();
            echo '</div>';
            echo ob_get_clean();
            return;
        }

        echo $this->get_empty_state_html();
    }

    public function render_article_item($options, $is_pinned = false) {
        $item_classes = 'my-article-item';
        if ($is_pinned) { $item_classes .= ' is-pinned'; }
        $display_mode = $options['display_mode'] ?? 'grid';
        $taxonomy = $options['resolved_taxonomy'] ?? self::resolve_taxonomy( $options );
        $enable_lazy_load = !empty($options['enable_lazy_load']);
        $excerpt_more = __( '…', 'mon-articles' );
        ?>
        <article class="<?php echo esc_attr($item_classes); ?>">
            <?php
            if ($display_mode === 'list') {
                $this->render_article_common_block($options, $is_pinned, $taxonomy, $enable_lazy_load, 'article-content-wrapper', $excerpt_more);
            } else {
                $this->render_article_common_block($options, $is_pinned, $taxonomy, $enable_lazy_load, 'article-title-wrapper', '');
            }
            ?>
        </article>
        <?php
    }

    private function render_article_common_block($options, $is_pinned, $taxonomy, $enable_lazy_load, $wrapper_class, $excerpt_more) {
        $permalink     = get_permalink();
        $escaped_link  = esc_url( $permalink );
        $raw_title     = get_the_title();
        $title_attr    = esc_attr( $raw_title );
        $title_display = esc_html( $raw_title );
        $title_plain   = trim( wp_strip_all_tags( $raw_title ) );
        $term_names    = array();

        if ( $options['show_category'] && ! empty( $taxonomy ) ) {
            $terms = get_the_terms( get_the_ID(), $taxonomy );

            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $term_names = array_map( 'sanitize_text_field', wp_list_pluck( $terms, 'name' ) );
            }
        }
        $title_id       = 'my-article-title-' . get_the_ID();
        $excerpt_id     = $title_id . '-excerpt';
        $read_more_text = isset( $options['excerpt_more_text'] ) ? trim( wp_strip_all_tags( (string) $options['excerpt_more_text'] ) ) : '';
        $has_read_more  = '' !== $read_more_text;

        $excerpt_markup        = '';
        $should_render_excerpt = false;

        if ( ! empty( $options['show_excerpt'] ) ) {
            $excerpt_length    = isset( $options['excerpt_length'] ) ? (int) $options['excerpt_length'] : 0;
            $raw_excerpt       = get_the_excerpt();
            $trimmed_excerpt   = '';

            if ( $excerpt_length > 0 ) {
                $trimmed_excerpt = wp_trim_words( $raw_excerpt, $excerpt_length, $excerpt_more );
            }

            $has_excerpt_content = '' !== trim( strip_tags( $trimmed_excerpt ) );

            if ( $has_excerpt_content || $has_read_more ) {
                ob_start();
                ?>
                <div class="my-article-excerpt" id="<?php echo esc_attr( $excerpt_id ); ?>">
                    <?php
                    if ( $has_excerpt_content ) {
                        echo wp_kses_post( $trimmed_excerpt );
                    }

                    if ( $has_read_more ) {
                        ?>
                        <span class="my-article-read-more" aria-hidden="true"><?php echo esc_html( $read_more_text ); ?></span>
                        <?php
                    }
                    ?>
                </div>
                <?php
                $excerpt_markup        = (string) ob_get_clean();
                $should_render_excerpt = '' !== trim( $excerpt_markup );
            }
        }

        $link_attributes = array(
            'class'           => 'my-article-link',
            'href'            => $escaped_link,
            'aria-labelledby' => $title_id,
        );

        if ( $should_render_excerpt ) {
            $link_attributes['aria-describedby'] = $excerpt_id;
        }

        ?>
        <a
            <?php
            foreach ( $link_attributes as $attribute => $value ) {
                if ( '' === $value ) {
                    continue;
                }

                printf( ' %s="%s"', esc_attr( $attribute ), esc_attr( (string) $value ) );
            }
            ?>
        >
            <div class="article-thumbnail-wrapper">
                <?php if ($is_pinned && !empty($options['pinned_show_badge'])) : ?><span class="my-article-badge"><?php echo esc_html($options['pinned_badge_text']); ?></span><?php endif; ?>
                <span class="article-thumbnail-link">
                <?php if (has_post_thumbnail()):
                    $image_id = get_post_thumbnail_id();
                    $thumbnail_html = $this->get_article_thumbnail_html( $image_id, $title_attr, $enable_lazy_load );

                    if ( '' !== $thumbnail_html ) {
                        echo $thumbnail_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    } else {
                        $fallback_alt = $this->resolve_thumbnail_alt_text( $image_id, $title_attr );
                        the_post_thumbnail( 'large', array( 'alt' => $fallback_alt ) );
                    }
                else: ?>
                    <?php $fallback_placeholder = MY_ARTICLES_PLUGIN_URL . 'assets/images/placeholder.svg'; ?>
                    <img src="<?php echo esc_url($fallback_placeholder); ?>" alt="<?php esc_attr_e('Image non disponible', 'mon-articles'); ?>">
                <?php endif; ?>
                </span>
            </div>
            <div class="<?php echo esc_attr($wrapper_class); ?>">
                <h2 class="article-title" id="<?php echo esc_attr( $title_id ); ?>">
                    <span class="article-title-link"><?php echo $title_display; ?></span>
                </h2>
                <?php if ($options['show_category'] || $options['show_author'] || $options['show_date']) : ?>
                    <div class="article-meta">
                        <?php if ($options['show_category'] && !empty($taxonomy) && !empty($term_names)) : ?>
                            <span class="article-category"><?php echo esc_html( implode( ', ', $term_names ) ); ?></span>
                        <?php endif; ?>
                        <?php if ( $options['show_author'] ) : ?>
                            <span class="article-author"><?php printf('%s %s', esc_html__( 'par', 'mon-articles' ), esc_html( get_the_author() ) ); ?></span>
                        <?php endif; ?>
                        <?php if ($options['show_date']) : ?>
                            <span class="article-date"><?php echo esc_html(get_the_date()); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ( $should_render_excerpt ) { echo $excerpt_markup; } ?>
            </div>
        </a>
        <?php
    }

    private function resolve_thumbnail_alt_text( $image_id, $title_attr ) {
        $raw_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

        if ( ! is_string( $raw_alt ) ) {
            $raw_alt = '';
        } else {
            $raw_alt = trim( $raw_alt );
        }

        if ( '' === $raw_alt ) {
            $raw_alt = $title_attr;
        }

        return $raw_alt;
    }

    private function get_article_thumbnail_html( $image_id, $title_attr, $enable_lazy_load ) {
        $size            = 'large';
        $placeholder_src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        $alt_text        = $this->resolve_thumbnail_alt_text( $image_id, $title_attr );

        if ( $enable_lazy_load ) {
            $image_data = wp_get_attachment_image_src( $image_id, $size );

            if ( empty( $image_data ) || empty( $image_data[0] ) ) {
                return '';
            }

            $image_src    = $image_data[0];
            $image_width  = isset( $image_data[1] ) ? (int) $image_data[1] : 0;
            $image_height = isset( $image_data[2] ) ? (int) $image_data[2] : 0;

            $image_srcset = wp_get_attachment_image_srcset( $image_id, $size );

            $attributes = array(
                'src'        => $placeholder_src,
                'class'      => 'attachment-large size-large wp-post-image lazyload',
                'alt'        => $alt_text,
                'data-sizes' => 'auto',
                'data-src'   => $image_src,
                'decoding'   => 'async',
                'loading'    => 'lazy',
            );

            if ( ! empty( $image_srcset ) ) {
                $attributes['data-srcset'] = $image_srcset;
            }

            if ( $image_width > 0 ) {
                $attributes['width'] = $image_width;
            }

            if ( $image_height > 0 ) {
                $attributes['height'] = $image_height;
            }

            $html = '<img';

            foreach ( $attributes as $attr_name => $attr_value ) {
                if ( '' === $attr_value && 0 !== $attr_value ) {
                    continue;
                }

                if ( 'data-src' === $attr_name ) {
                    $escaped_value = esc_url( $attr_value );
                } elseif ( 'data-srcset' === $attr_name ) {
                    $escaped_value = esc_attr( $attr_value );
                } elseif ( 'src' === $attr_name && 0 !== strncmp( $attr_value, 'data:', 5 ) ) {
                    $escaped_value = esc_url( $attr_value );
                } else {
                    $escaped_value = esc_attr( $attr_value );
                }

                $html .= ' ' . esc_attr( $attr_name ) . '="' . $escaped_value . '"';
            }

            $html .= ' />';

            return $html;
        }

        $attributes = array(
            'class'    => 'attachment-large size-large wp-post-image',
            'alt'      => $alt_text,
            'decoding' => 'async',
            'loading'  => 'eager',
        );

        $image_html = wp_get_attachment_image( $image_id, $size, false, $attributes );

        if ( ! $enable_lazy_load && is_string( $image_html ) && '' !== $image_html && false !== strpos( $image_html, 'loading="lazy"' ) ) {
            $image_html = str_replace( 'loading="lazy"', 'loading="eager"', $image_html );
        }

        return $image_html;
    }

    private function enqueue_swiper_scripts($options, $instance_id) {
        wp_enqueue_style('swiper-css');
        wp_enqueue_script('swiper-js');
        wp_enqueue_script('my-articles-swiper-init');

        $autoplay_settings = array(
            'enabled'                 => ! empty( $options['slideshow_autoplay'] ),
            'delay'                   => (int) $options['slideshow_delay'],
            'pause_on_interaction'    => ! empty( $options['slideshow_pause_on_interaction'] ),
            'pause_on_mouse_enter'    => ! empty( $options['slideshow_pause_on_mouse_enter'] ),
            'respect_reduced_motion'  => ! empty( $options['slideshow_respect_reduced_motion'] ),
        );

        $slider_id = 'my-articles-slideshow-' . (int) $instance_id;

        $localized_settings = array(
            'columns_mobile'                  => $options['columns_mobile'],
            'columns_tablet'                  => $options['columns_tablet'],
            'columns_desktop'                 => $options['columns_desktop'],
            'columns_ultrawide'               => $options['columns_ultrawide'],
            'gap_size'                        => $options['gap_size'],
            'loop'                            => ! empty( $options['slideshow_loop'] ),
            'autoplay'                        => $autoplay_settings,
            'show_navigation'                 => ! empty( $options['slideshow_show_navigation'] ),
            'show_pagination'                 => ! empty( $options['slideshow_show_pagination'] ),
            'respect_reduced_motion'          => ! empty( $options['slideshow_respect_reduced_motion'] ),
            'container_selector'              => '#my-articles-wrapper-' . $instance_id . ' .swiper-container',
            'controlled_slider_selector'      => '#' . $slider_id,
            'a11y_prev_slide_message'         => __( 'Diapositive précédente', 'mon-articles' ),
            'a11y_next_slide_message'         => __( 'Diapositive suivante', 'mon-articles' ),
            'a11y_first_slide_message'        => __( 'Première diapositive', 'mon-articles' ),
            'a11y_last_slide_message'         => __( 'Dernière diapositive', 'mon-articles' ),
            'a11y_pagination_bullet_message'  => __( 'Aller à la diapositive {{index}}', 'mon-articles' ),
            'a11y_slide_label_message'        => __( 'Diapositive {{index}} sur {{slidesLength}}', 'mon-articles' ),
            'a11y_container_message'          => __( 'Ce carrousel est navigable au clavier : utilisez les flèches pour changer de diapositive.', 'mon-articles' ),
            'a11y_container_role_description' => __( 'Carrousel d\'articles', 'mon-articles' ),
            'a11y_item_role_description'      => __( 'Diapositive', 'mon-articles' ),
        );

        if ( class_exists( 'My_Articles_Enqueue' ) ) {
            $enqueue = My_Articles_Enqueue::get_instance();

            if ( $enqueue instanceof My_Articles_Enqueue ) {
                $enqueue->register_script_data(
                    'my-articles-swiper-init',
                    'myArticlesSwiperSettings',
                    array(
                        (string) $instance_id => $localized_settings,
                    )
                );

                return;
            }
        }

        if ( function_exists( 'wp_localize_script' ) ) {
            wp_localize_script(
                'my-articles-swiper-init',
                'myArticlesSwiperSettings_' . $instance_id,
                $localized_settings
            );
        }
    }
    
    /**
     * Builds the HTML markup for numbered pagination links.
     *
     * @param int    $total_pages   Total number of pages available.
     * @param int    $current_page  Current page number.
     * @param string $query_var     Query variable used for the pagination links.
     * @param array  $query_args    Additional query arguments to preserve when generating links.
     * @param string $referer       Base URL to use when generating the pagination links. When empty, the
     *                              current request derived from $wp->request is used as a fallback.
     *
     * @return string HTML markup for the pagination component or an empty string if no pagination is needed.
     */
    public function get_numbered_pagination_html( $total_pages, $current_page, $query_var, array $query_args, $referer = '' ) {
        if ( $total_pages <= 1 ) {
            return '';
        }

        $site_home = home_url();

        $base_url = my_articles_normalize_internal_url( $referer, $site_home );

        if ( '' === $base_url ) {
            global $wp;

            $request_path = '';
            if ( isset( $wp ) && is_object( $wp ) && isset( $wp->request ) ) {
                $request_path = $wp->request;
            }

            $fallback_base = home_url( add_query_arg( array(), $request_path ) );

            if ( ! empty( $_GET ) ) {
                $raw_query_args = wp_unslash( $_GET );
                if ( is_array( $raw_query_args ) ) {
                    $sanitized_query_args = map_deep( $raw_query_args, 'sanitize_text_field' );
                } else {
                    $sanitized_query_args = array();
                }

                if ( isset( $sanitized_query_args[ $query_var ] ) ) {
                    unset( $sanitized_query_args[ $query_var ] );
                }

                if ( ! empty( $sanitized_query_args ) ) {
                    $fallback_base = add_query_arg( $sanitized_query_args, $fallback_base );
                }
            }

            $base_url = my_articles_normalize_internal_url( $fallback_base, $site_home );
        }

        if ( '' === $base_url ) {
            return '';
        }

        $base_url = remove_query_arg( $paged_var, $base_url );

        $existing_args = array();
        $query_string  = wp_parse_url( $base_url, PHP_URL_QUERY );
        if ( $query_string ) {
            wp_parse_str( $query_string, $existing_args );
            $existing_args = map_deep( $existing_args, 'sanitize_text_field' );

            if ( ! empty( $existing_args ) ) {
                $base_url = remove_query_arg( array_keys( $existing_args ), $base_url );
            }
        }

        $clean_additional_args = array();
        if ( ! empty( $query_args ) ) {
            foreach ( $query_args as $key => $value ) {
                $clean_key = sanitize_key( $key );
                if ( '' === $clean_key ) {
                    continue;
                }

                if ( is_array( $value ) ) {
                    continue;
                }

                $value       = (string) $value;
                $clean_value = sanitize_text_field( $value );

                if ( '' === $clean_value && '0' !== $clean_value ) {
                    continue;
                }

                $clean_additional_args[ $clean_key ] = $clean_value;
            }
        }

        $query_args = array_merge( $existing_args, $clean_additional_args );

        $base_without_query = rtrim( $base_url, '?' );
        $format             = ( strpos( $base_without_query, '?' ) !== false ? '&' : '?' ) . $query_var . '=%#%';

        $pagination_links = paginate_links(
            [
                'base'      => $base_without_query . '%_%',
                'format'    => $format,
                'add_args'  => ! empty( $query_args ) ? $query_args : false,
                'current'   => max( 1, (int) $current_page ),
                'total'     => max( 1, (int) $total_pages ),
                'prev_text' => __( '&laquo; Précédent', 'mon-articles' ),
                'next_text' => __( 'Suivant &raquo;', 'mon-articles' ),
            ]
        );

        if ( empty( $pagination_links ) ) {
            return '';
        }

        return '<nav class="my-articles-pagination">' . $pagination_links . '</nav>';
    }

    private function render_numbered_pagination( $total_pages, $current_page, $query_var, array $query_args = array(), $referer = '' ) {
        $pagination_html = $this->get_numbered_pagination_html( $total_pages, $current_page, $query_var, $query_args, $referer );

        if ( ! empty( $pagination_html ) ) {
            echo $pagination_html;
        }
    }

    private function build_search_suggestions( array $options, $available_categories, $pinned_query, $regular_query ) {
        $suggestions = array();

        if ( $pinned_query instanceof WP_Query && ! empty( $pinned_query->posts ) ) {
            $suggestions = array_merge( $suggestions, wp_list_pluck( $pinned_query->posts, 'post_title' ) );
        }

        if ( $regular_query instanceof WP_Query && ! empty( $regular_query->posts ) ) {
            $suggestions = array_merge( $suggestions, wp_list_pluck( $regular_query->posts, 'post_title' ) );
        }

        if ( is_array( $available_categories ) ) {
            foreach ( $available_categories as $category ) {
                if ( isset( $category->name ) ) {
                    $suggestions[] = (string) $category->name;
                }
            }
        }

        $suggestions = array_map( 'wp_strip_all_tags', array_map( 'strval', $suggestions ) );
        $suggestions = array_map( 'trim', $suggestions );
        $suggestions = array_filter( $suggestions, 'strlen' );
        $suggestions = array_values( array_unique( $suggestions ) );

        /**
         * Filters the search suggestions displayed inside the module search bar.
         *
         * @param array    $suggestions        Default suggestions built from current posts and categories.
         * @param array    $options            Normalized module options.
         * @param WP_Query $pinned_query       Query used to fetch pinned posts.
         * @param WP_Query $regular_query      Query used to fetch regular posts.
         * @param array    $available_categories Terms exposed in the category filter.
         */
        $filtered_suggestions = apply_filters( 'my_articles_search_suggestions', $suggestions, $options, $pinned_query, $regular_query, $available_categories );

        if ( is_array( $filtered_suggestions ) ) {
            $suggestions = $filtered_suggestions;
        }

        $suggestions = array_map( 'strval', $suggestions );
        $suggestions = array_map( 'wp_strip_all_tags', $suggestions );
        $suggestions = array_map( 'trim', $suggestions );
        $suggestions = array_values( array_filter( $suggestions, 'strlen' ) );

        return array_slice( $suggestions, 0, 8 );
    }

    private function format_result_count_label( $total_results ) {
        $total = max( 0, (int) $total_results );

        if ( 0 === $total ) {
            return __( 'Aucun résultat', 'mon-articles' );
        }

        $formatted_total = number_format_i18n( $total );

        return sprintf(
            _n( '%s résultat', '%s résultats', $total, 'mon-articles' ),
            $formatted_total
        );
    }

    private function get_search_icon_svg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" role="presentation" aria-hidden="true"><path d="M10.5 3a7.5 7.5 0 0 1 5.96 12.052l4.246 4.245a1 1 0 0 1-1.414 1.415l-4.246-4.246A7.5 7.5 0 1 1 10.5 3zm0 2a5.5 5.5 0 1 0 3.889 9.389A5.5 5.5 0 0 0 10.5 5Z"/></svg>';
    }

    private function render_inline_styles( $options, $id ) {
        unset( $id );

        $defaults = self::get_default_options();

        $min_card_width = 220;
        if ( isset( $options['min_card_width'] ) ) {
            $min_card_width = max( 1, (int) $options['min_card_width'] );
        }

        $columns_mobile    = max( 1, absint( $options['columns_mobile'] ?? $defaults['columns_mobile'] ) );
        $columns_tablet    = max( 1, absint( $options['columns_tablet'] ?? $defaults['columns_tablet'] ) );
        $columns_desktop   = max( 1, absint( $options['columns_desktop'] ?? $defaults['columns_desktop'] ) );
        $columns_ultrawide = max( 1, absint( $options['columns_ultrawide'] ?? $defaults['columns_ultrawide'] ) );

        $gap_size       = max( 0, absint( $options['gap_size'] ?? $defaults['gap_size'] ) );
        $list_item_gap  = max( 0, absint( $options['list_item_gap'] ?? $defaults['list_item_gap'] ) );
        $padding_top    = max( 0, absint( $options['list_content_padding_top'] ?? $defaults['list_content_padding_top'] ) );
        $padding_right  = max( 0, absint( $options['list_content_padding_right'] ?? $defaults['list_content_padding_right'] ) );
        $padding_bottom = max( 0, absint( $options['list_content_padding_bottom'] ?? $defaults['list_content_padding_bottom'] ) );
        $padding_left   = max( 0, absint( $options['list_content_padding_left'] ?? $defaults['list_content_padding_left'] ) );

        $border_radius      = max( 0, absint( $options['border_radius'] ?? $defaults['border_radius'] ) );
        $title_font_size    = max( 1, absint( $options['title_font_size'] ?? $defaults['title_font_size'] ) );
        $meta_font_size     = max( 1, absint( $options['meta_font_size'] ?? $defaults['meta_font_size'] ) );
        $excerpt_font_size  = max( 1, absint( $options['excerpt_font_size'] ?? $defaults['excerpt_font_size'] ) );
        $module_padding_top    = max( 0, absint( $options['module_padding_top'] ?? $defaults['module_padding_top'] ) );
        $module_padding_right  = max( 0, absint( $options['module_padding_right'] ?? $defaults['module_padding_right'] ) );
        $module_padding_bottom = max( 0, absint( $options['module_padding_bottom'] ?? $defaults['module_padding_bottom'] ) );
        $module_padding_left   = max( 0, absint( $options['module_padding_left'] ?? $defaults['module_padding_left'] ) );

        $title_color         = my_articles_sanitize_color( $options['title_color'] ?? '', $defaults['title_color'] );
        $meta_color          = my_articles_sanitize_color( $options['meta_color'] ?? '', $defaults['meta_color'] );
        $meta_color_hover    = my_articles_sanitize_color( $options['meta_color_hover'] ?? '', $defaults['meta_color_hover'] );
        $excerpt_color       = my_articles_sanitize_color( $options['excerpt_color'] ?? '', $defaults['excerpt_color'] );
        $pagination_color    = my_articles_sanitize_color( $options['pagination_color'] ?? '', $defaults['pagination_color'] );
        $shadow_color        = my_articles_sanitize_color( $options['shadow_color'] ?? '', $defaults['shadow_color'] );
        $shadow_color_hover  = my_articles_sanitize_color( $options['shadow_color_hover'] ?? '', $defaults['shadow_color_hover'] );
        $pinned_border_color = my_articles_sanitize_color( $options['pinned_border_color'] ?? '', $defaults['pinned_border_color'] );
        $pinned_badge_bg     = my_articles_sanitize_color( $options['pinned_badge_bg_color'] ?? '', $defaults['pinned_badge_bg_color'] );
        $pinned_badge_text   = my_articles_sanitize_color( $options['pinned_badge_text_color'] ?? '', $defaults['pinned_badge_text_color'] );
        $module_bg_color     = my_articles_sanitize_color( $options['module_bg_color'] ?? '', $defaults['module_bg_color'] );
        $vignette_bg_color   = my_articles_sanitize_color( $options['vignette_bg_color'] ?? '', $defaults['vignette_bg_color'] );
        $title_wrapper_bg    = my_articles_sanitize_color( $options['title_wrapper_bg_color'] ?? '', $defaults['title_wrapper_bg_color'] );

        $allowed_thumbnail_ratios = self::get_allowed_thumbnail_aspect_ratios();
        $default_thumbnail_ratio  = self::get_default_thumbnail_aspect_ratio();
        $thumbnail_ratio          = isset( $options['thumbnail_aspect_ratio'] ) ? (string) $options['thumbnail_aspect_ratio'] : $default_thumbnail_ratio;

        if ( ! in_array( $thumbnail_ratio, $allowed_thumbnail_ratios, true ) ) {
            $thumbnail_ratio = $default_thumbnail_ratio;
        }

        $custom_properties = array(
            '--my-articles-cols-mobile'            => (string) $columns_mobile,
            '--my-articles-cols-tablet'            => (string) $columns_tablet,
            '--my-articles-cols-desktop'           => (string) $columns_desktop,
            '--my-articles-cols-ultrawide'         => (string) $columns_ultrawide,
            '--my-articles-min-card-width'         => $min_card_width . 'px',
            '--my-articles-gap'                    => $gap_size . 'px',
            '--my-articles-list-gap'               => $list_item_gap . 'px',
            '--my-articles-list-padding-top'       => $padding_top . 'px',
            '--my-articles-list-padding-right'     => $padding_right . 'px',
            '--my-articles-list-padding-bottom'    => $padding_bottom . 'px',
            '--my-articles-list-padding-left'      => $padding_left . 'px',
            '--my-articles-border-radius'          => $border_radius . 'px',
            '--my-articles-title-color'            => $title_color,
            '--my-articles-title-font-size'        => $title_font_size . 'px',
            '--my-articles-meta-color'             => $meta_color,
            '--my-articles-meta-hover-color'       => $meta_color_hover,
            '--my-articles-meta-font-size'         => $meta_font_size . 'px',
            '--my-articles-excerpt-font-size'      => $excerpt_font_size . 'px',
            '--my-articles-excerpt-color'          => $excerpt_color,
            '--my-articles-pagination-color'       => $pagination_color,
            '--my-articles-shadow-color'           => $shadow_color,
            '--my-articles-shadow-color-hover'     => $shadow_color_hover,
            '--my-articles-pinned-border-color'    => $pinned_border_color,
            '--my-articles-badge-bg-color'         => $pinned_badge_bg,
            '--my-articles-badge-text-color'       => $pinned_badge_text,
            '--my-articles-thumbnail-aspect-ratio' => $thumbnail_ratio,
            '--my-articles-module-padding-top'     => $module_padding_top . 'px',
            '--my-articles-module-padding-right'   => $module_padding_right . 'px',
            '--my-articles-module-padding-bottom'  => $module_padding_bottom . 'px',
            '--my-articles-module-padding-left'    => $module_padding_left . 'px',
            '--my-articles-surface-color'          => $module_bg_color,
            '--my-articles-card-surface-color'     => $vignette_bg_color,
            '--my-articles-title-surface-color'    => $title_wrapper_bg,
        );

        $declarations = array();

        foreach ( $custom_properties as $property => $value ) {
            if ( '' === $value && '0' !== $value ) {
                continue;
            }

            $declarations[] = sprintf( '%s: %s;', $property, $value );
        }

        return implode( ' ', $declarations );
    }

    public static function resolve_taxonomy( $options ) {
        $post_type = my_articles_normalize_post_type( $options['post_type'] ?? 'post' );

        if ( ! empty( $options['taxonomy'] ) && taxonomy_exists( $options['taxonomy'] ) && is_object_in_taxonomy( $post_type, $options['taxonomy'] ) ) {
            return $options['taxonomy'];
        }

        if ( 'post' === $post_type && taxonomy_exists( 'category' ) && is_object_in_taxonomy( 'post', 'category' ) ) {
            return 'category';
        }

        return '';
    }
}
