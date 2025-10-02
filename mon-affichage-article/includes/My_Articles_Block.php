<?php
// Fichier: includes/class-my-articles-block.php

namespace LCV\MonAffichage;

if ( ! defined( 'WPINC' ) ) {
    die;
}

class My_Articles_Block {

    private static $instance;

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'register_block' ) );
    }

    public function register_block() {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        register_block_type(
            __DIR__ . '/../blocks/mon-affichage-articles',
            array(
                'render_callback' => array( $this, 'render_block' ),
            )
        );
    }

    public function render_block( $attributes = array(), $content = '' ) {
        if ( ! class_exists( My_Articles_Shortcode::class ) ) {
            return '';
        }

        $attributes  = is_array( $attributes ) ? $attributes : array();
        $instance_id = isset( $attributes['instanceId'] ) ? absint( $attributes['instanceId'] ) : 0;

        if ( $instance_id <= 0 ) {
            return '';
        }

        $overrides = $this->prepare_overrides( $attributes );

        $shortcode_instance = My_Articles_Shortcode::get_instance();

        return $shortcode_instance->render_shortcode(
            array(
                'id'        => $instance_id,
                'overrides' => $overrides,
            )
        );
    }

    private function prepare_overrides( array $attributes ) {
        $defaults  = My_Articles_Shortcode::get_default_options();
        $overrides = array();

        foreach ( $defaults as $key => $default_value ) {
            if ( ! array_key_exists( $key, $attributes ) ) {
                continue;
            }

            $raw_value = $attributes[ $key ];

            if ( is_array( $default_value ) ) {
                if ( is_array( $raw_value ) ) {
                    $overrides[ $key ] = $raw_value;
                }
                continue;
            }

            if ( is_int( $default_value ) ) {
                if ( is_bool( $raw_value ) ) {
                    $overrides[ $key ] = $raw_value ? 1 : 0;
                } else {
                    $overrides[ $key ] = (int) $raw_value;
                }
                continue;
            }

            if ( is_float( $default_value ) ) {
                $overrides[ $key ] = (float) $raw_value;
                continue;
            }

            $overrides[ $key ] = (string) $raw_value;
        }

        return $overrides;
    }
}
