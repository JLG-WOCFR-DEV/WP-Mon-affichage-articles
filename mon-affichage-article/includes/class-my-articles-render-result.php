<?php
/**
 * Render result DTO for My Articles responses.
 */

if ( ! class_exists( 'My_Articles_Render_Result' ) ) {
    class My_Articles_Render_Result {
        /**
         * @var array<int, string>
         */
        private $fragments = array();

        /**
         * @var array{total:int,pinned:int,regular:int}
         */
        private $counts = array(
            'total'   => 0,
            'pinned'  => 0,
            'regular' => 0,
        );

        /**
         * @var array<int>
         */
        private $displayed_pinned_ids = array();

        /**
         * @var array
         */
        private $options;

        /**
         * @var array
         */
        private $args;

        /**
         * @var bool
         */
        private $wrap_slides;

        /**
         * @var array<string, mixed>
         */
        private $queries;

        /**
         * Constructor.
         *
         * @param array $options Shortcode options used for rendering.
         * @param array $args    Rendering arguments passed to the renderer.
         * @param bool  $wrap_slides Whether the response is wrapped for Swiper slides.
         * @param WP_Query|null $pinned_query  Source query for pinned posts.
         * @param WP_Query|null $regular_query Source query for regular posts.
         */
        public function __construct( array $options, array $args, $wrap_slides, $pinned_query, $regular_query ) {
            $this->options     = $options;
            $this->args        = $args;
            $this->wrap_slides = (bool) $wrap_slides;
            $this->queries     = array(
                'pinned'  => $pinned_query,
                'regular' => $regular_query,
            );
        }

        /**
         * Adds markup to the rendered fragments list.
         *
         * @param string $html HTML fragment to append.
         */
        public function append_fragment( $html ) {
            if ( '' === $html ) {
                return;
            }

            $this->fragments[] = (string) $html;
        }

        /**
         * Replaces the current HTML fragments with the provided markup.
         *
         * @param string $html HTML fragment to use as the only output.
         */
        public function replace_html( $html ) {
            $this->fragments = array( (string) $html );
        }

        /**
         * Returns the rendered HTML markup.
         *
         * @return string
         */
        public function get_html() {
            if ( empty( $this->fragments ) ) {
                return '';
            }

            return implode( '', $this->fragments );
        }

        /**
         * Records a pinned article rendering event.
         *
         * @param int  $post_id      Post identifier.
         * @param bool $track_pinned Whether pinned posts should be tracked.
         *
         * @return int Current total number of rendered posts.
         */
        public function record_pinned_article( $post_id, $track_pinned ) {
            $this->counts['total']++;
            $this->counts['pinned']++;

            if ( $track_pinned ) {
                $this->displayed_pinned_ids[] = (int) $post_id;
            }

            return $this->counts['total'];
        }

        /**
         * Records a regular article rendering event.
         *
         * @return int Current total number of rendered posts.
         */
        public function record_regular_article() {
            $this->counts['total']++;
            $this->counts['regular']++;

            return $this->counts['total'];
        }

        /**
         * Retrieves rendered counts.
         *
         * @return array{total:int,pinned:int,regular:int}
         */
        public function get_counts() {
            return $this->counts;
        }

        /**
         * Gets the total number of rendered posts.
         *
         * @return int
         */
        public function get_displayed_posts_count() {
            return $this->counts['total'];
        }

        /**
         * Gets the number of rendered pinned posts.
         *
         * @return int
         */
        public function get_pinned_rendered_count() {
            return $this->counts['pinned'];
        }

        /**
         * Gets the number of rendered regular posts.
         *
         * @return int
         */
        public function get_regular_rendered_count() {
            return $this->counts['regular'];
        }

        /**
         * Retrieves the IDs of displayed pinned posts.
         *
         * @return array<int>
         */
        public function get_displayed_pinned_ids() {
            return $this->displayed_pinned_ids;
        }

        /**
         * Provides the shortcode options used for rendering.
         *
         * @return array
         */
        public function get_options() {
            return $this->options;
        }

        /**
         * Provides the renderer arguments used for rendering.
         *
         * @return array
         */
        public function get_args() {
            return $this->args;
        }

        /**
         * Indicates whether the response wraps items for Swiper slides.
         *
         * @return bool
         */
        public function should_wrap_slides() {
            return $this->wrap_slides;
        }

        /**
         * Retrieves the queries used during rendering.
         *
         * @return array<string, mixed>
         */
        public function get_queries() {
            return $this->queries;
        }

        /**
         * Builds the article rendering context passed to hooks.
         *
         * @param int       $post_id    Displayed post identifier.
         * @param bool      $is_pinned  Whether the article comes from the pinned query.
         * @param string    $query_type Machine-readable query identifier.
         * @param int       $position   1-based position of the article in the rendered collection.
         * @param WP_Query  $query      Source query instance.
         *
         * @return array<string, mixed>
         */
        public function build_article_context( $post_id, $is_pinned, $query_type, $position, $query ) {
            return array(
                'post_id'     => (int) $post_id,
                'is_pinned'   => (bool) $is_pinned,
                'query_type'  => $query_type,
                'position'    => (int) $position,
                'counts'      => $this->get_counts(),
                'options'     => $this->get_options(),
                'args'        => $this->get_args(),
                'wrap_slides' => $this->should_wrap_slides(),
                'query'       => $query,
            );
        }

        /**
         * Provides the collection context shared with summary hooks.
         *
         * @return array<string, mixed>
         */
        public function get_collection_context() {
            return array(
                'options'               => $this->get_options(),
                'args'                  => $this->get_args(),
                'wrap_slides'           => $this->should_wrap_slides(),
                'displayed_posts_count' => $this->get_displayed_posts_count(),
                'pinned_rendered_count' => $this->get_pinned_rendered_count(),
                'regular_rendered_count'=> $this->get_regular_rendered_count(),
                'displayed_pinned_ids'  => $this->get_displayed_pinned_ids(),
                'queries'               => $this->get_queries(),
            );
        }
    }
}
