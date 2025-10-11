<?php
if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( defined( 'WP_CLI' ) && WP_CLI && ! class_exists( 'My_Articles_CLI_Presets_Command' ) ) {
    /**
     * Manage design presets for the Mon Affichage Articles plugin.
     */
    class My_Articles_CLI_Presets_Command extends WP_CLI_Command {
        /**
         * Exporte les préréglages de design au format JSON.
         *
         * ## OPTIONS
         *
         * <file>
         * : Chemin du fichier de sortie. Utilisez "-" pour afficher le JSON dans le terminal.
         *
         * ## EXEMPLES
         *
         *     wp my-articles presets export presets.json
         *
         * @param array $args       Arguments positionnels.
         * @param array $assoc_args Arguments nommés.
         */
        public function export( $args, $assoc_args ) {
            if ( empty( $args ) ) {
                WP_CLI::error( 'Veuillez fournir un chemin de fichier.' );
            }

            $destination = (string) $args[0];
            $registry = My_Articles_Preset_Registry::get_instance();

            $catalog = array(
                'version'      => $registry->get_version(),
                'generated_at' => gmdate( 'c' ),
                'presets'      => $registry->get_presets_for_rest(),
            );

            $json_flags = self::get_json_flags();

            $encoded = wp_json_encode( $catalog, $json_flags );

            if ( false === $encoded ) {
                WP_CLI::error( 'Impossible de sérialiser les préréglages en JSON.' );
            }

            if ( '-' === $destination ) {
                WP_CLI::line( $encoded );
                WP_CLI::success( 'Préréglages exportés vers la sortie standard.' );
                return;
            }

            $written = file_put_contents( $destination, $encoded );

            if ( false === $written ) {
                WP_CLI::error( sprintf( 'Échec de l\'écriture dans le fichier %s.', $destination ) );
            }

            WP_CLI::success( sprintf( 'Préréglages exportés vers %s.', $destination ) );
        }

        /**
         * Importe des préréglages de design depuis un fichier JSON.
         *
         * ## OPTIONS
         *
         * <file>
         * : Chemin du fichier JSON à importer.
         *
         * [--merge]
         * : Fusionne les préréglages existants avec ceux du fichier au lieu de les remplacer.
         *
         * ## EXEMPLES
         *
         *     wp my-articles presets import presets.json --merge
         *
         * @param array $args       Arguments positionnels.
         * @param array $assoc_args Arguments nommés.
         */
        public function import( $args, $assoc_args ) {
            if ( empty( $args ) ) {
                WP_CLI::error( 'Veuillez fournir un fichier JSON à importer.' );
            }

            $source = (string) $args[0];

            if ( ! file_exists( $source ) || ! is_readable( $source ) ) {
                WP_CLI::error( sprintf( 'Fichier introuvable ou illisible : %s', $source ) );
            }

            $contents = file_get_contents( $source );

            if ( false === $contents ) {
                WP_CLI::error( sprintf( 'Impossible de lire le fichier %s.', $source ) );
            }

            $decoded = json_decode( $contents, true );

            if ( ! is_array( $decoded ) ) {
                WP_CLI::error( 'Le contenu fourni n’est pas un JSON valide.' );
            }

            $registry = My_Articles_Preset_Registry::get_instance();
            $base_dir = $registry->get_presets_dir();

            if ( ! is_dir( $base_dir ) ) {
                if ( function_exists( 'wp_mkdir_p' ) ) {
                    wp_mkdir_p( $base_dir );
                } else {
                    mkdir( $base_dir, 0755, true );
                }
            }

            $presets_payload = array();

            if ( isset( $decoded['presets'] ) && is_array( $decoded['presets'] ) ) {
                $presets_payload = $decoded['presets'];
            } else {
                $presets_payload = $decoded;
            }

            $created = 0;

            foreach ( $presets_payload as $key => $definition ) {
                if ( is_array( $definition ) && isset( $definition['id'] ) ) {
                    $preset_id = (string) $definition['id'];
                } elseif ( is_string( $key ) ) {
                    $preset_id = $key;
                } else {
                    continue;
                }

                if ( '' === $preset_id ) {
                    continue;
                }

                $target_dir = trailingslashit( $base_dir ) . $preset_id;

                if ( ! is_dir( $target_dir ) ) {
                    if ( function_exists( 'wp_mkdir_p' ) ) {
                        wp_mkdir_p( $target_dir );
                    } else {
                        mkdir( $target_dir, 0755, true );
                    }
                }

                $manifest = array(
                    'id'          => $preset_id,
                    'label'       => isset( $definition['label'] ) ? (string) $definition['label'] : $preset_id,
                    'description' => isset( $definition['description'] ) ? (string) $definition['description'] : '',
                    'locked'      => ! empty( $definition['locked'] ),
                    'values'      => isset( $definition['values'] ) && is_array( $definition['values'] ) ? $definition['values'] : array(),
                );

                if ( isset( $definition['swatch'] ) && is_array( $definition['swatch'] ) ) {
                    $manifest['swatch'] = $definition['swatch'];
                }

                if ( isset( $definition['tags'] ) && is_array( $definition['tags'] ) ) {
                    $manifest['tags'] = $definition['tags'];
                }

                $manifest_path = trailingslashit( $target_dir ) . 'manifest.json';
                $tags_path     = trailingslashit( $target_dir ) . 'tags.json';

                $json_flags = self::get_json_flags();

                $manifest_encoded = wp_json_encode( $manifest, $json_flags );

                if ( false === $manifest_encoded ) {
                    WP_CLI::warning( sprintf( 'Impossible de sérialiser le manifeste pour %s.', $preset_id ) );
                    continue;
                }

                file_put_contents( $manifest_path, $manifest_encoded );

                $tags = array();
                if ( isset( $definition['tags'] ) && is_array( $definition['tags'] ) ) {
                    $tags = array();
                    foreach ( $definition['tags'] as $tag ) {
                        if ( is_scalar( $tag ) ) {
                            $tags[] = (string) $tag;
                        }
                    }
                }

                file_put_contents( $tags_path, wp_json_encode( $tags, self::get_json_flags() ) );

                $created++;
            }

            $version = isset( $decoded['version'] ) && is_scalar( $decoded['version'] ) ? (string) $decoded['version'] : null;

            $index = $registry->rebuild_index( $version );

            My_Articles_Shortcode::flush_design_presets_cache();

            WP_CLI::success( sprintf( 'Préréglages importés (%d manifestes mis à jour).', $created ) );
            WP_CLI::log( sprintf( 'Version du catalogue : %s', $index['version'] ?? '0' ) );
        }

        /**
         * Synchronise l’index des préréglages et vérifie les ressources statiques.
         *
         * ## OPTIONS
         *
         * [--version=<version>]
         * : Force une nouvelle version pour le catalogue généré.
         */
        public function sync( $args, $assoc_args ) {
            $registry = My_Articles_Preset_Registry::get_instance();
            $version  = isset( $assoc_args['version'] ) ? (string) $assoc_args['version'] : null;

            $index = $registry->rebuild_index( $version );

            $missing = array();
            $base    = trailingslashit( $registry->get_presets_dir() );

            if ( isset( $index['presets'] ) && is_array( $index['presets'] ) ) {
                foreach ( $index['presets'] as $entry ) {
                    if ( empty( $entry['id'] ) || empty( $entry['manifest'] ) ) {
                        continue;
                    }

                    $manifest_path = $base . ltrim( (string) $entry['manifest'], '/' );
                    if ( ! file_exists( $manifest_path ) ) {
                        $missing[] = sprintf( '%s (manifest)', $entry['id'] );
                    }

                    if ( ! empty( $entry['thumbnail'] ) ) {
                        $thumbnail_path = $base . ltrim( (string) $entry['thumbnail'], '/' );
                        if ( ! file_exists( $thumbnail_path ) ) {
                            $missing[] = sprintf( '%s (thumbnail)', $entry['id'] );
                        }
                    }
                }
            }

            My_Articles_Shortcode::flush_design_presets_cache();

            if ( ! empty( $missing ) ) {
                foreach ( $missing as $warning ) {
                    WP_CLI::warning( sprintf( 'Ressource manquante : %s', $warning ) );
                }
            }

            $count = isset( $index['presets'] ) && is_array( $index['presets'] ) ? count( $index['presets'] ) : 0;

            WP_CLI::success( sprintf( 'Catalogue de préréglages synchronisé (%d manifestes).', $count ) );
        }

        private static function get_json_flags() {
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

            return $flags;
        }
    }

    WP_CLI::add_command( 'my-articles presets', 'My_Articles_CLI_Presets_Command' );
}
