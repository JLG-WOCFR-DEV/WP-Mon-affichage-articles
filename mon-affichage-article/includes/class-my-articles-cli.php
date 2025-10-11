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
            $presets     = My_Articles_Shortcode::get_design_presets();

            if ( ! is_array( $presets ) ) {
                $presets = array();
            }

            $json_flags = 0;
            if ( defined( 'JSON_PRETTY_PRINT' ) ) {
                $json_flags |= JSON_PRETTY_PRINT;
            }
            if ( defined( 'JSON_UNESCAPED_SLASHES' ) ) {
                $json_flags |= JSON_UNESCAPED_SLASHES;
            }
            if ( defined( 'JSON_UNESCAPED_UNICODE' ) ) {
                $json_flags |= JSON_UNESCAPED_UNICODE;
            }

            $encoded = wp_json_encode( $presets, $json_flags );

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

            $merge = ! empty( $assoc_args['merge'] );

            if ( $merge ) {
                $current = My_Articles_Shortcode::get_design_presets();
                if ( ! is_array( $current ) ) {
                    $current = array();
                }

                $decoded = array_merge( $current, $decoded );
            }

            $manifest_path = My_Articles_Shortcode::get_design_presets_manifest_path();

            $json_flags = 0;
            if ( defined( 'JSON_PRETTY_PRINT' ) ) {
                $json_flags |= JSON_PRETTY_PRINT;
            }
            if ( defined( 'JSON_UNESCAPED_SLASHES' ) ) {
                $json_flags |= JSON_UNESCAPED_SLASHES;
            }
            if ( defined( 'JSON_UNESCAPED_UNICODE' ) ) {
                $json_flags |= JSON_UNESCAPED_UNICODE;
            }

            $encoded = wp_json_encode( $decoded, $json_flags );

            if ( false === $encoded ) {
                WP_CLI::error( 'Impossible de sérialiser les préréglages importés.' );
            }

            $written = file_put_contents( $manifest_path, $encoded );

            if ( false === $written ) {
                WP_CLI::error( sprintf( 'Impossible d\'écrire dans %s.', $manifest_path ) );
            }

            My_Articles_Shortcode::flush_design_presets_cache();

            WP_CLI::success( sprintf( 'Préréglages importés depuis %s.', $source ) );
        }
    }

    WP_CLI::add_command( 'my-articles presets', 'My_Articles_CLI_Presets_Command' );
}
