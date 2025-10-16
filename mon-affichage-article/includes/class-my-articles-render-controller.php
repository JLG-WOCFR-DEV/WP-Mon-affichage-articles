<?php
if ( ! defined( 'WPINC' ) ) {
    die;
}

class My_Articles_Render_Controller {

    /**
     * @var My_Articles_Shortcode
     */
    private $shortcode;

    /**
     * @var My_Articles_Enqueue
     */
    private $enqueue;

    public function __construct( My_Articles_Shortcode $shortcode, My_Articles_Enqueue $enqueue = null ) {
        $this->shortcode = $shortcode;
        $this->enqueue   = $enqueue instanceof My_Articles_Enqueue ? $enqueue : My_Articles_Enqueue::get_instance();
    }

    /**
     * Prepare the render state for a shortcode instance.
     *
     * @param int   $instance_id Instance identifier.
     * @param array $args        {
     *     Optional. Additional arguments.
     *
     *     @type array $overrides Instance overrides.
     * }
     *
     * @return array|WP_Error
     */
    public function prepare( $instance_id, array $args = array() ) {
        $defaults = array(
            'overrides' => array(),
        );

        $args      = wp_parse_args( $args, $defaults );
        $overrides = is_array( $args['overrides'] ) ? $args['overrides'] : array();

        if ( ! $instance_id || 'mon_affichage' !== get_post_type( $instance_id ) ) {
            return new WP_Error( 'my_articles_invalid_instance', __( 'Instance non valide.', 'mon-articles' ) );
        }

        $post_status      = get_post_status( $instance_id );
        $allowed_statuses = My_Articles_Shortcode::get_allowed_instance_statuses( $instance_id );

        if ( empty( $post_status ) || ! in_array( $post_status, $allowed_statuses, true ) ) {
            return new WP_Error( 'my_articles_invalid_status', __( 'Statut non autorisé.', 'mon-articles' ) );
        }

        $preparation = $this->shortcode->get_data_preparer()->prepare( $instance_id, $overrides );

        if ( is_wp_error( $preparation ) ) {
            return $preparation;
        }

        $options            = isset( $preparation['options'] ) ? $preparation['options'] : array();
        $requested_values   = isset( $preparation['requested'] ) && is_array( $preparation['requested'] )
            ? $preparation['requested']
            : array();
        $request_query_vars = isset( $preparation['request_query_vars'] ) && is_array( $preparation['request_query_vars'] )
            ? $preparation['request_query_vars']
            : array();

        $requested_category = isset( $requested_values['category'] ) ? $requested_values['category'] : '';
        $requested_search   = isset( $requested_values['search'] ) ? $requested_values['search'] : '';
        $requested_sort     = isset( $requested_values['sort'] ) ? $requested_values['sort'] : '';
        $requested_page     = isset( $requested_values['page'] ) ? (int) $requested_values['page'] : 1;

        $category_query_var = isset( $request_query_vars['category'] ) ? $request_query_vars['category'] : 'my_articles_cat_' . $instance_id;
        $search_query_var   = isset( $request_query_vars['search'] ) ? $request_query_vars['search'] : 'my_articles_search_' . $instance_id;
        $sort_query_var     = isset( $request_query_vars['sort'] ) ? $request_query_vars['sort'] : 'my_articles_sort_' . $instance_id;
        $paged_var          = isset( $request_query_vars['paged'] ) ? $request_query_vars['paged'] : 'paged_' . $instance_id;

        $available_categories = isset( $options['available_categories'] ) ? $options['available_categories'] : array();

        $resolved_aria_label = '';
        if ( isset( $options['aria_label'] ) && is_string( $options['aria_label'] ) ) {
            $resolved_aria_label = trim( $options['aria_label'] );
        }

        if ( '' === $resolved_aria_label ) {
            $fallback_label = trim( wp_strip_all_tags( get_the_title( $instance_id ) ) );

            if ( '' === $fallback_label ) {
                /* translators: %d: module (post) ID. */
                $fallback_label = sprintf( __( "Module d'articles %d", 'mon-articles' ), $instance_id );
            }

            $resolved_aria_label = $fallback_label;
        }

        $script_payloads = array();
        if ( isset( $preparation['script_data'] ) && is_array( $preparation['script_data'] ) ) {
            $script_payloads = $preparation['script_data'];
        }

        $script_handles = array();
        $style_handles  = array();
        $inline_scripts = array();

        if ( ! empty( $options['show_category_filter'] ) || ! empty( $options['enable_keyword_search'] ) ) {
            $script_handles[] = 'my-articles-filter';
        }

        if ( isset( $options['pagination_mode'] ) && 'load_more' === $options['pagination_mode'] ) {
            $script_handles[] = 'my-articles-load-more';
        }

        if ( isset( $options['pagination_mode'] ) && 'numbered' === $options['pagination_mode'] ) {
            $script_handles[] = 'my-articles-scroll-fix';
        }

        $style_handles[] = 'my-articles-styles';

        if ( in_array( $options['display_mode'], array( 'grid', 'list', 'slideshow' ), true ) ) {
            $script_handles[] = 'my-articles-responsive-layout';
        }

        $requires_lazyload = ! empty( $options['enable_lazy_load'] );
        if ( $requires_lazyload ) {
            $script_handles[] = 'lazysizes';
        }

        if ( isset( $options['display_mode'] ) && 'slideshow' === $options['display_mode'] ) {
            $swiper_assets = $this->shortcode->get_swiper_assets( $options, $instance_id );

            if ( ! empty( $swiper_assets['styles'] ) ) {
                $style_handles = array_merge( $style_handles, (array) $swiper_assets['styles'] );
            }

            if ( ! empty( $swiper_assets['scripts'] ) ) {
                $script_handles = array_merge( $script_handles, (array) $swiper_assets['scripts'] );
            }

            if ( ! empty( $swiper_assets['payloads'] ) ) {
                $script_payloads = array_merge( $script_payloads, (array) $swiper_assets['payloads'] );
            }
        }

        $script_handles = array_values( array_unique( $script_handles ) );
        $style_handles  = array_values( array_unique( $style_handles ) );

        if ( $requested_page < 1 ) {
            $requested_page = 1;
        }

        $paged = $requested_page;

        $all_excluded_ids = isset( $options['all_excluded_ids'] ) ? (array) $options['all_excluded_ids'] : array();

        $state = $this->shortcode->build_display_state(
            $options,
            array(
                'paged'                   => $paged,
                'pagination_strategy'     => 'page',
                'enforce_unlimited_batch' => ( ! empty( $options['is_unlimited'] ) && 'slideshow' !== $options['display_mode'] ),
            )
        );

        $pinned_query          = isset( $state['pinned_query'] ) ? $state['pinned_query'] : null;
        $articles_query        = isset( $state['regular_query'] ) ? $state['regular_query'] : null;
        $total_matching_pinned = isset( $state['total_pinned_posts'] ) ? (int) $state['total_pinned_posts'] : 0;
        $total_regular_posts   = isset( $state['total_regular_posts'] ) ? (int) $state['total_regular_posts'] : 0;

        $initial_total_results = max( 0, $total_matching_pinned ) + max( 0, $total_regular_posts );

        $search_suggestions = $this->shortcode->get_search_suggestions( $options, $available_categories, $pinned_query, $articles_query );
        $result_count_label = $this->shortcode->get_result_count_label( $initial_total_results );

        $first_page_projected_pinned = $total_matching_pinned;
        $should_limit_display        = isset( $state['should_limit_display'] ) ? (bool) $state['should_limit_display'] : false;
        $render_limit                = isset( $state['render_limit'] ) ? (int) $state['render_limit'] : 0;
        $regular_posts_needed        = isset( $state['regular_posts_needed'] ) ? (int) $state['regular_posts_needed'] : 0;
        $is_unlimited                = ! empty( $state['is_unlimited'] );
        $effective_posts_per_page    = isset( $state['effective_posts_per_page'] ) ? (int) $state['effective_posts_per_page'] : 0;

        if ( 'slideshow' === ( $options['display_mode'] ?? '' ) ) {
            $should_limit_display = false;
        }

        $inline_styles = $this->shortcode->get_inline_styles_declaration( $options, $instance_id );

        $wrapper_class = 'my-articles-wrapper my-articles-' . esc_attr( $options['display_mode'] ?? 'grid' );

        if ( ! empty( $options['hover_lift_desktop'] ) ) {
            $wrapper_class .= ' my-articles-has-hover-lift';
        }

        if ( ! empty( $options['hover_neon_pulse'] ) ) {
            $wrapper_class .= ' my-articles-has-neon-pulse';
        }

        $columns_mobile    = max( 1, (int) ( $options['columns_mobile'] ?? 1 ) );
        $columns_tablet    = max( 1, (int) ( $options['columns_tablet'] ?? 1 ) );
        $columns_desktop   = max( 1, (int) ( $options['columns_desktop'] ?? 1 ) );
        $columns_ultrawide = max( 1, (int) ( $options['columns_ultrawide'] ?? 1 ) );
        $min_card_width    = max( 1, (int) ( $options['min_card_width'] ?? 220 ) );

        $active_filters_json = wp_json_encode( $options['active_tax_filters'] ?? array() );
        if ( false === $active_filters_json ) {
            $active_filters_json = '[]';
        }

        $results_region_id = 'my-articles-results-' . $instance_id;

        $wrapper_attributes = array(
            'id'                   => 'my-articles-wrapper-' . $instance_id,
            'class'                => $wrapper_class,
            'data-instance-id'     => $instance_id,
            'data-cols-mobile'     => $columns_mobile,
            'data-cols-tablet'     => $columns_tablet,
            'data-cols-desktop'    => $columns_desktop,
            'data-cols-ultrawide'  => $columns_ultrawide,
            'data-min-card-width'  => $min_card_width,
            'data-search-enabled'  => ! empty( $options['enable_keyword_search'] ) ? 'true' : 'false',
            'data-search-query'    => $options['search_query'] ?? '',
            'data-search-param'    => $search_query_var,
            'data-sort'            => $options['sort'] ?? '',
            'data-sort-param'      => $sort_query_var,
            'data-filters'         => $active_filters_json,
            'data-total-results'   => $initial_total_results,
            'role'                 => 'region',
            'aria-live'            => 'polite',
            'aria-label'           => $resolved_aria_label,
            'aria-busy'            => 'false',
            'data-results-target'  => $results_region_id,
        );

        if ( '' !== $inline_styles ) {
            $wrapper_attributes['style'] = $inline_styles;
        }

        $wrapper_attribute_string = $this->stringify_attributes( $wrapper_attributes );

        $search_form_html = $this->build_search_form_html(
            $instance_id,
            $options,
            $search_query_var,
            $initial_total_results,
            $result_count_label,
            $search_suggestions
        );

        $resolved_taxonomy   = isset( $options['resolved_taxonomy'] ) ? $options['resolved_taxonomy'] : My_Articles_Shortcode::resolve_taxonomy( $options );
        $filter_nav_fragment = $this->build_filter_nav_html(
            $instance_id,
            $options,
            $resolved_taxonomy,
            $available_categories,
            $results_region_id
        );

        $active_tab_id = $filter_nav_fragment['active_tab_id'];
        $filter_nav_html = $filter_nav_fragment['html'];

        $results_fragment = $this->build_results_html(
            $instance_id,
            $options,
            $state,
            $results_region_id,
            $render_limit,
            $effective_posts_per_page,
            $is_unlimited,
            $active_tab_id,
            $pinned_query,
            $articles_query
        );

        $results_html         = $results_fragment['html'];
        $displayed_pinned_ids = $results_fragment['displayed_pinned_ids'];

        if ( 1 === $paged ) {
            $first_page_projected_pinned = count( $displayed_pinned_ids );
            if ( 0 === $total_matching_pinned && ! empty( $displayed_pinned_ids ) ) {
                $total_matching_pinned = count( $displayed_pinned_ids );
            }
        }

        if ( in_array( $options['display_mode'], array( 'grid', 'list' ), true ) ) {
            if ( 0 === $total_regular_posts && ! ( $articles_query instanceof WP_Query ) ) {
                $count_query_args = array(
                    'post_type'          => $options['post_type'] ?? 'post',
                    'post_status'        => 'publish',
                    'posts_per_page'     => 1,
                    'post__not_in'       => $all_excluded_ids,
                    'ignore_sticky_posts'=> (int) ( $options['ignore_native_sticky'] ?? 0 ),
                    'fields'             => 'ids',
                );

                if ( '' !== ( $options['search_query'] ?? '' ) ) {
                    $count_query_args['s'] = $options['search_query'];
                }

                if ( ! empty( $options['meta_query'] ) && is_array( $options['meta_query'] ) ) {
                    $count_query_args = My_Articles_Shortcode::merge_meta_query_clauses( $count_query_args, $options['meta_query'] );
                }

                if ( '' !== $resolved_taxonomy && '' !== ( $options['term'] ?? '' ) && 'all' !== ( $options['term'] ?? '' ) ) {
                    $count_query_args['tax_query'] = array(
                        array(
                            'taxonomy' => $resolved_taxonomy,
                            'field'    => 'slug',
                            'terms'    => $options['term'],
                        ),
                    );
                }

                $count_query = new WP_Query( $count_query_args );
                $total_regular_posts = (int) $count_query->found_posts;
            }
        }

        $pagination_html = $this->build_pagination_html(
            $options,
            array(
                'instance_id'          => $instance_id,
                'total_matching_pinned' => $total_matching_pinned,
                'total_regular_posts'   => $total_regular_posts,
                'effective_per_page'    => $effective_posts_per_page,
                'paged'                 => $paged,
                'state'                 => $state,
                'displayed_pinned_ids'  => $displayed_pinned_ids,
                'active_filters_json'   => $active_filters_json,
                'options_term'          => $options['term'] ?? '',
                'search_query'          => $options['search_query'] ?? '',
                'sort'                  => $options['sort'] ?? '',
                'load_more_auto'        => ! empty( $options['load_more_auto'] ),
                'category_query_var'    => $category_query_var,
                'paged_var'             => $paged_var,
            )
        );

        if ( ! empty( $pagination_html['scripts'] ) ) {
            $script_handles = array_merge( $script_handles, $pagination_html['scripts'] );
        }

        $pagination_markup = $pagination_html['html'];

        if ( ! empty( $pagination_html['inline_scripts'] ) ) {
            $inline_scripts = array_merge( $inline_scripts, $pagination_html['inline_scripts'] );
        }

        $summary_metrics = array(
            'total_results'          => (int) $initial_total_results,
            'total_pinned_available' => (int) $total_matching_pinned,
            'rendered_pinned'        => count( (array) ( $state['rendered_pinned_ids'] ?? array() ) ),
            'total_regular'          => (int) $total_regular_posts,
            'per_page'               => (int) $effective_posts_per_page,
            'render_limit'           => (int) $render_limit,
            'is_unlimited'           => (bool) $is_unlimited,
            'should_limit_display'   => (bool) $should_limit_display,
            'unlimited_batch_size'   => (int) ( $state['unlimited_batch_size'] ?? 0 ),
            'regular_posts_needed'   => (int) $regular_posts_needed,
            'filters_available'      => is_array( $available_categories ) ? count( $available_categories ) : 0,
            'active_filters'         => isset( $options['active_tax_filters'] ) && is_array( $options['active_tax_filters'] )
                ? count( $options['active_tax_filters'] )
                : 0,
            'current_page'           => (int) $paged,
        );

        $summary_options = array(
            'display_mode'          => sanitize_key( $options['display_mode'] ?? 'grid' ),
            'pagination_mode'       => sanitize_key( $options['pagination_mode'] ?? 'none' ),
            'load_more_auto'        => ! empty( $options['load_more_auto'] ),
            'show_category_filter'  => ! empty( $options['show_category_filter'] ),
            'enable_keyword_search' => ! empty( $options['enable_keyword_search'] ),
        );

        $summary = array(
            'instance_id' => $instance_id,
            'metrics'     => $summary_metrics,
            'options'     => $summary_options,
        );

        $debug_fragment = $this->build_debug_html( $instance_id, $options );
        if ( ! empty( $debug_fragment['html'] ) ) {
            $script_handles[] = 'my-articles-debug-helper';
            $inline_scripts[] = $debug_fragment['inline_script'];
        }

        wp_reset_postdata();

        return array(
            'summary'  => $summary,
            'assets'   => array(
                'scripts'        => array_values( array_unique( $script_handles ) ),
                'styles'         => array_values( array_unique( $style_handles ) ),
                'script_payloads'=> $script_payloads,
                'inline_scripts' => $inline_scripts,
                'requires_lazyload' => $requires_lazyload,
            ),
            'context'  => array(
                'wrapper_attribute_string' => $wrapper_attribute_string,
                'search_form_html'         => $search_form_html,
                'filter_nav_html'          => $filter_nav_html,
                'results_html'             => $results_html,
                'pagination_html'          => $pagination_markup,
                'debug_html'               => $debug_fragment['html'],
            ),
        );
    }

