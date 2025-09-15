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
        wp_register_style('swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css');
        wp_register_script('swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], null, true);
    }
}
