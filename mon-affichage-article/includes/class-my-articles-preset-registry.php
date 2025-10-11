<?php
if ( ! defined( 'WPINC' ) ) {
    die;
}

class My_Articles_Preset_Registry {
    /**
     * @var My_Articles_Preset_Registry
     */
    private static $instance = null;

    /**
     * Cached preset definitions keyed by identifier.
     *
     * @var array<string, array>
     */
    private $presets_cache = null;

    /**
     * Cached index metadata.
     *
     * @var array|null
     */
    private $index_cache = null;

    /**
     * Returns the singleton instance.
     *
     * @return My_Articles_Preset_Registry
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Retrieves normalized preset definitions keyed by identifier.
     *
     * @return array<string, array>
     */
    public function get_presets() {
        if ( is_array( $this->presets_cache ) ) {
            return $this->presets_cache;
        }

        $cache_group = 'my-articles-presets';
        $cached      = function_exists( 'wp_cache_get' ) ? wp_cache_get( 'catalog', $cache_group ) : false;

        if ( is_array( $cached ) ) {
            $this->presets_cache = $cached;
            return $this->presets_cache;
        }

        $loaded = $this->load_presets_from_manifests();

        $this->presets_cache = $loaded;

        if ( function_exists( 'wp_cache_set' ) ) {
            wp_cache_set( 'catalog', $this->presets_cache, $cache_group, HOUR_IN_SECONDS );
        }

        return $this->presets_cache;
    }

    /**
     * Returns preset data formatted for the shortcode layer.
     *
     * @return array<string, array>
     */
    public function get_presets_for_shortcode() {
        $presets = $this->get_presets();

        $normalized = array();

        foreach ( $presets as $id => $definition ) {
            if ( ! is_string( $id ) || '' === $id ) {
                continue;
            }

            $normalized[ $id ] = array(
                'label'       => $definition['label'] ?? $id,
                'description' => $definition['description'] ?? '',
                'locked'      => ! empty( $definition['locked'] ),
                'tags'        => isset( $definition['tags'] ) && is_array( $definition['tags'] ) ? $definition['tags'] : array(),
                'values'      => isset( $definition['values'] ) && is_array( $definition['values'] ) ? $definition['values'] : array(),
            );
        }

        return $normalized;
    }

    /**
     * Returns preset data formatted for REST responses.
     *
     * @return array<int, array>
     */
    public function get_presets_for_rest() {
        $presets = $this->get_presets();

        return array_values( array_map( array( $this, 'filter_rest_fields' ), $presets ) );
    }

    /**
     * Retrieves the catalog version string.
     *
     * @return string
     */
    public function get_version() {
        $index = $this->get_index_manifest();

        if ( isset( $index['version'] ) && is_scalar( $index['version'] ) ) {
            return (string) $index['version'];
        }

        return '0';
    }

    /**
     * Flushes any cached data.
     */
    public function flush_cache() {
        $this->presets_cache = null;
        $this->index_cache   = null;

        if ( function_exists( 'wp_cache_delete' ) ) {
            wp_cache_delete( 'catalog', 'my-articles-presets' );
        }
    }

    /**
     * Rebuilds the index manifest based on the current directory contents.
     *
     * @param string|null $version Optional catalog version to persist.
     * @return array The rebuilt index contents.
     */
    public function rebuild_index( $version = null ) {
        $base_dir = $this->get_presets_dir();

        if ( ! is_dir( $base_dir ) ) {
            return array();
        }

        $entries = array();
        $handle  = opendir( $base_dir );

        if ( ! $handle ) {
            return array();
        }

        while ( false !== ( $item = readdir( $handle ) ) ) {
            if ( '.' === $item || '..' === $item ) {
                continue;
            }

            $preset_dir = $this->path_join( $base_dir, $item );

            if ( ! is_dir( $preset_dir ) ) {
                continue;
            }

            $manifest_path = $this->path_join( $preset_dir, 'manifest.json' );

            if ( ! file_exists( $manifest_path ) ) {
                continue;
            }

            $entry = array(
                'id'       => $item,
                'manifest' => $this->relative_path( $manifest_path ),
            );

            $tags_path = $this->path_join( $preset_dir, 'tags.json' );
            if ( file_exists( $tags_path ) ) {
                $entry['tags'] = $this->relative_path( $tags_path );
            }

            $thumbnail_path = $this->find_thumbnail_for_directory( $preset_dir );
            if ( $thumbnail_path ) {
                $entry['thumbnail'] = $this->relative_path( $thumbnail_path );
            }

            $entries[] = $entry;
        }

        closedir( $handle );

        usort(
            $entries,
            function ( $a, $b ) {
                return strcmp( $a['id'] ?? '', $b['id'] ?? '' );
            }
        );

        $index = array(
            'version'      => $version ? (string) $version : $this->get_version(),
            'generated_at' => gmdate( 'c' ),
            'presets'      => $entries,
        );

        $this->write_index_manifest( $index );
        $this->index_cache   = $index;
        $this->presets_cache = null;

        if ( function_exists( 'wp_cache_delete' ) ) {
            wp_cache_delete( 'catalog', 'my-articles-presets' );
        }

        return $index;
    }

