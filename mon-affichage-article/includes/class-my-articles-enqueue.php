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
        wp_register_script( 'my-articles-filter', MY_ARTICLES_PLUGIN_URL . 'assets/js/filter.js', array( 'jquery' ), MY_ARTICLES_VERSION, true );
        wp_register_script( 'my-articles-load-more', MY_ARTICLES_PLUGIN_URL . 'assets/js/load-more.js', array( 'jquery' ), MY_ARTICLES_VERSION, true );
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

        if ( class_exists( 'My_Articles_Shortcode' ) && function_exists( 'wp_add_inline_script' ) && wp_script_is( $editor_handle, 'registered' ) ) {
            if ( function_exists( 'wp_set_script_translations' ) ) {
                wp_set_script_translations( $editor_handle, 'mon-articles', $translations_dir );
            }

            $presets = My_Articles_Shortcode::get_design_presets();
            $export  = array();

            if ( is_array( $presets ) ) {
                foreach ( $presets as $preset_id => $preset ) {
                    if ( ! is_string( $preset_id ) || '' === $preset_id ) {
                        continue;
                    }

                    $label = '';

                    if ( is_array( $preset ) && isset( $preset['label'] ) && is_string( $preset['label'] ) ) {
                        $label = $preset['label'];
                    }

                    if ( '' === $label ) {
                        $label = $preset_id;
                    }

                    $description = '';

                    if ( is_array( $preset ) && isset( $preset['description'] ) && is_string( $preset['description'] ) ) {
                        $description = $preset['description'];
                    }

                    $tags = array();

                    if ( is_array( $preset ) && isset( $preset['tags'] ) && is_array( $preset['tags'] ) ) {
                        foreach ( $preset['tags'] as $tag ) {
                            if ( is_scalar( $tag ) ) {
                                $tags[] = (string) $tag;
                            }
                        }
                    }

                    $values = array();

                    if ( is_array( $preset ) && isset( $preset['values'] ) && is_array( $preset['values'] ) ) {
                        foreach ( $preset['values'] as $key => $value ) {
                            if ( is_string( $key ) && ( is_scalar( $value ) || is_null( $value ) ) ) {
                                $values[ $key ] = $value;
                            }
                        }
                    }

                    $export[ $preset_id ] = array(
                        'label'       => $label,
                        'description' => $description,
                        'locked'      => ! empty( $preset['locked'] ),
                        'tags'        => $tags,
                        'values'      => $values,
                    );
                }
            }

            wp_add_inline_script(
                $editor_handle,
                'window.myArticlesDesignPresets = ' . wp_json_encode( $export ) . ';',
                'before'
            );
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