    /**
     * Convert an associative array of attributes to a HTML attribute string.
     *
     * @param array $attributes Attributes array.
     * @return string
     */
    private function stringify_attributes( array $attributes ) {
        $strings = array();

        foreach ( $attributes as $attribute => $value ) {
            $strings[] = sprintf( '%s="%s"', esc_attr( $attribute ), esc_attr( (string) $value ) );
        }

        return implode( ' ', $strings );
    }

    private function build_search_form_html( $instance_id, array $options, $search_query_var, $initial_total_results, $result_count_label, array $search_suggestions ) {
        if ( empty( $options['enable_keyword_search'] ) ) {
            return '';
        }

        $search_form_classes = 'my-articles-search-form';

        if ( '' !== ( $options['search_query'] ?? '' ) ) {
            $search_form_classes .= ' has-value';
        }

        $search_label       = __( 'Rechercher des articles', 'mon-articles' );
        $search_placeholder = __( 'Rechercher par mots-clés…', 'mon-articles' );
        $search_submit_text = __( 'Rechercher', 'mon-articles' );
        $search_clear_label = __( 'Effacer la recherche', 'mon-articles' );
        $search_input_id    = 'my-articles-search-input-' . $instance_id;
        $search_form_id     = 'my-articles-search-form-' . $instance_id;
        $search_count_id    = 'my-articles-search-count-' . $instance_id;
        $search_datalist_id = '';

        if ( ! empty( $search_suggestions ) ) {
            $search_datalist_id = 'my-articles-search-suggestions-' . $instance_id;
        }

        $icon_markup = $this->shortcode->get_search_icon_markup();

        ob_start();
        ?>
        <form id="<?php echo esc_attr( $search_form_id ); ?>" class="<?php echo esc_attr( $search_form_classes ); ?>" role="search" aria-label="<?php echo esc_attr( $search_label ); ?>" data-instance-id="<?php echo esc_attr( $instance_id ); ?>" data-search-param="<?php echo esc_attr( $search_query_var ); ?>" data-current-search="<?php echo esc_attr( $options['search_query'] ?? '' ); ?>">
            <div class="my-articles-search-inner">
                <label class="my-articles-search-label screen-reader-text" for="<?php echo esc_attr( $search_input_id ); ?>"><?php echo esc_html( $search_label ); ?></label>
                <div class="my-articles-search-controls">
                    <span class="my-articles-search-icon" aria-hidden="true"><?php echo $icon_markup; ?></span>
                    <input type="search" id="<?php echo esc_attr( $search_input_id ); ?>" class="my-articles-search-input" name="my-articles-search" value="<?php echo esc_attr( $options['search_query'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $search_placeholder ); ?>" autocomplete="off"<?php echo $search_datalist_id ? ' list="' . esc_attr( $search_datalist_id ) . '"' : ''; ?> aria-describedby="<?php echo esc_attr( $search_count_id ); ?>" />
                    <button type="submit" class="my-articles-search-submit"><span class="my-articles-search-submit-label"><?php echo esc_html( $search_submit_text ); ?></span><span class="my-articles-search-spinner" aria-hidden="true"></span></button>
                    <button type="button" class="my-articles-search-clear" aria-label="<?php echo esc_attr( $search_clear_label ); ?>"><span aria-hidden="true">&times;</span><span class="screen-reader-text"><?php echo esc_html( $search_clear_label ); ?></span></button>
                </div>
                <div class="my-articles-search-meta">
                    <output id="<?php echo esc_attr( $search_count_id ); ?>" class="my-articles-search-count" role="status" aria-live="polite" aria-atomic="true" data-count="<?php echo esc_attr( $initial_total_results ); ?>" for="<?php echo esc_attr( $search_input_id ); ?>"><?php echo esc_html( $result_count_label ); ?></output>
                </div>
                <?php if ( ! empty( $search_suggestions ) ) : ?>
                    <div class="my-articles-search-suggestions" role="list" aria-label="<?php echo esc_attr__( 'Suggestions de recherche', 'mon-articles' ); ?>">
                        <?php foreach ( $search_suggestions as $suggestion ) : ?>
                            <button type="button" class="my-articles-search-suggestion" role="listitem" data-suggestion="<?php echo esc_attr( $suggestion ); ?>"><span><?php echo esc_html( $suggestion ); ?></span></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ( $search_datalist_id ) : ?>
                <datalist id="<?php echo esc_attr( $search_datalist_id ); ?>">
                    <?php foreach ( $search_suggestions as $suggestion ) : ?>
                        <option value="<?php echo esc_attr( $suggestion ); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            <?php endif; ?>
        </form>
        <?php
        return trim( ob_get_clean() );
    }

