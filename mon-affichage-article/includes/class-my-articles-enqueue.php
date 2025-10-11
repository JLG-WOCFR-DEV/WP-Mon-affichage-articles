<?php
// Fichier: includes/class-my-articles-enqueue.php

if ( ! defined( 'WPINC' ) ) {
    die;
}

class My_Articles_Enqueue {

    private static $instance;

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'register_plugin_styles_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'ensure_assets_registered' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
    }

    public function register_plugin_styles_scripts() {
        $vendor_url = MY_ARTICLES_PLUGIN_URL . 'assets/vendor/';

        wp_register_style( 'swiper-css', $vendor_url . 'swiper/swiper-bundle.min.css', array(), '11.0.0' );
        wp_register_style( 'my-articles-styles', MY_ARTICLES_PLUGIN_URL . 'assets/css/styles.css', array(), MY_ARTICLES_VERSION );
        wp_register_script( 'swiper-js', $vendor_url . 'swiper/swiper-bundle.min.js', array(), '11.0.0', true );
        wp_register_script( 'lazysizes', $vendor_url . 'lazysizes/lazysizes.min.js', array(), '5.3.2', true );
        wp_register_script( 'my-articles-responsive-layout', MY_ARTICLES_PLUGIN_URL . 'assets/js/responsive-layout.js', array(), MY_ARTICLES_VERSION, true );
        wp_register_script( 'my-articles-shared-runtime', MY_ARTICLES_PLUGIN_URL . 'assets/js/shared-runtime.js', array(), MY_ARTICLES_VERSION, true );
        wp_register_script( 'my-articles-filter', MY_ARTICLES_PLUGIN_URL . 'assets/js/filter.js', array( 'jquery', 'my-articles-shared-runtime' ), MY_ARTICLES_VERSION, true );
        wp_register_script( 'my-articles-load-more', MY_ARTICLES_PLUGIN_URL . 'assets/js/load-more.js', array( 'jquery', 'my-articles-shared-runtime' ), MY_ARTICLES_VERSION, true );
        wp_register_script( 'my-articles-scroll-fix', MY_ARTICLES_PLUGIN_URL . 'assets/js/scroll-fix.js', array( 'jquery' ), MY_ARTICLES_VERSION, true );
        wp_register_script(
            'my-articles-swiper-init',
            MY_ARTICLES_PLUGIN_URL . 'assets/js/swiper-init.js',
            array( 'swiper-js', 'my-articles-responsive-layout' ),
            MY_ARTICLES_VERSION,
            true
        );
        wp_register_script( 'my-articles-debug-helper', false, array(), MY_ARTICLES_VERSION, true );

        if ( function_exists( 'wp_script_add_data' ) ) {
            wp_script_add_data( 'lazysizes', 'async', true );
        }
    }

    public function ensure_assets_registered() {
        $this->register_plugin_styles_scripts();
    }

    public function enqueue_block_editor_assets() {
        $this->register_plugin_styles_scripts();

        wp_enqueue_style( 'my-articles-styles' );

        wp_enqueue_script( 'my-articles-responsive-layout' );
        wp_enqueue_script( 'my-articles-debug-helper' );

        $editor_handle  = 'mon-affichage-articles-editor-script';
        $preview_handle = 'mon-affichage-articles-preview';

        $translations_dir = MY_ARTICLES_PLUGIN_DIR . 'languages';

        if ( function_exists( 'wp_set_script_translations' ) && wp_script_is( $preview_handle, 'registered' ) ) {
            wp_set_script_translations( $preview_handle, 'mon-articles', $translations_dir );
        }

        $dynamic_assets = $this->get_dynamic_asset_manifest();

        if ( ! empty( $dynamic_assets ) && function_exists( 'wp_add_inline_script' ) ) {
            $manifest_json = wp_json_encode( $dynamic_assets );

            if ( false !== $manifest_json ) {
                $snippet = 'window.myArticlesAssets = window.myArticlesAssets || {}; window.myArticlesAssets.dynamic = window.myArticlesAssets.dynamic || ' . $manifest_json . ';';

                if ( wp_script_is( $preview_handle, 'registered' ) ) {
                    wp_add_inline_script( $preview_handle, $snippet, 'before' );
                }

                if ( wp_script_is( $editor_handle, 'registered' ) ) {
                    wp_add_inline_script( $editor_handle, $snippet, 'before' );
                }
            }
        }

        if ( function_exists( 'wp_add_inline_script' ) && wp_script_is( $editor_handle, 'registered' ) ) {
            if ( function_exists( 'wp_set_script_translations' ) ) {
                wp_set_script_translations( $editor_handle, 'mon-articles', $translations_dir );
            }

            $catalog = array(
                'version'      => '0',
                'generated_at' => gmdate( 'c' ),
                'presets'      => array(),
            );

            if ( class_exists( 'My_Articles_Preset_Registry' ) ) {
                $registry = My_Articles_Preset_Registry::get_instance();
                $catalog  = array(
                    'version'      => $registry->get_version(),
                    'generated_at' => gmdate( 'c' ),
                    'presets'      => $registry->get_presets_for_rest(),
                );
            } elseif ( class_exists( 'My_Articles_Shortcode' ) ) {
                $fallback = My_Articles_Shortcode::get_design_presets();
                if ( is_array( $fallback ) ) {
                    foreach ( $fallback as $preset_id => $definition ) {
                        if ( ! is_string( $preset_id ) || '' === $preset_id ) {
                            continue;
                        }

                        $catalog['presets'][] = array(
                            'id'          => $preset_id,
                            'label'       => isset( $definition['label'] ) ? (string) $definition['label'] : $preset_id,
                            'description' => isset( $definition['description'] ) ? (string) $definition['description'] : '',
                            'locked'      => ! empty( $definition['locked'] ),
                            'tags'        => isset( $definition['tags'] ) && is_array( $definition['tags'] ) ? $definition['tags'] : array(),
                            'values'      => isset( $definition['values'] ) && is_array( $definition['values'] ) ? $definition['values'] : array(),
                            'thumbnail'   => '',
                            'swatch'      => array(),
                        );
                    }
                }
            }

            $inline_map = array();
            foreach ( $catalog['presets'] as $preset ) {
                if ( empty( $preset['id'] ) ) {
                    continue;
                }

                $inline_map[ $preset['id'] ] = array(
                    'label'       => $preset['label'] ?? $preset['id'],
                    'description' => $preset['description'] ?? '',
                    'locked'      => ! empty( $preset['locked'] ),
                    'tags'        => isset( $preset['tags'] ) && is_array( $preset['tags'] ) ? $preset['tags'] : array(),
                    'values'      => isset( $preset['values'] ) && is_array( $preset['values'] ) ? $preset['values'] : array(),
                );
            }

            $catalog_json = wp_json_encode( $catalog );
            $map_json     = wp_json_encode( $inline_map );

            if ( false !== $catalog_json && false !== $map_json ) {
                $script = 'window.myArticlesDesignPresetsCatalog = ' . $catalog_json . '; window.myArticlesDesignPresets = ' . $map_json . ';';

                wp_add_inline_script( $editor_handle, $script, 'before' );
            }

            wp_add_inline_script(
                $editor_handle,
                'window.myArticlesDesignPresets = ' . wp_json_encode( $export ) . ';',
                'before'
            );

            $adapter_definitions = My_Articles_Shortcode::get_content_adapter_definitions_for_admin();
            $encoded_adapters    = wp_json_encode( $adapter_definitions );

            if ( false !== $encoded_adapters ) {
                wp_add_inline_script(
                    $editor_handle,
                    'window.myArticlesContentAdapters = ' . $encoded_adapters . ';',
                    'before'
                );
            }
        }
    }

    private function get_dynamic_asset_manifest() {
        if ( ! defined( 'MY_ARTICLES_PLUGIN_URL' ) ) {
            return array();
        }

        $base_url = MY_ARTICLES_PLUGIN_URL;

        return array(
            'swiper'    => array(
                'styles'  => array(
                    array(
                        'handle' => 'swiper-css',
                        'src'    => $base_url . 'assets/vendor/swiper/swiper-bundle.min.css',
                        'ver'    => '11.0.0',
                    ),
                ),
                'scripts' => array(
                    array(
                        'handle' => 'swiper-js',
                        'src'    => $base_url . 'assets/vendor/swiper/swiper-bundle.min.js',
                        'ver'    => '11.0.0',
                    ),
                    array(
                        'handle' => 'my-articles-swiper-init',
                        'src'    => $base_url . 'assets/js/swiper-init.js',
                        'ver'    => defined( 'MY_ARTICLES_VERSION' ) ? MY_ARTICLES_VERSION : '1.0.0',
                    ),
                ),
            ),
            'lazysizes' => array(
                'scripts' => array(
                    array(
                        'handle'     => 'lazysizes',
                        'src'        => $base_url . 'assets/vendor/lazysizes/lazysizes.min.js',
                        'ver'        => '5.3.2',
                        'attributes' => array(
                            'async' => true,
                        ),
                    ),
                ),
            ),
        );
    }

    public function register_script_data( $handle, $object_name, array $data ) {
        if ( ! class_exists( 'My_Articles_Frontend_Data' ) ) {
            return false;
        }

        return My_Articles_Frontend_Data::get_instance()->register( $handle, $object_name, $data );
    }
}
