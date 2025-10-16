<?php
/**
 * Response renderer responsible for loading template partials.
 */

if ( ! class_exists( 'My_Articles_Response_Renderer' ) ) {
    class My_Articles_Response_Renderer {
        /**
         * @var string
         */
        private $parts_path;

        public function __construct( $parts_path = '' ) {
            if ( '' === $parts_path ) {
                $parts_path = rtrim( dirname( __DIR__ ), '/\\' ) . '/templates/parts/';
            }

            if ( function_exists( 'trailingslashit' ) ) {
                $parts_path = trailingslashit( $parts_path );
            } else {
                $parts_path = rtrim( $parts_path, '/\\' ) . '/';
            }

            $this->parts_path = $parts_path;
        }

        /**
         * Renders the article item template.
         *
         * @param My_Articles_Shortcode $shortcode_instance Shortcode instance responsible for rendering.
         * @param array                 $options            Rendering options.
         * @param bool                  $is_pinned          Whether the current article is pinned.
         * @param bool                  $wrap_slides        Whether to wrap the item in a Swiper slide.
         *
         * @return string
         */
        public function render_article_item( $shortcode_instance, array $options, $is_pinned, $wrap_slides ) {
            return $this->render_template(
                'article-item',
                array(
                    'shortcode'   => $shortcode_instance,
                    'options'     => $options,
                    'is_pinned'   => $is_pinned,
                    'wrap_slides' => (bool) $wrap_slides,
                )
            );
        }

        /**
         * Renders the empty state template.
         *
         * @param My_Articles_Shortcode $shortcode_instance Shortcode instance responsible for rendering.
         * @param array                 $options            Rendering options.
         * @param bool                  $wrap_slides        Whether to render the empty state inside a slide.
         *
         * @return string
         */
        public function render_empty_state( $shortcode_instance, array $options, $wrap_slides ) {
            return $this->render_template(
                'empty-state',
                array(
                    'shortcode'   => $shortcode_instance,
                    'options'     => $options,
                    'wrap_slides' => (bool) $wrap_slides,
                )
            );
        }

        /**
         * Renders the skeleton placeholder when supported.
         *
         * @param My_Articles_Shortcode $shortcode_instance Shortcode instance responsible for rendering.
         * @param array                 $options            Rendering options.
         * @param int                   $render_limit       Number of articles requested for rendering.
         *
         * @return string
         */
        public function render_skeleton_placeholder( $shortcode_instance, array $options, $render_limit ) {
            if ( ! is_object( $shortcode_instance ) || ! method_exists( $shortcode_instance, 'get_skeleton_placeholder_markup' ) ) {
                return '';
            }

            $display_mode = isset( $options['display_mode'] ) ? $options['display_mode'] : 'grid';

            if ( ! in_array( $display_mode, array( 'grid', 'list' ), true ) ) {
                return '';
            }

            $container_class = ( 'list' === $display_mode ) ? 'my-articles-list-content' : 'my-articles-grid-content';

            return (string) $shortcode_instance->get_skeleton_placeholder_markup( $container_class, $options, (int) $render_limit );
        }

        /**
         * Loads a template part and returns its markup.
         *
         * @param string               $template Template slug.
         * @param array<string, mixed> $context  Template context.
         *
         * @return string
         */
        private function render_template( $template, array $context ) {
            $template_path = $this->parts_path . $template . '.php';

            if ( ! file_exists( $template_path ) ) {
                return '';
            }

            ob_start();

            extract( $context, EXTR_SKIP );

            include $template_path;

            return (string) ob_get_clean();
        }
    }
}