    private function build_filter_nav_html( $instance_id, array $options, $resolved_taxonomy, $available_categories, $results_region_id ) {
        $active_tab_id = '';

        if ( empty( $options['show_category_filter'] ) || empty( $resolved_taxonomy ) || empty( $available_categories ) ) {
            return array(
                'html'          => '',
                'active_tab_id' => $active_tab_id,
            );
        }

        $alignment_class = 'filter-align-' . esc_attr( $options['filter_alignment'] ?? 'right' );
        $nav_attributes  = array(
            'class'      => 'my-articles-filter-nav ' . $alignment_class,
            'aria-label' => $options['category_filter_aria_label'] ?? '',
        );

        $tablist_id   = 'my-articles-tabs-' . $instance_id;
        $default_cat  = $options['term'] ?? '';
        $is_all_active = '' === $default_cat || 'all' === $default_cat;

        if ( $is_all_active ) {
            $active_tab_id = 'my-articles-tab-' . $instance_id . '-all';
        }

        ob_start();
        ?>
        <nav <?php echo $this->stringify_attributes( $nav_attributes ); ?>>
            <ul role="tablist" id="<?php echo esc_attr( $tablist_id ); ?>">
                <li class="<?php echo $is_all_active ? 'active' : ''; ?>" role="presentation">
                    <button type="button" role="tab" id="<?php echo esc_attr( 'my-articles-tab-' . $instance_id . '-all' ); ?>" data-category="all" aria-controls="<?php echo esc_attr( $results_region_id ); ?>" aria-selected="<?php echo $is_all_active ? 'true' : 'false'; ?>" tabindex="<?php echo $is_all_active ? '0' : '-1'; ?>"><?php esc_html_e( 'Tout', 'mon-articles' ); ?></button>
                </li>
                <?php foreach ( $available_categories as $category ) :
                    $is_active = ( $default_cat === $category->slug );
                    $tab_id    = 'my-articles-tab-' . $instance_id . '-' . $category->slug;
                    if ( $is_active ) {
                        $active_tab_id = $tab_id;
                    }
                    ?>
                    <li class="<?php echo $is_active ? 'active' : ''; ?>" role="presentation">
                        <button type="button" role="tab" id="<?php echo esc_attr( $tab_id ); ?>" data-category="<?php echo esc_attr( $category->slug ); ?>" aria-controls="<?php echo esc_attr( $results_region_id ); ?>" aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>" tabindex="<?php echo $is_active ? '0' : '-1'; ?>"><?php echo esc_html( $category->name ); ?></button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php

        return array(
            'html'          => trim( ob_get_clean() ),
            'active_tab_id' => $active_tab_id,
        );
    }

