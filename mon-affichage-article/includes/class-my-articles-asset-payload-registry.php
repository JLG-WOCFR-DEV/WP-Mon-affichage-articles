<?php
// Fichier: includes/class-my-articles-asset-payload-registry.php

if ( ! defined( 'WPINC' ) ) {
    die;
}

class My_Articles_Asset_Payload_Registry {

    private static $instance;

    /**
     * @var array<string, array<string, array>>
     */
    private $payloads = array();

    private function __construct() {}

    /**
     * Retrieve the singleton instance.
     *
     * @return My_Articles_Asset_Payload_Registry
     */
    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Registers a payload for the provided asset handle and JavaScript object.
     *
     * Payloads registered multiple times are deeply merged to avoid duplicated
     * export blocks when several shortcode instances share the same script
     * handle.
     *
     * @param string $handle      Script handle.
     * @param string $object_name JavaScript object name.
     * @param array  $data        Payload to expose in JavaScript.
     *
     * @return array The aggregated payload currently stored for the pair.
     */
    public function register( $handle, $object_name, array $data ) {
        $handle = is_string( $handle ) ? trim( $handle ) : '';
        $object_name = is_string( $object_name ) ? trim( $object_name ) : '';

        if ( '' === $handle || '' === $object_name || empty( $data ) ) {
            return array();
        }

        $sanitized_object_name = preg_replace( '/[^A-Za-z0-9_]/', '_', $object_name );

        if ( '' === $sanitized_object_name ) {
            return array();
        }

        if ( ! isset( $this->payloads[ $handle ] ) ) {
            $this->payloads[ $handle ] = array();
        }

        if ( isset( $this->payloads[ $handle ][ $sanitized_object_name ] ) ) {
            $this->payloads[ $handle ][ $sanitized_object_name ] = $this->deep_merge(
                $this->payloads[ $handle ][ $sanitized_object_name ],
                $data
            );
        } else {
            $this->payloads[ $handle ][ $sanitized_object_name ] = $data;
        }

        return $this->payloads[ $handle ][ $sanitized_object_name ];
    }

    /**
     * Dispatches all stored payloads to the frontend data registry.
     */
    public function dispatch_all() {
        if ( ! class_exists( 'My_Articles_Frontend_Data' ) ) {
            return;
        }

        $frontend = My_Articles_Frontend_Data::get_instance();

        foreach ( $this->payloads as $handle => $objects ) {
            foreach ( $objects as $object_name => $payload ) {
                $frontend->register( $handle, $object_name, $payload );
            }
        }
    }

    /**
     * Dispatches the payload for a specific handle/object pair.
     *
     * @param string $handle      Script handle.
     * @param string $object_name JavaScript object name.
     */
    public function dispatch( $handle, $object_name ) {
        if ( '' === $handle || '' === $object_name ) {
            return;
        }

        if ( ! class_exists( 'My_Articles_Frontend_Data' ) ) {
            return;
        }

        $sanitized_object_name = preg_replace( '/[^A-Za-z0-9_]/', '_', (string) $object_name );

        if ( '' === $sanitized_object_name ) {
            return;
        }

        if ( empty( $this->payloads[ $handle ][ $sanitized_object_name ] ) ) {
            return;
        }

        My_Articles_Frontend_Data::get_instance()->register(
            $handle,
            $sanitized_object_name,
            $this->payloads[ $handle ][ $sanitized_object_name ]
        );
    }

    /**
     * Retrieves the aggregated payload for a handle/object pair.
     *
     * @param string $handle      Script handle.
     * @param string $object_name JavaScript object name.
     *
     * @return array
     */
    public function get_payload( $handle, $object_name ) {
        $handle = is_string( $handle ) ? trim( $handle ) : '';
        $object_name = is_string( $object_name ) ? trim( $object_name ) : '';

        if ( '' === $handle || '' === $object_name ) {
            return array();
        }

        $sanitized_object_name = preg_replace( '/[^A-Za-z0-9_]/', '_', $object_name );

        if ( '' === $sanitized_object_name ) {
            return array();
        }

        if ( isset( $this->payloads[ $handle ][ $sanitized_object_name ] ) ) {
            return $this->payloads[ $handle ][ $sanitized_object_name ];
        }

        return array();
    }

    /**
     * Resets the registry. Intended for test environments.
     */
    public function reset() {
        $this->payloads = array();
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
