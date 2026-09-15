<?php
// Fichier: includes/class-my-articles-enqueue.php

if ( ! defined( 'WPINC' ) ) {
    die;
}

class My_Articles_Enqueue {

    private static $instance;
    private $script_dependencies = array();
    private $style_dependencies = array();
    private $inline_script_registry = array();

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'register_plugin_styles_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'ensure_assets_registered' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
        add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_editor_canvas_assets' ) );
    }

    public function register_plugin_styles_scripts() {
        $vendor_url = MY_ARTICLES_PLUGIN_URL . 'assets/vendor/';

        wp_register_style( 'swiper-css', $vendor_url . 'swiper/swiper-bundle.min.css', array(), '11.0.0' );
        wp_register_style( 'my-articles-styles', MY_ARTICLES_PLUGIN_URL . 'assets/css/styles.css', array(), MY_ARTICLES_VERSION );
        wp_register_script( 'swiper-js', $vendor_url . 'swiper/swiper-bundle.min.js', array(), '11.0.0', true );
        wp_register_script( 'lazysizes', $vendor_url . 'lazysizes/lazysizes.min.js', array(), '5.3.2', true );
        wp_register_script( 'my-articles-responsive-layout', MY_ARTICLES_PLUGIN_URL . 'assets/js/responsive-layout.js', array(), MY_ARTICLES_VERSION, true );
        wp_register_script( 'my-articles-shared-runtime', MY_ARTICLES_PLUGIN_URL . 'assets/js/shared-runtime.js', array(), MY_ARTICLES_VERSION, true );
        wp_register_script( 'my-articles-filter', MY_ARTICLES_PLUGIN_URL . 'assets/js/filter.js', array( 'jquery', 'my-articles-shared-runtime' ), MY_ARTICLES_VERSION, true );
        wp_register_script( 'my-articles-load-more', MY_ARTICLES_PLUGIN_URL . 'assets/js/load-more.js', array( 'jquery', 'my-articles-shared-runtime' ), MY_ARTICLES_VERSION, true );
        wp_register_script( 'my-articles-scroll-fix', MY_ARTICLES_PLUGIN_URL . 'assets/js/scroll-fix.js', array( 'jquery' ), MY_ARTICLES_VERSION, true );
        wp_register_script(
            'my-articles-swiper-init',
            MY_ARTICLES_PLUGIN_URL . 'assets/js/swiper-init.js',
            array( 'swiper-js', 'my-articles-responsive-layout' ),
            MY_ARTICLES_VERSION,
            true
        );
        wp_register_script( 'my-articles-debug-helper', false, array(), MY_ARTICLES_VERSION, true );

        if ( function_exists( 'wp_script_add_data' ) ) {
            wp_script_add_data( 'lazysizes', 'async', true );
        }
    }

    /**
     * Declare a dependency on a registered asset handle.
     *
     * @param string $handle Asset handle.
     * @param string $type   Asset type. Accepts 'script' or 'style'.
     */
    public function declare_dependency( $handle, $type = 'script' ) {
        if ( '' === $handle ) {
            return;
        }

        $type = 'style' === $type ? 'style' : 'script';

        if ( 'script' === $type ) {
            if ( isset( $this->script_dependencies[ $handle ] ) ) {
                return;
            }

            $this->script_dependencies[ $handle ] = true;

            if ( function_exists( 'wp_enqueue_script' ) ) {
                wp_enqueue_script( $handle );
            }

            return;
        }

        if ( isset( $this->style_dependencies[ $handle ] ) ) {
            return;
        }

        $this->style_dependencies[ $handle ] = true;

        if ( function_exists( 'wp_enqueue_style' ) ) {
            wp_enqueue_style( $handle );
        }
    }

    /**
     * Push an inline script payload associated with a registered script handle.
     *
     * @param string $handle   Script handle.
     * @param string $code     JavaScript code snippet.
     * @param string $position Optional. Position relative to the script. Accepts 'before' or 'after'. Default 'after'.
     */
    public function push_inline_payload( $handle, $code, $position = 'after' ) {
        if ( '' === $handle || '' === trim( (string) $code ) ) {
            return;
        }

        $position = 'before' === $position ? 'before' : 'after';
        $signature = md5( $handle . '|' . $position . '|' . $code );

        if ( isset( $this->inline_script_registry[ $signature ] ) ) {
            return;
        }

        $this->inline_script_registry[ $signature ] = true;

        if ( function_exists( 'wp_add_inline_script' ) ) {
            wp_add_inline_script( $handle, $code, $position );
        }
    }

    public function ensure_assets_registered() {
        $this->register_plugin_styles_scripts();
    }

    public function enqueue_block_editor_assets() {
        $this->register_plugin_styles_scripts();

        $editor_handle  = 'mon-affichage-articles-editor-script';
        $preview_handle = 'mon-affichage-articles-preview';

        $translations_dir = MY_ARTICLES_PLUGIN_DIR . 'languages';

        if ( function_exists( 'wp_set_script_translations' ) && wp_script_is( $preview_handle, 'registered' ) ) {
            wp_set_script_translations( $preview_handle, 'mon-articles', $translations_dir );
        }

        $this->print_editor_flag( $preview_handle );
        $this->print_editor_flag( $editor_handle );

        $dynamic_assets = $this->get_dynamic_asset_manifest();

        if ( ! empty( $dynamic_assets ) && function_exists( 'wp_add_inline_script' ) ) {
            $manifest_json = wp_json_encode( $dynamic_assets );

            if ( false !== $manifest_json ) {
                $snippet = 'window.myArticlesAssets = window.myArticlesAssets || {}; window.myArticlesAssets.dynamic = window.myArticlesAssets.dynamic || ' . $manifest_json . ';';

                if ( wp_script_is( $preview_handle, 'registered' ) ) {
                    wp_add_inline_script( $preview_handle, $snippet, 'before' );
                }

                if ( wp_script_is( $editor_handle, 'registered' ) ) {
                    wp_add_inline_script( $editor_handle, $snippet, 'before' );
                }
            }
        }

        if ( function_exists( 'wp_add_inline_script' ) && wp_script_is( $editor_handle, 'registered' ) ) {
            if ( function_exists( 'wp_set_script_translations' ) ) {
                wp_set_script_translations( $editor_handle, 'mon-articles', $translations_dir );
            }

            $catalog = array(
                'version'      => '0',
                'generated_at' => gmdate( 'c' ),
                'presets'      => array(),
            );

            if ( class_exists( 'My_Articles_Preset_Registry' ) ) {
                $registry = My_Articles_Preset_Registry::get_instance();
                $catalog  = array(
                    'version'      => $registry->get_version(),
                    'generated_at' => gmdate( 'c' ),
                    'presets'      => $registry->get_presets_for_rest(),
                );
            } elseif ( class_exists( 'My_Articles_Shortcode' ) ) {
                $fallback = My_Articles_Shortcode::get_design_presets();
                if ( is_array( $fallback ) ) {
                    foreach ( $fallback as $preset_id => $definition ) {
                        if ( ! is_string( $preset_id ) || '' === $preset_id ) {
                            continue;
                        }

                        $catalog['presets'][] = array(
                            'id'          => $preset_id,
                            'label'       => isset( $definition['label'] ) ? (string) $definition['label'] : $preset_id,
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

            $inline_map = array();
            foreach ( $catalog['presets'] as $preset ) {
                if ( empty( $preset['id'] ) ) {
                    continue;
                }

                $inline_map[ $preset['id'] ] = array(
                    'label'       => $preset['label'] ?? $preset['id'],
                    'description' => $preset['description'] ?? '',
                    'locked'      => ! empty( $preset['locked'] ),
                    'tags'        => isset( $preset['tags'] ) && is_array( $preset['tags'] ) ? $preset['tags'] : array(),
                    'values'      => isset( $preset['values'] ) && is_array( $preset['values'] ) ? $preset['values'] : array(),
                );
            }

            $export = $inline_map;

            $catalog_json = wp_json_encode( $catalog );
            $map_json     = wp_json_encode( $inline_map );

            if ( false !== $catalog_json && false !== $map_json ) {
                $script = 'window.myArticlesDesignPresetsCatalog = ' . $catalog_json . '; window.myArticlesDesignPresets = ' . $map_json . ';';

                wp_add_inline_script( $editor_handle, $script, 'before' );
            }

            wp_add_inline_script(
                $editor_handle,
                'window.myArticlesDesignPresets = ' . wp_json_encode( $export ) . ';',
                'before'
            );

            $adapter_definitions = My_Articles_Shortcode::get_content_adapter_definitions_for_admin();
            $encoded_adapters    = wp_json_encode( $adapter_definitions );

            if ( false !== $encoded_adapters ) {
                wp_add_inline_script(
                    $editor_handle,
                    'window.myArticlesContentAdapters = ' . $encoded_adapters . ';',
                    'before'
                );
            }
        }
    }

    private function get_dynamic_asset_manifest() {
        if ( ! defined( 'MY_ARTICLES_PLUGIN_URL' ) ) {
            return array();
        }

        $base_url = MY_ARTICLES_PLUGIN_URL;

        return array(
            'swiper'    => array(
                'styles'  => array(
                    array(
                        'handle' => 'swiper-css',
                        'src'    => $base_url . 'assets/vendor/swiper/swiper-bundle.min.css',
                        'ver'    => '11.0.0',
                    ),
                ),
                'scripts' => array(
                    array(
                        'handle' => 'swiper-js',
                        'src'    => $base_url . 'assets/vendor/swiper/swiper-bundle.min.js',
                        'ver'    => '11.0.0',
                    ),
                    array(
                        'handle' => 'my-articles-swiper-init',
                        'src'    => $base_url . 'assets/js/swiper-init.js',
                        'ver'    => defined( 'MY_ARTICLES_VERSION' ) ? MY_ARTICLES_VERSION : '1.0.0',
                    ),
                ),
            ),
            'lazysizes' => array(
                'scripts' => array(
                    array(
                        'handle'     => 'lazysizes',
                        'src'        => $base_url . 'assets/vendor/lazysizes/lazysizes.min.js',
                        'ver'        => '5.3.2',
                        'attributes' => array(
                            'async' => true,
                        ),
                    ),
                ),
            ),
        );
    }

    public function register_script_data( $handle, $object_name, array $data ) {
        if ( ! class_exists( 'My_Articles_Frontend_Data' ) ) {
            return false;
        }

        return My_Articles_Frontend_Data::get_instance()->register( $handle, $object_name, $data );
    }

    /**
     * Copies preview CSS and an editor-mode flag into the WP 6.3+/7.1 iframed canvas.
     *
     * `enqueue_block_editor_assets` prints into the parent editor frame, so tile
     * styles would miss the iframe. `enqueue_block_assets` is copied into the canvas.
     * The flag must stay off on the public site.
     */
    public function enqueue_block_editor_canvas_assets() {
        if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
            return;
        }

        $this->register_plugin_styles_scripts();

        if ( function_exists( 'wp_enqueue_style' ) ) {
            wp_enqueue_style( 'my-articles-styles' );
            wp_enqueue_style( 'swiper-css' );
        }

        if ( function_exists( 'wp_register_script' ) ) {
            wp_register_script(
                'my-articles-editor-canvas-guard',
                false,
                array(),
                defined( 'MY_ARTICLES_VERSION' ) ? MY_ARTICLES_VERSION : '1.0.0',
                true
            );
        }

        if ( function_exists( 'wp_enqueue_script' ) ) {
            wp_enqueue_script( 'my-articles-editor-canvas-guard' );
        }

        $this->print_editor_flag( 'my-articles-editor-canvas-guard' );
    }

    /**
     * Whether the current request is a block editor / canvas preview.
     *
     * @return bool
     */
    public static function is_block_editor_preview_context() {
        if ( function_exists( 'wp_is_block_editor' ) && wp_is_block_editor() ) {
            return true;
        }

        if ( function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();
            if ( $screen && ! empty( $screen->is_block_editor ) ) {
                return true;
            }
        }

        if ( isset( $_GET['canvas'] ) && 'edit' === $_GET['canvas'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return true;
        }

        $route = '';

        if ( isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) && isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
            $route = (string) $GLOBALS['wp']->query_vars['rest_route'];
        } elseif ( isset( $_SERVER['REQUEST_URI'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $route = (string) $_SERVER['REQUEST_URI'];
        }

        $is_rest = ( defined( 'REST_REQUEST' ) && REST_REQUEST )
            || ( '' !== $route && false !== strpos( $route, '/wp-json/' ) );

        if ( $is_rest ) {
            $context = '';

            if ( isset( $_REQUEST['context'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $raw     = $_REQUEST['context']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $raw     = function_exists( 'wp_unslash' ) ? wp_unslash( $raw ) : $raw;
                $context = function_exists( 'sanitize_key' ) ? sanitize_key( $raw ) : strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $raw ) );
            }

            if ( 'edit' === $context ) {
                return true;
            }

            if ( '' !== $route && ( false !== strpos( $route, 'block-renderer' ) || false !== strpos( $route, 'render-preview' ) ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether interactive front scripts should be enqueued.
     *
     * @return bool
     */
    public static function should_enqueue_frontend_script() {
        if ( self::is_block_editor_preview_context() ) {
            return false;
        }

        if ( function_exists( 'is_admin' ) && is_admin() ) {
            return false;
        }

        return true;
    }

    /**
     * @param string $handle Script handle.
     */
    private function print_editor_flag( $handle ) {
        if ( '' === $handle || ! function_exists( 'wp_add_inline_script' ) || ! function_exists( 'wp_script_is' ) ) {
            return;
        }

        if ( ! wp_script_is( $handle, 'registered' ) && ! wp_script_is( $handle, 'enqueued' ) ) {
            return;
        }

        wp_add_inline_script( $handle, 'window.MY_ARTICLES_IS_EDITOR = true;', 'before' );
    }
}
