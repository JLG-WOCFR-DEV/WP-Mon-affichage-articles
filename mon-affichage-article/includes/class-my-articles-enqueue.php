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

        wp_enqueue_style( 'swiper-css' );
        wp_enqueue_style( 'my-articles-styles' );

        wp_enqueue_script( 'swiper-js' );
        wp_enqueue_script( 'lazysizes' );
        wp_enqueue_script( 'my-articles-responsive-layout' );
        wp_enqueue_script( 'my-articles-debug-helper' );

        $editor_handle  = 'mon-affichage-articles-editor-script';
        $preview_handle = 'mon-affichage-articles-preview';

        $translations_dir = MY_ARTICLES_PLUGIN_DIR . 'languages';

        if ( function_exists( 'wp_set_script_translations' ) && wp_script_is( $preview_handle, 'registered' ) ) {
            wp_set_script_translations( $preview_handle, 'mon-articles', $translations_dir );
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
}