    private function build_results_html( $instance_id, array $options, array $state, $results_region_id, $render_limit, $effective_posts_per_page, $is_unlimited, $active_tab_id, $pinned_query, $articles_query ) {
        $adapter_items = My_Articles_Shortcode::collect_content_adapter_items(
            $options,
            array(
                'instance_id'         => $instance_id,
                'render_limit'        => $render_limit,
                'display_mode'        => $options['display_mode'] ?? '',
                'rendered_pinned_ids' => $state['rendered_pinned_ids'] ?? array(),
            )
        );

        $posts_per_page_for_render    = $render_limit > 0 ? $render_limit : $effective_posts_per_page;
        $posts_per_page_for_slideshow = $effective_posts_per_page;

        if ( $is_unlimited && 0 === $posts_per_page_for_render ) {
            $posts_per_page_for_render = 0;
        }

        if ( $is_unlimited && 0 === $posts_per_page_for_slideshow ) {
            $posts_per_page_for_slideshow = 0;
        }

        $results_attributes = array(
            'id'                    => $results_region_id,
            'class'                 => 'my-articles-results',
            'role'                  => 'tabpanel',
            'data-my-articles-role' => 'results',
            'aria-live'             => 'polite',
            'aria-busy'             => 'false',
        );

        if ( ! empty( $active_tab_id ) ) {
            $results_attributes['aria-labelledby'] = $active_tab_id;
        }

        $displayed_pinned_ids = array();

        ob_start();
        ?>
        <div <?php echo $this->stringify_attributes( $results_attributes ); ?>>
            <?php
            if ( 'slideshow' === ( $options['display_mode'] ?? '' ) ) {
                echo $this->shortcode->get_slideshow_fragment( $pinned_query, $articles_query, $options, $posts_per_page_for_slideshow, $results_region_id, $adapter_items, $instance_id );
            } elseif ( 'list' === ( $options['display_mode'] ?? '' ) ) {
                $list_fragment       = $this->shortcode->get_list_fragment( $pinned_query, $articles_query, $options, $posts_per_page_for_render, $results_region_id, $adapter_items );
                $displayed_pinned_ids = $list_fragment['displayed_pinned_ids'];
                echo $list_fragment['html'];
            } else {
                $grid_fragment       = $this->shortcode->get_grid_fragment( $pinned_query, $articles_query, $options, $posts_per_page_for_render, $results_region_id, $adapter_items );
                $displayed_pinned_ids = $grid_fragment['displayed_pinned_ids'];
                echo $grid_fragment['html'];
            }
            ?>
        </div>
        <?php

        return array(
            'html'                 => trim( ob_get_clean() ),
            'displayed_pinned_ids' => $displayed_pinned_ids,
        );
    }

