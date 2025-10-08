<?php
if ( ! defined( 'WPINC' ) ) {
    die;
}

class My_Articles_Response_Cache_Key {
    private $namespace;
    private $base_context = array();
    private $fragments = array();

    public function __construct( $namespace, array $base_context = array() ) {
        $this->namespace    = $this->sanitize_namespace( $namespace );
        $this->base_context = $this->normalize_base_context( $base_context );
    }

    public function add_fragment( $name, $value ) {
        $name = is_string( $name ) ? $name : (string) $name;

        if ( '' === $name ) {
            return $this;
        }

        $normalized_value = $this->normalize_fragment_value( $value );

        if ( null === $normalized_value ) {
            return $this;
        }

        $this->fragments[ $name ] = $normalized_value;

        return $this;
    }

    public function add_fragments( array $fragments ) {
        foreach ( $fragments as $name => $value ) {
            $this->add_fragment( $name, $value );
        }

        return $this;
    }

    public function to_string() {
        $payload = $this->base_context;

        if ( ! empty( $this->fragments ) ) {
            ksort( $this->fragments );
            $payload['fragments'] = $this->fragments;
        }

        $encoded = $this->encode_payload( $payload );
        $hash    = md5( $encoded );

        return sprintf( 'my_articles_%s_%s', $this->namespace, $hash );
    }

    public function get_fragments() {
        return $this->fragments;
    }

    private function normalize_base_context( array $base_context ) {
        $normalized = array(
            'instance' => isset( $base_context['instance'] ) ? absint( $base_context['instance'] ) : 0,
            'category' => isset( $base_context['category'] ) ? (string) $base_context['category'] : '',
            'paged'    => isset( $base_context['paged'] ) ? absint( $base_context['paged'] ) : 0,
            'mode'     => isset( $base_context['mode'] ) ? (string) $base_context['mode'] : '',
        );

        if ( isset( $base_context['extra'] ) ) {
            $normalized['extra'] = $this->normalize_fragment_value( $base_context['extra'] );
        }

        return $normalized;
    }

    private function normalize_fragment_value( $value ) {
        if ( null === $value ) {
            return null;
        }

        if ( is_bool( $value ) ) {
            return $value ? '1' : '0';
        }

        if ( is_numeric( $value ) ) {
            return (string) $value;
        }

        if ( is_string( $value ) ) {
            $value = trim( $value );

            return '' === $value ? null : $value;
        }

        if ( is_array( $value ) ) {
            $normalized = array();

            foreach ( $value as $item ) {
                $normalized_item = $this->normalize_fragment_value( $item );

                if ( null === $normalized_item ) {
                    continue;
                }

                $normalized[] = $normalized_item;
            }

            if ( empty( $normalized ) ) {
                return null;
            }

            return array_values( $normalized );
        }

        if ( is_object( $value ) && method_exists( $value, '__toString' ) ) {
            return $this->normalize_fragment_value( (string) $value );
        }

        return null;
    }

    private function sanitize_namespace( $namespace ) {
        $namespace = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $namespace );

        if ( '' === $namespace ) {
            $namespace = 'default';
        }

        return strtolower( $namespace );
    }

    private function encode_payload( array $payload ) {
        $encoder = function_exists( 'wp_json_encode' ) ? 'wp_json_encode' : 'json_encode';
        $encoded = call_user_func( $encoder, $payload );

        if ( false === $encoded || null === $encoded ) {
            $encoded = serialize( $payload );
        }

        return (string) $encoded;
    }
}
