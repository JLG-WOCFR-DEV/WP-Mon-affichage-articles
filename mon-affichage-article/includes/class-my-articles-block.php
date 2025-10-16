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
        $attributes = is_array( $attributes ) ? $attributes : array();

        if ( $this->should_render_preview() ) {
            return $this->render_editor_preview( $attributes );
        }

        if ( ! class_exists( 'My_Articles_Shortcode' ) ) {
            return '';
        }

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

    private function should_render_preview() {
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return true;
        }

        if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
            return true;
        }

        if ( function_exists( 'is_admin' ) && is_admin() ) {
            return true;
        }

        return false;
    }

    private function render_editor_preview( array $attributes ) {
        if ( ! class_exists( 'My_Articles_Block_Preview_Adapter' ) ) {
            return '';
        }

        $adapter = new My_Articles_Block_Preview_Adapter();
        $data    = $adapter->get_items(
            array(),
            array(),
            array(
                'attributes' => $attributes,
            )
        );

        if ( ! is_array( $data ) ) {
            $data = array();
        }

        $items = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
        $cta   = isset( $data['cta'] ) && is_array( $data['cta'] ) ? $data['cta'] : array();

        $output  = '<div class="my-articles-block-preview" aria-hidden="true">';

        foreach ( $items as $item ) {
            $output .= $this->render_preview_item( is_array( $item ) ? $item : array() );
        }

        $cta_label = isset( $cta['label'] ) ? $this->sanitize_preview_text( $cta['label'] ) : '';

        if ( '' !== $cta_label ) {
            $output .= '<div class="my-articles-block-preview__footer"><span class="my-articles-block-preview__cta">' . esc_html( $cta_label ) . '</span></div>';
        }

        $output .= '</div>';

        return $output;
    }

    private function render_preview_item( array $item ) {
        $title    = $this->sanitize_preview_text( isset( $item['title'] ) ? $item['title'] : '' );
        $excerpt  = $this->sanitize_preview_text( isset( $item['excerpt'] ) ? $item['excerpt'] : '' );
        $category = $this->sanitize_preview_text( isset( $item['category'] ) ? $item['category'] : '' );
        $date     = $this->sanitize_preview_text( isset( $item['date'] ) ? $item['date'] : '' );

        $meta_parts = array();

        if ( '' !== $category ) {
            $meta_parts[] = '<span class="my-articles-block-preview__category">' . esc_html( $category ) . '</span>';
        }

        if ( '' !== $date ) {
            $meta_parts[] = '<span class="my-articles-block-preview__date">' . esc_html( $date ) . '</span>';
        }

        $meta_html = '';

        if ( ! empty( $meta_parts ) ) {
            $meta_html = implode( '<span class="my-articles-block-preview__separator">•</span>', $meta_parts );
        }

        $output  = '<article class="my-articles-block-preview__item">';
        $output .= '<div class="my-articles-block-preview__media" aria-hidden="true"></div>';
        $output .= '<div class="my-articles-block-preview__content">';

        if ( '' !== $meta_html ) {
            $output .= '<p class="my-articles-block-preview__meta">' . $meta_html . '</p>';
        }

        $output .= '<h3 class="my-articles-block-preview__title">' . esc_html( $title ) . '</h3>';

        if ( '' !== $excerpt ) {
            $output .= '<p class="my-articles-block-preview__excerpt">' . esc_html( $excerpt ) . '</p>';
        }

        $output .= '</div>';
        $output .= '</article>';

        return $output;
    }

    private function sanitize_preview_text( $value ) {
        if ( is_scalar( $value ) || ( is_object( $value ) && method_exists( $value, '__toString' ) ) ) {
            $value = (string) $value;
        } else {
            $value = '';
        }

        return trim( wp_strip_all_tags( $value ) );
    }

    public static function prepare_overrides_from_attributes( array $attributes ) {
        $defaults  = My_Articles_Shortcode::get_default_options();
        $overrides = array();
        $filtered  = array_intersect_key( $attributes, $defaults );
        $schema    = self::get_override_schema();

        foreach ( $filtered as $key => $raw_value ) {
            if ( null === $raw_value ) {
                continue;
            }

            $definition    = isset( $schema[ $key ] ) && is_array( $schema[ $key ] ) ? $schema[ $key ] : array();
            $default_value = $defaults[ $key ];
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
            'slideshow_respect_reduced_motion' => array( 'type' => 'bool' ),
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