    private function build_pagination_html( array $options, array $context ) {
        $scripts        = array();
        $inline_scripts = array();

        $html = '';

        if ( ! in_array( $options['display_mode'], array( 'grid', 'list' ), true ) ) {
            return array(
                'html'            => $html,
                'scripts'         => $scripts,
                'inline_scripts'  => $inline_scripts,
            );
        }

        $total_matching_pinned = (int) ( $context['total_matching_pinned'] ?? 0 );
        $total_regular_posts   = (int) ( $context['total_regular_posts'] ?? 0 );
        $effective_per_page    = (int) ( $context['effective_per_page'] ?? 0 );
        $paged                 = (int) ( $context['paged'] ?? 1 );
        $state                 = $context['state'] ?? array();
        $displayed_pinned_ids  = isset( $context['displayed_pinned_ids'] ) && is_array( $context['displayed_pinned_ids'] )
            ? array_map( 'absint', $context['displayed_pinned_ids'] )
            : array();

        $pagination_context = array(
            'current_page' => $paged,
        );

        if ( ! empty( $state['is_unlimited'] ) ) {
            $pagination_context['unlimited_page_size'] = $state['unlimited_batch_size'] ?? 0;
            $pagination_context['analytics_page_size'] = $state['unlimited_batch_size'] ?? 0;
        }

        $pagination_totals = my_articles_calculate_total_pages(
            $total_matching_pinned,
            $total_regular_posts,
            $effective_per_page,
            $pagination_context
        );
        $total_pages = (int) ( $pagination_totals['total_pages'] ?? 0 );

        if ( 'load_more' === ( $options['pagination_mode'] ?? '' ) ) {
            if ( $total_pages > 1 && $paged < $total_pages ) {
                $scripts[] = 'my-articles-load-more';

                $next_page           = min( $paged + 1, $total_pages );
                $load_more_pinned_ids = ! empty( $displayed_pinned_ids ) ? array_map( 'absint', $displayed_pinned_ids ) : array();

                ob_start();
                ?>
                <div class="my-articles-load-more-container">
                    <button class="my-articles-load-more-btn" data-instance-id="<?php echo esc_attr( $context['instance_id'] ?? 0 ); ?>" data-paged="<?php echo esc_attr( $next_page ); ?>" data-total-pages="<?php echo esc_attr( $total_pages ); ?>" data-pinned-ids="<?php echo esc_attr( implode( ',', $load_more_pinned_ids ) ); ?>" data-category="<?php echo esc_attr( $options['term'] ?? '' ); ?>" data-search="<?php echo esc_attr( $options['search_query'] ?? '' ); ?>" data-sort="<?php echo esc_attr( $options['sort'] ?? '' ); ?>" data-filters="<?php echo esc_attr( $context['active_filters_json'] ?? '[]' ); ?>" data-auto-load="<?php echo esc_attr( ! empty( $options['load_more_auto'] ) ? '1' : '0' ); ?>"><?php esc_html_e( 'Charger plus', 'mon-articles' ); ?></button>
                </div>
                <?php
                $html = trim( ob_get_clean() );
            }
        } elseif ( 'numbered' === ( $options['pagination_mode'] ?? '' ) ) {
            $query_args = array();
            if ( '' !== ( $options['term'] ?? '' ) ) {
                $query_args[ $context['category_query_var'] ?? '' ] = $options['term'];
            }

            $html = $this->shortcode->get_numbered_pagination_fragment( $total_pages, $paged, $context['paged_var'] ?? 'paged_' . uniqid(), $query_args );
        }

        return array(
            'html'           => $html,
            'scripts'        => array_values( array_unique( $scripts ) ),
            'inline_scripts' => $inline_scripts,
        );
    }