    /**
     * Absolute path to the presets directory.
     *
     * @return string
     */
    public function get_presets_dir() {
        $base_dir = defined( 'MY_ARTICLES_PLUGIN_DIR' ) ? MY_ARTICLES_PLUGIN_DIR : dirname( __FILE__, 2 ) . '/';

        $path = $this->path_join( $base_dir, 'config/design-presets' );

        return $path;
    }

    /**
     * Absolute path to the index manifest file.
     *
     * @return string
     */
    public function get_index_path() {
        return $this->path_join( $this->get_presets_dir(), 'index.json' );
    }

    /**
     * Converts an absolute path to a relative path inside the presets directory.
     *
     * @param string $absolute Absolute path.
     * @return string
     */
    private function relative_path( $absolute ) {
        $base = $this->get_presets_dir();
        $base = rtrim( $base, '/\\' ) . DIRECTORY_SEPARATOR;

        if ( 0 === strpos( $absolute, $base ) ) {
            $relative = substr( $absolute, strlen( $base ) );
        } else {
            $relative = basename( $absolute );
        }

        return str_replace( array( '\\', DIRECTORY_SEPARATOR ), '/', $relative );
    }

    /**
     * Locates a thumbnail file within a preset directory.
     *
     * @param string $preset_dir Directory path.
     * @return string|null Absolute path if found.
     */
    private function find_thumbnail_for_directory( $preset_dir ) {
        $candidates = array( 'thumbnail.svg', 'thumbnail.png', 'thumbnail.jpg', 'thumbnail.jpeg' );

        foreach ( $candidates as $candidate ) {
            $path = $this->path_join( $preset_dir, $candidate );

            if ( file_exists( $path ) ) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Resolves the index manifest, building a fallback when necessary.
     *
     * @return array
     */
    private function get_index_manifest() {
        if ( is_array( $this->index_cache ) ) {
            return $this->index_cache;
        }

        $path = $this->get_index_path();
        $data = $this->read_json_file( $path );

        if ( is_array( $data ) ) {
            $this->index_cache = $data;
            return $this->index_cache;
        }

        $fallback = array(
            'version'      => '0',
            'generated_at' => gmdate( 'c' ),
            'presets'      => array(),
        );

        $this->index_cache = $fallback;

        return $this->index_cache;
    }

    /**
     * Loads preset definitions from manifest files.
     *
     * @return array<string, array>
     */
    private function load_presets_from_manifests() {
        $index   = $this->get_index_manifest();
        $base    = $this->get_presets_dir();
        $presets = array();

        if ( isset( $index['presets'] ) && is_array( $index['presets'] ) ) {
            foreach ( $index['presets'] as $entry ) {
                if ( ! is_array( $entry ) || empty( $entry['id'] ) || empty( $entry['manifest'] ) ) {
                    continue;
                }

                $preset_id = (string) $entry['id'];
                $manifest  = $this->read_json_file( $this->path_join( $base, $entry['manifest'] ) );

                if ( ! is_array( $manifest ) ) {
                    continue;
                }

                $normalized = $this->normalize_manifest( $preset_id, $manifest );

                $tags_path = isset( $entry['tags'] ) ? $this->path_join( $base, $entry['tags'] ) : null;
                $tags      = $this->load_tags_from_sources( $normalized, $tags_path );

                $normalized['tags'] = $tags;

                if ( ! empty( $entry['thumbnail'] ) ) {
                    $normalized['thumbnail'] = $this->resolve_public_url( $entry['thumbnail'] );
                } else {
                    $normalized['thumbnail'] = '';
                }

                $presets[ $preset_id ] = $normalized;
            }
        }

        $fallbacks = $this->get_builtin_fallbacks();

        foreach ( $fallbacks as $fallback_id => $definition ) {
            if ( ! isset( $presets[ $fallback_id ] ) ) {
                $presets[ $fallback_id ] = $definition;
            }
        }

        return $presets;
    }

    /**
     * Normalizes a manifest structure.
     *
     * @param string $preset_id Preset identifier.
     * @param array  $manifest  Raw manifest data.
     * @return array
     */
    private function normalize_manifest( $preset_id, array $manifest ) {
        $label = $this->translate_text( $manifest['label'] ?? $preset_id );

        if ( '' === $label ) {
            $label = $preset_id;
        }

        $description = $this->translate_text( $manifest['description'] ?? '' );
        $locked      = ! empty( $manifest['locked'] );

        $values = array();
        if ( isset( $manifest['values'] ) && is_array( $manifest['values'] ) ) {
            foreach ( $manifest['values'] as $key => $value ) {
                if ( is_string( $key ) && ( is_scalar( $value ) || is_null( $value ) ) ) {
                    $values[ $key ] = $value;
                }
            }
        }

        $swatch = array();
        if ( isset( $manifest['swatch'] ) && is_array( $manifest['swatch'] ) ) {
            foreach ( $manifest['swatch'] as $key => $value ) {
                if ( is_string( $key ) && is_scalar( $value ) ) {
                    $swatch[ $key ] = (string) $value;
                }
            }
        }

        if ( empty( $swatch ) ) {
            $swatch = array(
                'background' => isset( $values['module_bg_color'] ) ? (string) $values['module_bg_color'] : '#ffffff',
                'accent'     => isset( $values['pagination_color'] ) ? (string) $values['pagination_color'] : '#2563eb',
                'heading'    => isset( $values['title_color'] ) ? (string) $values['title_color'] : '#1f2937',
            );
        }

        $result = array(
            'id'          => $preset_id,
            'label'       => $label,
            'description' => $description,
            'locked'      => $locked,
            'values'      => $values,
            'swatch'      => $swatch,
        );

        if ( isset( $manifest['tags'] ) && is_array( $manifest['tags'] ) ) {
            $result['tags'] = $this->sanitize_tag_list( $manifest['tags'] );
        }

        return $result;
    }

    /**
     * Loads tags from the manifest and optional external source file.
     *
     * @param array       $normalized Current normalized preset definition.
     * @param string|null $extra_path Optional absolute path to an additional tags file.
     * @return array
     */
    private function load_tags_from_sources( array $normalized, $extra_path ) {
        $tags = array();

        if ( isset( $normalized['tags'] ) && is_array( $normalized['tags'] ) ) {
            $tags = $normalized['tags'];
        }

        $from_file = array();

        if ( $extra_path && file_exists( $extra_path ) ) {
            $decoded = $this->read_json_file( $extra_path );

            if ( is_array( $decoded ) ) {
                $from_file = $this->sanitize_tag_list( $decoded );
            }
        }

        $combined = array();
        foreach ( array_merge( $tags, $from_file ) as $tag ) {
            if ( ! is_string( $tag ) || '' === $tag ) {
                continue;
            }

            $translated = $this->translate_text( $tag );

            if ( in_array( $translated, $combined, true ) ) {
                continue;
            }

            $combined[] = $translated;
        }

        return $combined;
    }

    /**
     * Normalizes a list of tag labels.
     *
     * @param array $raw Raw tags.
     * @return array
     */
    private function sanitize_tag_list( array $raw ) {
        $clean = array();

        foreach ( $raw as $tag ) {
            if ( is_scalar( $tag ) ) {
                $label = (string) $tag;

                if ( '' !== $label && ! in_array( $label, $clean, true ) ) {
                    $clean[] = $label;
                }
            }
        }

        return $clean;
    }

    /**
     * Provides built-in fallback presets.
     *
     * @return array<string, array>
     */
    private function get_builtin_fallbacks() {
        $label = $this->translate_text( 'Personnalisé' );
        if ( '' === $label ) {
            $label = 'custom';
        }

        return array(
            'custom' => array(
                'id'          => 'custom',
                'label'       => $label,
                'description' => $this->translate_text( 'Conservez vos propres réglages de couleurs et d’espacements.' ),
                'locked'      => false,
                'values'      => array(),
                'swatch'      => array(
                    'background' => '#ffffff',
                    'accent'     => '#1f2937',
                    'heading'    => '#1f2937',
                ),
                'tags'        => array(
                    $this->translate_text( 'Libre' ),
                    $this->translate_text( 'Personnalisé' ),
                ),
                'thumbnail'   => $this->resolve_public_url( 'custom/thumbnail.svg' ),
            ),
        );
    }

    /**
     * Resolves a public URL to a preset asset.
     *
     * @param string $relative Relative path inside the presets directory.
     * @return string
     */
    private function resolve_public_url( $relative ) {
        if ( ! is_string( $relative ) || '' === $relative ) {
            return '';
        }

        $relative = ltrim( $relative, '/\\' );
        $base_url = defined( 'MY_ARTICLES_PLUGIN_URL' ) ? MY_ARTICLES_PLUGIN_URL : '';

        if ( '' === $base_url ) {
            return '';
        }

        if ( function_exists( 'trailingslashit' ) ) {
            return trailingslashit( $base_url ) . 'config/design-presets/' . $relative;
        }

        return rtrim( $base_url, '/\\' ) . '/config/design-presets/' . $relative;
    }

    /**
     * Filters preset fields for REST responses.
     *
     * @param array $preset Normalized preset.
     * @return array
     */
    private function filter_rest_fields( array $preset ) {
        return array(
            'id'          => $preset['id'] ?? '',
            'label'       => $preset['label'] ?? '',
            'description' => $preset['description'] ?? '',
            'locked'      => ! empty( $preset['locked'] ),
            'tags'        => isset( $preset['tags'] ) && is_array( $preset['tags'] ) ? $preset['tags'] : array(),
            'values'      => isset( $preset['values'] ) && is_array( $preset['values'] ) ? $preset['values'] : array(),
            'thumbnail'   => $preset['thumbnail'] ?? '',
            'swatch'      => isset( $preset['swatch'] ) && is_array( $preset['swatch'] ) ? $preset['swatch'] : array(),
        );
    }

    /**
     * Reads a JSON file and returns its decoded representation.
     *
     * @param string $path Absolute path to the file.
     * @return array|null
     */
    private function read_json_file( $path ) {
        if ( ! $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
            return null;
        }

        $decoded = null;

        if ( function_exists( 'wp_json_file_decode' ) ) {
            $decoded = wp_json_file_decode( $path, array( 'associative' => true ) );
        } else {
            $contents = file_get_contents( $path );

            if ( false !== $contents ) {
                $decoded = json_decode( $contents, true );
            }
        }

        if ( ! is_array( $decoded ) ) {
            return null;
        }

        return $decoded;
    }

    /**
     * Writes the index manifest back to disk.
     *
     * @param array $index Index data.
     */
    private function write_index_manifest( array $index ) {
        $path = $this->get_index_path();
        $dir  = dirname( $path );

        if ( ! is_dir( $dir ) ) {
            if ( function_exists( 'wp_mkdir_p' ) ) {
                wp_mkdir_p( $dir );
            } else {
                mkdir( $dir, 0755, true );
            }
        }

        $flags = 0;
        if ( defined( 'JSON_PRETTY_PRINT' ) ) {
            $flags |= JSON_PRETTY_PRINT;
        }
        if ( defined( 'JSON_UNESCAPED_SLASHES' ) ) {
            $flags |= JSON_UNESCAPED_SLASHES;
        }
        if ( defined( 'JSON_UNESCAPED_UNICODE' ) ) {
            $flags |= JSON_UNESCAPED_UNICODE;
        }

        $encoded = wp_json_encode( $index, $flags );

        if ( false === $encoded ) {
            return;
        }

        file_put_contents( $path, $encoded );
    }

    /**
     * Translation helper that tolerates missing WordPress context.
     *
     * @param string $text Text to translate.
     * @return string
     */
    private function translate_text( $text ) {
        if ( ! is_string( $text ) || '' === $text ) {
            return '';
        }

        if ( function_exists( '__' ) ) {
            return __( $text, 'mon-articles' );
        }

        return $text;
    }

    /**
     * Joins two path fragments.
     *
     * @param string $base Base path.
     * @param string $segment Segment to append.
     * @return string
     */
    private function path_join( $base, $segment ) {
        $base    = rtrim( $base, '/\\' );
        $segment = ltrim( $segment, '/\\' );

        return $base . DIRECTORY_SEPARATOR . $segment;
    }
}
