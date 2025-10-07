<?php
// Fichier: includes/class-my-articles-frontend-data.php

if ( ! defined( 'WPINC' ) ) {
    die;
}

class My_Articles_Frontend_Data {

    private static $instance;

    private $data = array();

    private $handles_to_output = array();

    private $printed_handles = array();

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_print_scripts', array( $this, 'maybe_output_registered_data' ), 1 );
        add_action( 'wp_print_footer_scripts', array( $this, 'maybe_output_registered_data' ), 1 );
    }

    public function register( $handle, $object_name, array $data ) {
        if ( empty( $handle ) || empty( $object_name ) || empty( $data ) ) {
            return false;
        }

        $sanitized_object_name = preg_replace( '/[^A-Za-z0-9_]/', '_', (string) $object_name );
        if ( '' === $sanitized_object_name ) {
            return false;
        }

        if ( ! isset( $this->data[ $handle ] ) ) {
            $this->data[ $handle ] = array();
        }

        if ( isset( $this->data[ $handle ][ $sanitized_object_name ] ) ) {
            $this->data[ $handle ][ $sanitized_object_name ] = $this->deep_merge(
                $this->data[ $handle ][ $sanitized_object_name ],
                $data
            );
        } else {
            $this->data[ $handle ][ $sanitized_object_name ] = $data;
        }

        $this->handles_to_output[ $handle ] = true;

        if ( did_action( 'wp_print_scripts' ) && ! isset( $this->printed_handles[ $handle ] ) ) {
            $this->maybe_output_handle( $handle );
        }

        return true;
    }

    public function maybe_output_registered_data() {
        if ( empty( $this->handles_to_output ) ) {
            return;
        }

        foreach ( array_keys( $this->handles_to_output ) as $handle ) {
            $this->maybe_output_handle( $handle );
        }
    }

    private function maybe_output_handle( $handle ) {
        if ( isset( $this->printed_handles[ $handle ] ) ) {
            return;
        }

        if ( ! isset( $this->data[ $handle ] ) || empty( $this->data[ $handle ] ) ) {
            $this->printed_handles[ $handle ] = true;
            return;
        }

        if ( ! wp_script_is( $handle, 'enqueued' ) ) {
            return;
        }

        foreach ( $this->data[ $handle ] as $object_name => $payload ) {
            $encoded = wp_json_encode( $payload );

            if ( false === $encoded ) {
                continue;
            }

            $inline = sprintf(
                'window.%1$s = window.%1$s || {}; window.%1$s = Object.assign({}, window.%1$s, %2$s);',
                $object_name,
                $encoded
            );

            wp_add_inline_script( $handle, $inline, 'before' );
        }

        $this->printed_handles[ $handle ] = true;
    }

    private function deep_merge( array $original, array $replacement ) {
        foreach ( $replacement as $key => $value ) {
            if ( is_array( $value ) && isset( $original[ $key ] ) && is_array( $original[ $key ] ) ) {
                $original[ $key ] = $this->deep_merge( $original[ $key ], $value );
                continue;
            }

            $original[ $key ] = $value;
        }

        return $original;
    }
}