    private function build_debug_html( $instance_id, array $options ) {
        if ( empty( $options['enable_debug_mode'] ) ) {
            return array(
                'html'          => '',
                'inline_script' => array(),
            );
        }

        ob_start();
        ?>
        <div style="background: #fff; border: 2px solid red; padding: 15px; margin: 20px 0; text-align: left; color: #000; font-family: monospace; line-height: 1.6; clear: both;">
            <h4 style="margin: 0 0 10px 0;">-- DEBUG MODE --</h4>
            <ul>
                <li>Réglage "Lazy Load" activé : <strong><?php echo ! empty( $options['enable_lazy_load'] ) ? 'Oui' : 'Non'; ?></strong></li>
                <li>Statut du script lazysizes : <strong id="lazysizes-status-<?php echo esc_attr( $instance_id ); ?>" style="color: red;">En attente...</strong></li>
            </ul>
        </div>
        <?php
        $html = trim( ob_get_clean() );

        $status_span_id = 'lazysizes-status-' . $instance_id;
        $debug_script   = sprintf(
            "document.addEventListener('DOMContentLoaded',function(){var statusSpan=document.getElementById(%s);if(!statusSpan){return;}setTimeout(function(){if(window.lazySizes){statusSpan.textContent=%s;statusSpan.style.color='green';}else{statusSpan.textContent=%s;}},500);});",
            wp_json_encode( $status_span_id ),
            wp_json_encode( '✅ Chargé et actif !' ),
            wp_json_encode( '❌ ERREUR : Non trouvé !' )
        );

        return array(
            'html'          => $html,
            'inline_script' => array(
                'handle'   => 'my-articles-debug-helper',
                'code'     => $debug_script,
                'position' => 'after',
            ),
        );
    }
}
