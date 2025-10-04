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
                'sanitize_callback' => 'sanitize_title',
            ),
            'current_url' => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'search'      => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
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
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'category'    => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_title',
            ),
            'search'      => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
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
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'post_type' => array(
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_key',
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
                'current_url'  => $request->get_param( 'current_url' ),
                'http_referer' => $request->get_header( 'referer' ),
                'search'       => $request->get_param( 'search' ),
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
                'search'      => $request->get_param( 'search' ),
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
}
