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
        add_action( 'wp_enqueue_scripts', array( $this, 'register_plugin_styles_scripts' ) );
    }

    public function register_plugin_styles_scripts() {
        $vendor_url = MY_ARTICLES_PLUGIN_URL . 'assets/vendor/';

        wp_register_style( 'swiper-css', $vendor_url . 'swiper/swiper-bundle.min.css', array(), '11.0.0' );
        wp_register_style( 'my-articles-styles', MY_ARTICLES_PLUGIN_URL . 'assets/css/styles.css', array(), MY_ARTICLES_VERSION );
        wp_register_script( 'swiper-js', $vendor_url . 'swiper/swiper-bundle.min.js', array(), '11.0.0', true );
        wp_register_script( 'lazysizes', $vendor_url . 'lazysizes/lazysizes.min.js', array(), '5.3.2', true );
        wp_register_script( 'my-articles-responsive-layout', MY_ARTICLES_PLUGIN_URL . 'assets/js/responsive-layout.js', array(), MY_ARTICLES_VERSION, true );

        if ( function_exists( 'wp_script_add_data' ) ) {
            wp_script_add_data( 'lazysizes', 'async', true );
        }
    }
}
