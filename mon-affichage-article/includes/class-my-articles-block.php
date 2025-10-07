<?php
// Fichier: includes/class-my-articles-block.php

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
        if ( ! class_exists( 'My_Articles_Shortcode' ) ) {
            return '';
        }

        $attributes  = is_array( $attributes ) ? $attributes : array();
        $instance_id = isset( $attributes['instanceId'] ) ? absint( $attributes['instanceId'] ) : 0;

        if ( $instance_id <= 0 ) {
            return '';
        }

        $overrides = self::prepare_overrides_from_attributes( $attributes );

        $shortcode_instance = My_Articles_Shortcode::get_instance();

        return $shortcode_instance->render_shortcode(
            array(
                'id'        => $instance_id,
                'overrides' => $overrides,
            )
        );
    }

    public static function prepare_overrides_from_attributes( array $attributes ) {
        $defaults = My_Articles_Shortcode::get_default_options();
        $schema   = self::get_override_schema();
        $overrides = array();

        $allowed_keys = array_fill_keys( array_keys( $defaults ), true );

        foreach ( array_keys( $schema ) as $schema_key ) {
            $allowed_keys[ $schema_key ] = true;
        }

        $filtered = array_intersect_key( $attributes, $allowed_keys );

        foreach ( $filtered as $key => $raw_value ) {
            if ( null === $raw_value ) {
                continue;
            }

            $definition = isset( $schema[ $key ] ) && is_array( $schema[ $key ] ) ? $schema[ $key ] : array();
            $default_value = array_key_exists( $key, $defaults )
                ? $defaults[ $key ]
                : ( isset( $definition['default'] ) ? $definition['default'] : null );
            $normalized    = self::normalize_override_value( $raw_value, $default_value, $definition );

            if ( null === $normalized ) {
                continue;
            }

            $overrides[ $key ] = $normalized;
        }

        return $overrides;
    }

    /**
     * Returns the normalization schema used for block attribute overrides.
     *
     * The schema maps attribute keys to a definition describing how the value
     * should be normalized. Custom definitions can be injected via the
     * `my_articles_block_override_schema` filter.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_override_schema() {
        $defaults = My_Articles_Shortcode::get_default_options();

        $schema = array(
            'slideshow_loop'                  => array( 'type' => 'bool' ),
            'slideshow_autoplay'              => array( 'type' => 'bool' ),
            'slideshow_pause_on_interaction'  => array( 'type' => 'bool' ),
            'slideshow_pause_on_mouse_enter'  => array( 'type' => 'bool' ),
            'slideshow_show_navigation'       => array( 'type' => 'bool' ),
            'slideshow_show_pagination'       => array( 'type' => 'bool' ),
            'load_more_auto'                  => array( 'type' => 'bool' ),
            'slideshow_delay'                 => array(
                'type'                         => 'int',
                'min'                          => 0,
                'max'                          => 20000,
                'min_if_positive'              => 1000,
                'fallback_to_default_if_zero'  => true,
            ),
        );

        $schema = apply_filters( 'my_articles_block_override_schema', $schema, $defaults );

        if ( ! is_array( $schema ) ) {
            return array();
        }

        return $schema;
    }

    /**
     * Normalizes a single override value according to the provided definition.
     *
     * @param mixed  $raw_value     Raw value coming from block attributes.
     * @param mixed  $default_value Default shortcode option value.
     * @param array  $definition    Schema definition for the override.
     *
     * @return mixed|null Normalized value or null when it should be skipped.
     */
    private static function normalize_override_value( $raw_value, $default_value, array $definition ) {
        $type = isset( $definition['type'] ) ? $definition['type'] : self::infer_override_type( $default_value );

        switch ( $type ) {
            case 'bool':
                return ! empty( $raw_value ) ? 1 : 0;

            case 'int':
                if ( is_bool( $raw_value ) ) {
                    $value = $raw_value ? 1 : 0;
                } else {
                    $value = (int) $raw_value;
                }

                if ( isset( $definition['min'] ) ) {
                    $value = max( (int) $definition['min'], $value );
                }

                if ( isset( $definition['max'] ) ) {
                    $value = min( (int) $definition['max'], $value );
                }

                if ( isset( $definition['min_if_positive'] ) && $value > 0 ) {
                    $value = max( $value, (int) $definition['min_if_positive'] );
                }

                if ( ! empty( $definition['fallback_to_default_if_zero'] ) && 0 === $value && is_numeric( $default_value ) ) {
                    $value = (int) $default_value;
                }

                return $value;

            case 'float':
                return (float) $raw_value;

            case 'array':
                if ( is_array( $raw_value ) ) {
                    return $raw_value;
                }

                return null;

            case 'string':
            default:
                return (string) $raw_value;
        }
    }

    /**
     * Infers the normalization type from the default shortcode value.
     *
     * @param mixed $default_value Default shortcode option value.
     *
     * @return string One of: bool, int, float, array, string.
     */
    private static function infer_override_type( $default_value ) {
        if ( is_bool( $default_value ) ) {
            return 'bool';
        }

        if ( is_int( $default_value ) ) {
            return 'int';
        }

        if ( is_float( $default_value ) ) {
            return 'float';
        }

        if ( is_array( $default_value ) ) {
            return 'array';
        }

        return 'string';
    }
}
