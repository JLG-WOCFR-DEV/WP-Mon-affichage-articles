<?php
/**
 * REST controller for My Articles responses.
 *
 * @package Mon_Affichage_Articles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class My_Articles_Controller extends WP_REST_Controller {
    /**
     * Main plugin instance.
     *
     * @var Mon_Affichage_Articles
     */
    protected $plugin;

    /**
     * My_Articles_Controller constructor.
     *
     * @param Mon_Affichage_Articles $plugin Plugin instance.
     */
    public function __construct( Mon_Affichage_Articles $plugin ) {
        $this->namespace = 'my-articles/v1';
        $this->plugin    = $plugin;
    }

    /**
     * Registers REST API routes.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/filter',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'filter_articles' ),
                    'permission_callback' => '__return_true',
                    'args'                => $this->get_filter_args(),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/load-more',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'load_more_articles' ),
                    'permission_callback' => '__return_true',
                    'args'                => $this->get_load_more_args(),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/search',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'search_posts' ),
                    'permission_callback' => '__return_true',
                    'args'                => $this->get_search_args(),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/nonce',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_rest_nonce' ),
                    'permission_callback' => '__return_true',
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/render-preview',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'render_preview' ),
                    'permission_callback' => array( $this, 'preview_permission_check' ),
                    'args'                => $this->get_render_preview_args(),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/track',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'track_interaction' ),
                    'permission_callback' => '__return_true',
                    'args'                => $this->get_track_args(),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/presets',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_design_presets' ),
                    'permission_callback' => '__return_true',
                ),
            )
        );
    }

    /**
     * Argument definitions for the filter endpoint.
     *
     * @return array
     */
    protected function get_filter_args() {
        return array(
            'instance_id' => array(
                'type'              => 'integer',
                'required'          => true,
                'sanitize_callback' => 'absint',
            ),
            'category'    => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => array( $this, 'sanitize_title_arg' ),
            ),
            'filters'     => array(
                'type'              => 'array',
                'required'          => false,
                'sanitize_callback' => array( $this, 'sanitize_filters_arg' ),
            ),
            'current_url' => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => array( $this, 'sanitize_text_arg' ),
            ),
            'search'      => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => array( $this, 'sanitize_text_arg' ),
            ),
            'sort'        => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => array( $this, 'sanitize_key_arg' ),
            ),
        );
    }

    /**
     * Argument definitions for the load more endpoint.
     *
     * @return array
     */
    protected function get_load_more_args() {
        return array(
            'instance_id' => array(
                'type'              => 'integer',
                'required'          => true,
                'sanitize_callback' => 'absint',
            ),
            'paged'       => array(
                'type'              => 'integer',
                'required'          => false,
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ),
            'pinned_ids'  => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => array( $this, 'sanitize_text_arg' ),
            ),
            'category'    => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => array( $this, 'sanitize_title_arg' ),
            ),
            'filters'     => array(
                'type'              => 'array',
                'required'          => false,
                'sanitize_callback' => array( $this, 'sanitize_filters_arg' ),
            ),
            'search'      => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => array( $this, 'sanitize_text_arg' ),
            ),
            'sort'        => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => array( $this, 'sanitize_key_arg' ),
            ),
        );
    }

    /**
     * Argument definitions for the search endpoint.
     *
     * @return array
     */
    protected function get_search_args() {
        return array(
            'search' => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => array( $this, 'sanitize_text_arg' ),
            ),
            'post_type' => array(
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => array( $this, 'sanitize_key_arg' ),
            ),
        );
    }

    public function get_design_presets( WP_REST_Request $request ) {
        $response = array(
            'version' => '0',
            'presets' => array(),
        );

        if ( class_exists( 'My_Articles_Preset_Registry' ) ) {
            $registry          = My_Articles_Preset_Registry::get_instance();
            $response['version'] = $registry->get_version();
            $response['presets'] = $registry->get_presets_for_rest();
        } elseif ( class_exists( 'My_Articles_Shortcode' ) ) {
            $presets = My_Articles_Shortcode::get_design_presets();

            if ( is_array( $presets ) ) {
                foreach ( $presets as $id => $definition ) {
                    if ( ! is_string( $id ) || '' === $id ) {
                        continue;
                    }

                    $response['presets'][] = array(
                        'id'          => $id,
                        'label'       => isset( $definition['label'] ) ? (string) $definition['label'] : $id,
                        'description' => isset( $definition['description'] ) ? (string) $definition['description'] : '',
                        'locked'      => ! empty( $definition['locked'] ),
                        'tags'        => isset( $definition['tags'] ) && is_array( $definition['tags'] ) ? $definition['tags'] : array(),
                        'values'      => isset( $definition['values'] ) && is_array( $definition['values'] ) ? $definition['values'] : array(),
                        'thumbnail'   => '',
                        'swatch'      => array(),
                    );
                }
            }
        }

        return rest_ensure_response( $response );
    }

    /**
     * Argument definitions for the tracking endpoint.
     *
     * @return array
     */
    protected function get_track_args() {
        return array(
            'event'  => array(
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => array( $this, 'sanitize_text_arg' ),
            ),
            'detail' => array(
                'type'     => 'object',
                'required' => false,
            ),
        );
    }

    /**
     * Validates the REST nonce sent with the request headers.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return true|WP_Error
     */
    protected function validate_request_nonce( WP_REST_Request $request ) {
        $raw_nonce = $request->get_header( 'X-WP-Nonce' );
        $nonce     = is_string( $raw_nonce ) ? sanitize_text_field( wp_unslash( $raw_nonce ) ) : '';

        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error(
                'my_articles_invalid_nonce',
                __( 'Nonce invalide.', 'mon-articles' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Validates request origin information to ensure it targets the current site.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return true|WP_Error
     */
    protected function validate_request_origin( WP_REST_Request $request ) {
        $home_url = home_url();

        $raw_origin  = $request->get_header( 'origin' );
        $raw_referer = $request->get_header( 'referer' );

        $normalized_origin  = is_string( $raw_origin ) ? my_articles_normalize_internal_url( $raw_origin, $home_url ) : '';
        $normalized_referer = is_string( $raw_referer ) ? my_articles_normalize_internal_url( $raw_referer, $home_url ) : '';

        if ( '' === $normalized_origin && '' === $normalized_referer ) {
            return new WP_Error(
                'my_articles_invalid_request_origin',
                __( 'Origine de la requête invalide.', 'mon-articles' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Handles filtering articles requests.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function filter_articles( WP_REST_Request $request ) {
        $nonce_validation = $this->validate_request_nonce( $request );

        if ( is_wp_error( $nonce_validation ) ) {
            return $nonce_validation;
        }

        $response = $this->plugin->prepare_filter_articles_response(
            array(
                'instance_id'  => $request->get_param( 'instance_id' ),
                'category'     => $request->get_param( 'category' ),
                'filters'      => $request->get_param( 'filters' ),
                'current_url'  => $request->get_param( 'current_url' ),
                'http_referer' => $request->get_header( 'referer' ),
                'search'       => $request->get_param( 'search' ),
                'sort'         => $request->get_param( 'sort' ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return rest_ensure_response( $response );
    }

    /**
     * Handles load more articles requests.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function load_more_articles( WP_REST_Request $request ) {
        $nonce_validation = $this->validate_request_nonce( $request );

        if ( is_wp_error( $nonce_validation ) ) {
            return $nonce_validation;
        }

        $response = $this->plugin->prepare_load_more_articles_response(
            array(
                'instance_id' => $request->get_param( 'instance_id' ),
                'paged'       => $request->get_param( 'paged' ),
                'pinned_ids'  => $request->get_param( 'pinned_ids' ),
                'category'    => $request->get_param( 'category' ),
                'filters'     => $request->get_param( 'filters' ),
                'search'      => $request->get_param( 'search' ),
                'sort'        => $request->get_param( 'sort' ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return rest_ensure_response( $response );
    }

    /**
     * Handles search posts requests.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function search_posts( WP_REST_Request $request ) {
        $nonce_validation = $this->validate_request_nonce( $request );

        if ( is_wp_error( $nonce_validation ) ) {
            return $nonce_validation;
        }

        $response = $this->plugin->prepare_search_posts_response(
            array(
                'search'    => $request->get_param( 'search' ),
                'post_type' => $request->get_param( 'post_type' ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return rest_ensure_response( $response );
    }

    /**
     * Provides a refreshed REST API nonce for front-end interactions.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function get_rest_nonce( WP_REST_Request $request ) {
        $nonce_validation = $this->validate_request_nonce( $request );

        if ( is_wp_error( $nonce_validation ) ) {
            return $nonce_validation;
        }

        $origin_validation = $this->validate_request_origin( $request );

        if ( is_wp_error( $origin_validation ) ) {
            return $origin_validation;
        }

        return rest_ensure_response(
            array(
                'success' => true,
                'data'    => array(
                    'nonce' => wp_create_nonce( 'wp_rest' ),
                ),
            )
        );
    }

    /**
     * Handles instrumentation events sent from the front-end.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function track_interaction( WP_REST_Request $request ) {
        $nonce_validation = $this->validate_request_nonce( $request );

        if ( is_wp_error( $nonce_validation ) ) {
            return $nonce_validation;
        }

        if ( ! my_articles_is_instrumentation_enabled() ) {
            return new WP_Error(
                'my_articles_tracking_disabled',
                __( 'L\'instrumentation est désactivée.', 'mon-articles' ),
                array( 'status' => 403 )
            );
        }

        $event_name = (string) $request->get_param( 'event' );
        $event_name = sanitize_text_field( $event_name );

        if ( '' === $event_name ) {
            return new WP_Error(
                'my_articles_invalid_tracking_event',
                __( 'Événement de suivi invalide.', 'mon-articles' ),
                array( 'status' => 400 )
            );
        }

        $detail = $request->get_param( 'detail' );

        if ( is_null( $detail ) ) {
            $detail = array();
        } elseif ( ! is_array( $detail ) ) {
            $detail = (array) $detail;
        }

        /**
         * Allow developers to react to front-end tracking events.
         *
         * @param string           $event_name Event identifier.
         * @param array            $detail     Event payload.
         * @param WP_REST_Request  $request    Original REST request.
         */
        do_action( 'my_articles_track_interaction', $event_name, $detail, $request );

        return rest_ensure_response(
            array(
                'success' => true,
            )
        );
    }

    /**
     * Permission check for preview rendering requests.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return true|WP_Error
     */
    public function preview_permission_check( WP_REST_Request $request ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error(
                'my_articles_preview_forbidden',
                __( 'Vous n’avez pas les droits nécessaires pour prévisualiser ce module.', 'mon-articles' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }

        return true;
    }

    /**
     * Argument definitions for the preview endpoint.
     *
     * @return array
     */
    protected function get_render_preview_args() {
        return array(
            'instance_id' => array(
                'type'              => 'integer',
                'required'          => true,
                'sanitize_callback' => 'absint',
                'validate_callback' => function ( $value ) {
                    return $value > 0;
                },
            ),
            'attributes'  => array(
                'type'     => 'object',
                'required' => false,
            ),
        );
    }

    /**
     * Handles preview rendering requests for the block editor.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function render_preview( WP_REST_Request $request ) {
        $nonce_validation = $this->validate_request_nonce( $request );

        if ( is_wp_error( $nonce_validation ) ) {
            return $nonce_validation;
        }

        $instance_id = absint( $request->get_param( 'instance_id' ) );

        if ( $instance_id <= 0 ) {
            return new WP_Error(
                'my_articles_missing_instance_id',
                __( 'ID d\'instance manquant.', 'mon-articles' ),
                array( 'status' => 400 )
            );
        }

        if ( ! current_user_can( 'edit_post', $instance_id ) ) {
            return new WP_Error(
                'my_articles_preview_forbidden',
                __( 'Vous n’avez pas les droits nécessaires pour prévisualiser ce module.', 'mon-articles' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }

        $validation = $this->plugin->validate_instance_for_request( $instance_id );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        if ( ! class_exists( 'My_Articles_Shortcode' ) ) {
            return new WP_Error(
                'my_articles_shortcode_unavailable',
                __( 'Le shortcode est indisponible.', 'mon-articles' ),
                array( 'status' => 500 )
            );
        }

        $attributes = $request->get_param( 'attributes' );
        if ( ! is_array( $attributes ) ) {
            $attributes = array();
        }

        $overrides = array();

        if ( class_exists( 'My_Articles_Block' ) && method_exists( 'My_Articles_Block', 'prepare_overrides_from_attributes' ) ) {
            $overrides = My_Articles_Block::prepare_overrides_from_attributes( $attributes );
        } else {
            $overrides = $this->build_preview_overrides( $attributes );
        }

        $shortcode_instance = My_Articles_Shortcode::get_instance();

        $html = $shortcode_instance->render_shortcode(
            array(
                'id'        => $instance_id,
                'overrides' => $overrides,
            )
        );

        $options_meta = get_post_meta( $instance_id, '_my_articles_settings', true );
        if ( ! is_array( $options_meta ) ) {
            $options_meta = array();
        }

        $normalized_options = My_Articles_Shortcode::normalize_instance_options( array_merge( $options_meta, $overrides ) );

        $last_summary = My_Articles_Shortcode::get_last_render_summary();
        $summary_matches_instance = is_array( $last_summary )
            && isset( $last_summary['instance_id'] )
            && (int) $last_summary['instance_id'] === (int) $instance_id;

        $summary_metrics = array();
        $summary_options = array();

        if ( $summary_matches_instance ) {
            if ( isset( $last_summary['metrics'] ) && is_array( $last_summary['metrics'] ) ) {
                foreach ( $last_summary['metrics'] as $metric_key => $metric_value ) {
                    if ( is_scalar( $metric_value ) ) {
                        if ( is_bool( $metric_value ) ) {
                            $summary_metrics[ $metric_key ] = (bool) $metric_value;
                        } elseif ( is_numeric( $metric_value ) ) {
                            $summary_metrics[ $metric_key ] = (int) $metric_value;
                        } else {
                            $summary_metrics[ $metric_key ] = sanitize_text_field( (string) $metric_value );
                        }
                    }
                }
            }

            if ( isset( $last_summary['options'] ) && is_array( $last_summary['options'] ) ) {
                foreach ( $last_summary['options'] as $option_key => $option_value ) {
                    if ( is_bool( $option_value ) ) {
                        $summary_options[ $option_key ] = (bool) $option_value;
                    } elseif ( is_scalar( $option_value ) ) {
                        if ( is_numeric( $option_value ) ) {
                            $summary_options[ $option_key ] = (int) $option_value;
                        } else {
                            $summary_options[ $option_key ] = sanitize_text_field( (string) $option_value );
                        }
                    }
                }
            }
        }

        $instance_title = get_the_title( $instance_id );
        $metadata       = array(
            'instance_id'            => $instance_id,
            'instance_title'         => sanitize_text_field( wp_strip_all_tags( $instance_title ) ),
            'display_mode'           => isset( $normalized_options['display_mode'] ) ? sanitize_key( $normalized_options['display_mode'] ) : '',
            'has_swiper'             => isset( $normalized_options['display_mode'] ) && 'slideshow' === $normalized_options['display_mode'],
            'design_preset'          => isset( $normalized_options['design_preset'] ) ? sanitize_key( $normalized_options['design_preset'] ) : '',
            'design_preset_locked'   => ! empty( $normalized_options['design_preset_locked'] ),
            'thumbnail_aspect_ratio' => isset( $normalized_options['thumbnail_aspect_ratio'] ) ? sanitize_text_field( (string) $normalized_options['thumbnail_aspect_ratio'] ) : '',
            'has_content'            => ( '' !== trim( wp_strip_all_tags( (string) $html ) ) ),
        );

        if ( ! empty( $summary_metrics ) ) {
            $metadata['metrics'] = $summary_metrics;
        }

        if ( ! empty( $summary_options ) ) {
            $metadata['options_snapshot'] = $summary_options;
        }

        return rest_ensure_response(
            array(
                'html'     => $html,
                'metadata' => $metadata,
            )
        );
    }

    /**
     * Builds shortcode overrides when the block helper is unavailable.
     *
     * @param array $attributes Block attributes.
     *
     * @return array
     */
    protected function build_preview_overrides( array $attributes ) {
        $defaults  = My_Articles_Shortcode::get_default_options();
        $overrides = array();
        $filtered  = array_intersect_key( $attributes, $defaults );

        $boolean_override_keys = array(
            'slideshow_loop',
            'slideshow_autoplay',
            'slideshow_pause_on_interaction',
            'slideshow_pause_on_mouse_enter',
            'slideshow_respect_reduced_motion',
            'slideshow_show_navigation',
            'slideshow_show_pagination',
            'load_more_auto',
        );

        foreach ( $filtered as $key => $raw_value ) {
            $default_value = $defaults[ $key ];

            if ( null === $raw_value ) {
                continue;
            }

            if ( in_array( $key, $boolean_override_keys, true ) ) {
                $overrides[ $key ] = ! empty( $raw_value ) ? 1 : 0;
                continue;
            }

            if ( 'slideshow_delay' === $key ) {
                $value = (int) $raw_value;

                if ( $value < 0 ) {
                    $value = 0;
                }

                if ( $value > 0 && $value < 1000 ) {
                    $value = 1000;
                }

                if ( $value > 20000 ) {
                    $value = 20000;
                }

                if ( 0 === $value ) {
                    $value = (int) $default_value;
                }

                $overrides[ $key ] = $value;
                continue;
            }

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

    /**
     * Extracts a scalar string representation from arbitrary REST payloads.
     *
     * Mirrors the guards used across the plugin when reading request data so
     * callbacks never forward complex values—such as WP_REST_Request
     * instances—to core sanitizers that expect strings.
     *
     * @param mixed $value Raw value to normalize.
     *
     * @return string|null Normalized scalar string or null when unavailable.
     */
    private function normalize_request_scalar( $value ) {
        return my_articles_normalize_scalar_value( $value );
    }

    /**
     * Sanitizes arbitrary text arguments received via the REST API.
     *
     * @param mixed           $value   Raw value provided by the request.
     * @param WP_REST_Request $request REST request instance.
     * @param string          $param   Parameter name.
     *
     * @return string Sanitized string.
     */
    public function sanitize_text_arg( $value, $request = null, $param = '' ) {
        $normalized = $this->normalize_request_scalar( $value );

        if ( null === $normalized ) {
            return '';
        }

        return sanitize_text_field( $normalized );
    }

    /**
     * Sanitizes arguments that should conform to key-like structures.
     *
     * @param mixed           $value   Raw value provided by the request.
     * @param WP_REST_Request $request REST request instance.
     * @param string          $param   Parameter name.
     *
     * @return string Sanitized key string.
     */
    public function sanitize_key_arg( $value, $request = null, $param = '' ) {
        $normalized = $this->normalize_request_scalar( $value );

        if ( null === $normalized ) {
            return '';
        }

        return sanitize_key( $normalized );
    }

    /**
     * Sanitizes arguments that represent term slugs or similar titles.
     *
     * @param mixed           $value   Raw value provided by the request.
     * @param WP_REST_Request $request REST request instance.
     * @param string          $param   Parameter name.
     *
     * @return string Sanitized title string.
     */
    public function sanitize_title_arg( $value, $request = null, $param = '' ) {
        $normalized = $this->normalize_request_scalar( $value );

        if ( null === $normalized ) {
            return '';
        }

        return sanitize_title( $normalized );
    }

    /**
     * Sanitizes the filters parameter, accepting JSON strings or arrays.
     *
     * @param mixed            $value   Raw value provided by the request.
     * @param WP_REST_Request  $request REST request instance.
     * @param string           $param   Parameter name.
     *
     * @return array<int, array{taxonomy:string,slug:string}> Sanitized filters.
     */
    public function sanitize_filters_arg( $value, $request, $param ) {
        $value = my_articles_prepare_filters_value( $value );

        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );

            if ( is_array( $decoded ) ) {
                $value = $decoded;
            }
        }

        return My_Articles_Shortcode::sanitize_filter_pairs( $value );
    }
}

