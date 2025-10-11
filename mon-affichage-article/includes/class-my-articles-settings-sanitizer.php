<?php
/**
 * Declarative sanitization layer for the global settings screen.
 *
 * The class exposes a reusable schema describing each field along with
 * its validation constraints so the logic can be unit-tested and extended
 * via filters without having to touch the WordPress-specific settings
 * screen implementation.
 */

if ( ! class_exists( 'My_Articles_Settings_Sanitizer' ) ) {
    /**
     * Normalize and validate settings values according to a declarative schema.
     */
    class My_Articles_Settings_Sanitizer {

        /**
         * Retrieve the sanitization schema.
         *
         * @return array<string, array<string, mixed>>
         */
        public static function get_schema() {
            $schema = array(
                'display_mode' => array(
                    'type'    => 'enum',
                    'allowed' => array( 'grid', 'slideshow', 'list' ),
                    'default' => 'grid',
                    'label'   => self::translate( 'Mode d\'affichage' ),
                ),
                'default_category' => array(
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_title',
                    'default'           => '',
                    'label'             => self::translate( 'Catégorie par défaut' ),
                ),
                'posts_per_page' => array(
                    'type'    => 'int',
                    'min'     => 0,
                    'max'     => 50,
                    'default' => 10,
                    'label'   => self::translate( 'Nombre d\'articles à afficher' ),
                ),
                'enable_keyword_search' => array(
                    'type'         => 'bool',
                    'default'      => 0,
                    'absent_value' => 0,
                    'label'        => self::translate( 'Activer la recherche par mots-clés' ),
                ),
                'meta_query_relation' => array(
                    'type'    => 'enum',
                    'allowed' => array( 'AND', 'OR' ),
                    'default' => 'AND',
                    'label'   => self::translate( 'Relation des meta queries' ),
                ),
                'meta_query' => array(
                    'type'    => 'meta_query',
                    'default' => array(),
                    'label'   => self::translate( 'Meta queries avancées' ),
                ),
                'desktop_columns' => array(
                    'type'    => 'int',
                    'min'     => 1,
                    'max'     => 6,
                    'default' => 3,
                    'label'   => self::translate( 'Articles visibles (Desktop)' ),
                ),
                'mobile_columns' => array(
                    'type'    => 'int',
                    'min'     => 1,
                    'max'     => 3,
                    'default' => 1,
                    'label'   => self::translate( 'Articles visibles (Mobile)' ),
                ),
                'thumbnail_aspect_ratio' => array(
                    'type'    => 'enum',
                    'allowed' => self::get_allowed_thumbnail_aspect_ratios(),
                    'default' => self::get_default_thumbnail_aspect_ratio(),
                    'label'   => self::translate( 'Ratio de la miniature' ),
                ),
                'gap_size' => array(
                    'type'    => 'int',
                    'min'     => 0,
                    'max'     => 50,
                    'default' => 25,
                    'label'   => self::translate( 'Espacement des vignettes' ),
                ),
                'border_radius' => array(
                    'type'    => 'int',
                    'min'     => 0,
                    'max'     => 50,
                    'default' => 12,
                    'label'   => self::translate( 'Arrondi des bordures' ),
                ),
                'title_color' => array(
                    'type'    => 'color',
                    'default' => '#333333',
                    'label'   => self::translate( 'Couleur du titre' ),
                ),
                'title_font_size' => array(
                    'type'    => 'int',
                    'min'     => 10,
                    'max'     => 40,
                    'default' => 16,
                    'label'   => self::translate( 'Taille de police du titre' ),
                ),
                'show_category' => array(
                    'type'         => 'bool',
                    'default'      => 1,
                    'absent_value' => 0,
                    'label'        => self::translate( 'Afficher la catégorie' ),
                ),
                'show_author' => array(
                    'type'         => 'bool',
                    'default'      => 1,
                    'absent_value' => 0,
                    'label'        => self::translate( 'Afficher l\'auteur' ),
                ),
                'show_date' => array(
                    'type'         => 'bool',
                    'default'      => 1,
                    'absent_value' => 0,
                    'label'        => self::translate( 'Afficher la date' ),
                ),
                'meta_font_size' => array(
                    'type'    => 'int',
                    'min'     => 8,
                    'max'     => 20,
                    'default' => 12,
                    'label'   => self::translate( 'Taille de police (méta)' ),
                ),
                'meta_color' => array(
                    'type'    => 'color',
                    'default' => '#6b7280',
                    'label'   => self::translate( 'Couleur des métadonnées' ),
                ),
                'meta_color_hover' => array(
                    'type'    => 'color',
                    'default' => '#000000',
                    'label'   => self::translate( 'Couleur des métadonnées (survol)' ),
                ),
                'module_bg_color' => array(
                    'type'    => 'color',
                    'default' => 'rgba(255,255,255,0)',
                    'label'   => self::translate( 'Couleur de fond du module' ),
                ),
                'vignette_bg_color' => array(
                    'type'    => 'color',
                    'default' => '#ffffff',
                    'label'   => self::translate( 'Couleur de fond de la vignette' ),
                ),
                'title_wrapper_bg_color' => array(
                    'type'    => 'color',
                    'default' => '#ffffff',
                    'label'   => self::translate( 'Couleur de fond du bloc titre' ),
                ),
                'module_margin_top' => array(
                    'type'    => 'int',
                    'min'     => 0,
                    'max'     => 200,
                    'default' => 0,
                    'label'   => self::translate( 'Marge en haut' ),
                ),
                'module_margin_bottom' => array(
                    'type'    => 'int',
                    'min'     => 0,
                    'max'     => 200,
                    'default' => 0,
                    'label'   => self::translate( 'Marge en bas' ),
                ),
                'module_margin_left' => array(
                    'type'    => 'int',
                    'min'     => 0,
                    'max'     => 200,
                    'default' => 0,
                    'label'   => self::translate( 'Marge à gauche' ),
                ),
                'module_margin_right' => array(
                    'type'    => 'int',
                    'min'     => 0,
                    'max'     => 200,
                    'default' => 0,
                    'label'   => self::translate( 'Marge à droite' ),
                ),
                'pagination_color' => array(
                    'type'    => 'color',
                    'default' => '#333333',
                    'label'   => self::translate( 'Couleur de la pagination' ),
                ),
                'instrumentation_enabled' => array(
                    'type'         => 'bool',
                    'default'      => 0,
                    'absent_value' => 0,
                    'label'        => self::translate( 'Activer l\'instrumentation' ),
                ),
                'instrumentation_channel' => array(
                    'type'    => 'enum',
                    'allowed' => array( 'console', 'dataLayer', 'fetch' ),
                    'default' => 'console',
                    'label'   => self::translate( 'Canal de sortie' ),
                ),
                'admin_theme' => array(
                    'type'    => 'enum',
                    'allowed' => array( 'auto', 'light', 'dark' ),
                    'default' => 'auto',
                    'label'   => self::translate( 'Mode d\'affichage de l\'interface' ),
                ),
            );

            if ( function_exists( 'apply_filters' ) ) {
                $schema = apply_filters( 'my_articles_settings_schema', $schema );
            }

            return $schema;
        }

        /**
         * Sanitize a raw settings array according to the schema.
         *
         * @param array<string, mixed> $input         Raw input values coming from the settings form.
         * @param callable|null        $error_handler Optional callback invoked when a field is invalid.
         *                                            Receives the field name, the error message and an
         *                                            array of contextual details.
         *
         * @return array<string, mixed> Sanitized settings.
         */
        public static function sanitize( $input, $error_handler = null ) {
            $input = is_array( $input ) ? $input : array();

            $schema = self::get_schema();
            $sanitized = array();

            foreach ( $schema as $field => $definition ) {
                $has_raw_value = array_key_exists( $field, $input );
                $raw_value     = $has_raw_value ? $input[ $field ] : null;

                $result = self::sanitize_field( $field, $definition, $raw_value, $has_raw_value );
                $sanitized[ $field ] = $result['value'];

                if ( ! $result['valid'] && is_callable( $error_handler ) ) {
                    $message = self::build_error_message( $definition );
                    call_user_func(
                        $error_handler,
                        $field,
                        $message,
                        array(
                            'code'       => isset( $definition['error_code'] ) ? $definition['error_code'] : $field,
                            'definition' => $definition,
                            'raw_value'  => $raw_value,
                        )
                    );
                }
            }

            $sanitized = self::apply_dependencies( $sanitized );

            if ( function_exists( 'apply_filters' ) ) {
                return apply_filters( 'my_articles_settings_sanitized', $sanitized, $input, $schema );
            }

            return $sanitized;
        }

        /**
         * Normalize a single field.
         *
         * @param string               $field          Field identifier.
         * @param array<string, mixed> $definition     Field definition from the schema.
         * @param mixed                $raw_value      Raw value submitted by the user.
         * @param bool                 $has_raw_value  Whether the field was present in the submission.
         *
         * @return array{value:mixed,valid:bool}
         */
        private static function sanitize_field( $field, array $definition, $raw_value, $has_raw_value ) {
            $type    = isset( $definition['type'] ) ? $definition['type'] : 'string';
            $default = isset( $definition['default'] ) ? $definition['default'] : '';

            $value = $default;
            $valid = true;

            switch ( $type ) {
                case 'enum':
                    $value = $has_raw_value ? (string) $raw_value : (string) $default;
                    $allowed = isset( $definition['allowed'] ) && is_array( $definition['allowed'] ) ? $definition['allowed'] : array();
                    if ( ! in_array( $value, $allowed, true ) ) {
                        $value = (string) $default;
                        $valid = false;
                    }
                    break;

                case 'int':
                    $value = $has_raw_value ? (int) $raw_value : (int) $default;
                    $min   = isset( $definition['min'] ) ? (int) $definition['min'] : null;
                    $max   = isset( $definition['max'] ) ? (int) $definition['max'] : null;

                    if ( null !== $min && $value < $min ) {
                        $value = $min;
                        $valid = false;
                    }

                    if ( null !== $max && $value > $max ) {
                        $value = $max;
                        $valid = false;
                    }
                    break;

                case 'bool':
                    if ( $has_raw_value ) {
                        $value = ! empty( $raw_value ) ? 1 : 0;
                    } else {
                        $absent = isset( $definition['absent_value'] ) ? $definition['absent_value'] : $default;
                        $value  = ! empty( $absent ) ? 1 : 0;
                    }
                    break;

                case 'color':
                    $value = (string) $default;
                    if ( $has_raw_value ) {
                        $sanitized_color = my_articles_sanitize_color( (string) $raw_value, '__invalid__' );
                        if ( '__invalid__' === $sanitized_color ) {
                            $valid = false;
                        } else {
                            $value = $sanitized_color;
                        }
                    }
                    break;

                case 'meta_query':
                    if ( $has_raw_value ) {
                        $value = self::sanitize_meta_queries( $raw_value );
                        if ( empty( $value ) && ! empty( $raw_value ) ) {
                            $valid = false;
                        }
                    } else {
                        $value = array();
                    }
                    break;

                case 'string':
                default:
                    $value = $has_raw_value ? (string) $raw_value : (string) $default;
                    if ( $has_raw_value ) {
                        if ( isset( $definition['sanitize_callback'] ) && is_callable( $definition['sanitize_callback'] ) ) {
                            $value = call_user_func( $definition['sanitize_callback'], $value );
                        } else {
                            if ( function_exists( 'sanitize_text_field' ) ) {
                                $value = sanitize_text_field( $value );
                            } else {
                                $value = is_string( $value ) ? trim( strip_tags( $value ) ) : $value;
                            }
                        }
                    }
                    break;
            }

            return array(
                'value' => $value,
                'valid' => $valid,
            );
        }

        /**
         * Build a translated error message for invalid fields.
         *
         * @param array<string, mixed> $definition Field definition from the schema.
         *
         * @return string
         */
        private static function build_error_message( array $definition ) {
            if ( isset( $definition['error_message'] ) && is_string( $definition['error_message'] ) ) {
                return $definition['error_message'];
            }

            $label = isset( $definition['label'] ) ? $definition['label'] : '';

            $template = self::translate( 'La valeur fournie pour « %s » est invalide. Une valeur par défaut a été appliquée.' );

            if ( '' === $label ) {
                return $template;
            }

            return sprintf( $template, $label );
        }

        /**
         * Apply dependencies between fields after sanitization.
         *
         * @param array<string, mixed> $sanitized Current sanitized values.
         *
         * @return array<string, mixed>
         */
        public static function sanitize_meta_queries( $raw_meta_queries ) {
            if ( is_string( $raw_meta_queries ) ) {
                $decoded = json_decode( $raw_meta_queries, true );
                if ( is_array( $decoded ) ) {
                    $raw_meta_queries = $decoded;
                } else {
                    return array();
                }
            }

            if ( ! is_array( $raw_meta_queries ) ) {
                return array();
            }

            $relation = 'AND';
            if ( isset( $raw_meta_queries['relation'] ) && is_string( $raw_meta_queries['relation'] ) ) {
                $candidate_relation = strtoupper( $raw_meta_queries['relation'] );
                if ( in_array( $candidate_relation, array( 'AND', 'OR' ), true ) ) {
                    $relation = $candidate_relation;
                }
            }

            $clauses_input = array();

            if ( isset( $raw_meta_queries['clauses'] ) ) {
                $clauses_input = $raw_meta_queries['clauses'];
            } else {
                $clauses_input = $raw_meta_queries;
            }

            if ( is_string( $clauses_input ) ) {
                $decoded_clauses = json_decode( $clauses_input, true );
                if ( is_array( $decoded_clauses ) ) {
                    $clauses_input = $decoded_clauses;
                } else {
                    return array();
                }
            }

            if ( ! is_array( $clauses_input ) ) {
                return array();
            }

            $normalized = array();
            foreach ( $clauses_input as $clause ) {
                if ( is_string( $clause ) ) {
                    $decoded_clause = json_decode( $clause, true );
                    if ( is_array( $decoded_clause ) ) {
                        $clause = $decoded_clause;
                    }
                }

                if ( ! is_array( $clause ) ) {
                    continue;
                }

                $key = isset( $clause['key'] ) ? (string) $clause['key'] : '';
                if ( function_exists( 'sanitize_text_field' ) ) {
                    $key = sanitize_text_field( $key );
                } else {
                    $key = is_string( $key ) ? trim( strip_tags( $key ) ) : '';
                }

                if ( '' === $key ) {
                    continue;
                }

                $normalized_clause = array(
                    'key' => $key,
                );

                if ( array_key_exists( 'value', $clause ) ) {
                    $value = self::sanitize_meta_query_value( $clause['value'] );
                    if ( null !== $value ) {
                        $normalized_clause['value'] = $value;
                    }
                }

                if ( isset( $clause['compare'] ) && is_string( $clause['compare'] ) ) {
                    $compare = strtoupper( $clause['compare'] );
                    $allowed_compares = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS', 'REGEXP', 'NOT REGEXP', 'RLIKE' );
                    if ( in_array( $compare, $allowed_compares, true ) ) {
                        $normalized_clause['compare'] = $compare;
                    }
                }

                if ( isset( $clause['type'] ) && is_string( $clause['type'] ) ) {
                    $type = strtoupper( $clause['type'] );
                    $allowed_types = array( 'NUMERIC', 'BINARY', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'UNSIGNED', 'TIME' );
                    if ( in_array( $type, $allowed_types, true ) ) {
                        $normalized_clause['type'] = $type;
                    }
                }

                $normalized[] = $normalized_clause;
            }

            if ( empty( $normalized ) ) {
                return array();
            }

            return array_merge( array( 'relation' => $relation ), $normalized );
        }

        private static function sanitize_meta_query_value( $value ) {
            if ( is_array( $value ) ) {
                $sanitized = array();
                foreach ( $value as $entry ) {
                    $normalized = self::sanitize_meta_query_value( $entry );
                    if ( null !== $normalized ) {
                        $sanitized[] = $normalized;
                    }
                }

                return empty( $sanitized ) ? null : $sanitized;
            }

            if ( is_bool( $value ) ) {
                return $value ? 1 : 0;
            }

            if ( is_int( $value ) || is_float( $value ) ) {
                return $value + 0;
            }

            if ( null === $value ) {
                return null;
            }

            if ( is_scalar( $value ) ) {
                $text = (string) $value;
                if ( function_exists( 'sanitize_text_field' ) ) {
                    $text = sanitize_text_field( $text );
                } else {
                    $text = trim( strip_tags( $text ) );
                }

                if ( '' === $text ) {
                    return null;
                }

                return $text;
            }

            return null;
        }

        private static function apply_dependencies( array $sanitized ) {
            if ( isset( $sanitized['pagination_mode'], $sanitized['load_more_auto'] ) && 'load_more' !== $sanitized['pagination_mode'] ) {
                $sanitized['load_more_auto'] = 0;
            }

            if ( isset( $sanitized['meta_query'] ) ) {
                if ( empty( $sanitized['meta_query'] ) ) {
                    $sanitized['meta_query'] = array();
                    $sanitized['meta_query_relation'] = 'AND';
                } else {
                    $sanitized['meta_query_relation'] = isset( $sanitized['meta_query']['relation'] ) ? $sanitized['meta_query']['relation'] : 'AND';
                }
            }

            return $sanitized;
        }

        /**
         * Provide a translated string while remaining test friendly.
         *
         * @param string $text   Text to translate.
         * @param string $domain Optional text domain.
         *
         * @return string
         */
        private static function translate( $text, $domain = 'mon-articles' ) {
            if ( function_exists( '__' ) ) {
                return __( $text, $domain );
            }

            return $text;
        }

        /**
         * Retrieve allowed thumbnail aspect ratios without hard dependency on the shortcode class.
         *
         * @return array<int, string>
         */
        private static function get_allowed_thumbnail_aspect_ratios() {
            if ( class_exists( 'My_Articles_Shortcode' ) && method_exists( 'My_Articles_Shortcode', 'get_allowed_thumbnail_aspect_ratios' ) ) {
                return My_Articles_Shortcode::get_allowed_thumbnail_aspect_ratios();
            }

            return array( '1', '4/3', '3/2', '16/9' );
        }

        /**
         * Retrieve the default thumbnail aspect ratio.
         *
         * @return string
         */
        private static function get_default_thumbnail_aspect_ratio() {
            if ( class_exists( 'My_Articles_Shortcode' ) && method_exists( 'My_Articles_Shortcode', 'get_default_thumbnail_aspect_ratio' ) ) {
                return My_Articles_Shortcode::get_default_thumbnail_aspect_ratio();
            }

            return '16/9';
        }
    }
}
