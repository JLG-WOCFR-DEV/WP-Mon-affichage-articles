<?php
// Fichier: includes/class-my-articles-shortcode.php

if ( ! defined( 'WPINC' ) ) {
    die;
}

class My_Articles_Shortcode {

    private static $instance;
    private static $lazysizes_enqueued = false;

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'mon_affichage_articles', array( $this, 'render_shortcode' ) );
    }

    public static function get_default_options() {
        return [
            'post_type' => 'post',
            'taxonomy' => '',
            'term' => '',
            'counting_behavior' => 'exact',
            'posts_per_page' => 10,
            'pagination_mode' => 'none',
            'show_category_filter' => 0,
            'filter_alignment' => 'right',
            'filter_categories' => array(),
            'pinned_posts' => array(),
            'pinned_border_color' => '#eab308',
            'pinned_posts_ignore_filter' => 0,
            'pinned_show_badge' => 0,
            'pinned_badge_text' => 'Épinglé',
            'pinned_badge_bg_color' => '#eab308',
            'pinned_badge_text_color' => '#ffffff',
            'exclude_posts' => '',
            'ignore_native_sticky' => 1,
            'enable_lazy_load' => 1,
            'enable_debug_mode' => 0,
            'display_mode' => 'grid',
            'columns_mobile' => 1, 'columns_tablet' => 2, 'columns_desktop' => 3, 'columns_ultrawide' => 4,
            'module_padding_left' => 0, 'module_padding_right' => 0,
            'gap_size' => 25, 'list_item_gap' => 25,
            'list_content_padding_top' => 0, 'list_content_padding_right' => 0,
            'list_content_padding_bottom' => 0, 'list_content_padding_left' => 0,
            'border_radius' => 12, 'title_font_size' => 16,
            'meta_font_size' => 12, 'show_category' => 1, 'show_author' => 1, 'show_date' => 1,
            'show_excerpt' => 0,
            'excerpt_length' => 25,
            'excerpt_more_text' => 'Lire la suite',
            'excerpt_font_size' => 14,
            'excerpt_color' => '#4b5563',
            'module_bg_color' => 'rgba(255,255,255,0)', 'vignette_bg_color' => '#ffffff',
            'title_wrapper_bg_color' => '#ffffff', 'title_color' => '#333333',
            'meta_color' => '#6b7280', 'meta_color_hover' => '#000000', 'pagination_color' => '#333333',
            'shadow_color' => 'rgba(0,0,0,0.07)', 'shadow_color_hover' => 'rgba(0,0,0,0.12)',
        ];
    }

    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( ['id' => 0], $atts, 'mon_affichage_articles' );
        $id = absint($atts['id']);

        if ( !$id || 'mon_affichage' !== get_post_type($id) ) {
            return '';
        }

        $options = (array) get_post_meta( $id, '_my_articles_settings', true );
        $defaults = self::get_default_options();
        $options = wp_parse_args($options, $defaults);

        $resolved_taxonomy = $this->resolve_taxonomy( $options );
        $options['resolved_taxonomy'] = $resolved_taxonomy;

        if ( !empty($options['show_category_filter']) ) {
            wp_enqueue_script('my-articles-filter', MY_ARTICLES_PLUGIN_URL . 'assets/js/filter.js', ['jquery'], MY_ARTICLES_VERSION, true);
            wp_localize_script(
                'my-articles-filter',
                'myArticlesFilter',
                [
                    'ajax_url'  => admin_url('admin-ajax.php'),
                    'nonce'     => wp_create_nonce('my_articles_filter_nonce'),
                    'errorText' => __( 'Erreur AJAX.', 'mon-articles' ),
                ]
            );
        }

        if ( $options['pagination_mode'] === 'load_more' ) {
            wp_enqueue_script('my-articles-load-more', MY_ARTICLES_PLUGIN_URL . 'assets/js/load-more.js', ['jquery'], MY_ARTICLES_VERSION, true);
            wp_localize_script(
                'my-articles-load-more',
                'myArticlesLoadMore',
                [
                    'ajax_url'     => admin_url('admin-ajax.php'),
                    'nonce'        => wp_create_nonce('my_articles_load_more_nonce'),
                    'loadingText'  => __( 'Chargement...', 'mon-articles' ),
                    'loadMoreText' => __( 'Charger plus', 'mon-articles' ),
                    'errorText'    => __( 'Erreur AJAX.', 'mon-articles' ),
                ]
            );
        }

        if ( $options['pagination_mode'] === 'numbered' ) {
            wp_enqueue_script('my-articles-scroll-fix', MY_ARTICLES_PLUGIN_URL . 'assets/js/scroll-fix.js', ['jquery'], MY_ARTICLES_VERSION, true);
        }

        if ( !empty($options['enable_lazy_load']) && !self::$lazysizes_enqueued ) {
            wp_enqueue_script('lazysizes');
            self::$lazysizes_enqueued = true;
        }

        $paged_var = 'paged_' . $id;
        $paged = isset($_GET[$paged_var]) ? absint( wp_unslash( $_GET[$paged_var] ) ) : 1;
        $posts_per_page = (int)($options['posts_per_page'] ?? 10);

        if ($options['counting_behavior'] === 'auto_fill' && ($options['display_mode'] === 'grid' || $options['display_mode'] === 'slideshow')) {
            $master_columns = (int)($options['columns_ultrawide'] ?? 4);
            if ($master_columns > 0) {
                $rows_needed = ceil($posts_per_page / $master_columns);
                $posts_per_page = $rows_needed * $master_columns;
            }
        }

        $pinned_ids = array();
        if ( ! empty( $options['pinned_posts'] ) && is_array( $options['pinned_posts'] ) ) {
            $pinned_ids = array_map( 'absint', $options['pinned_posts'] );
        }
        $exclude_ids = !empty($options['exclude_posts']) ? array_map('absint', explode(',', $options['exclude_posts'])) : array();
        $all_excluded_ids = array_unique(array_merge($pinned_ids, $exclude_ids));

        $pinned_query = null;
        $pinned_posts_found = 0;
        $effective_pinned_ids = array();
        if ( ! empty( $pinned_ids ) ) {
            $pinned_query_args = [
                'post_type'    => 'any',
                'post_status'  => 'publish',
                'post__in'     => $pinned_ids,
                'orderby'      => 'post__in',
                'post__not_in' => $exclude_ids,
            ];

            $default_term = isset( $options['term'] ) ? sanitize_text_field( $options['term'] ) : '';
            $taxonomy = $resolved_taxonomy;

            if ( empty( $options['pinned_posts_ignore_filter'] ) && '' !== $default_term && 'all' !== $default_term ) {
                if ( ! empty( $taxonomy ) ) {
                    $pinned_query_args['tax_query'] = [
                        [
                            'taxonomy' => $taxonomy,
                            'field'    => 'slug',
                            'terms'    => $default_term,
                        ],
                    ];
                } else {
                    $pinned_query_args['category_name'] = $default_term;
                }
            }

            if ( $paged === 1 ) {
                $pinned_query_args['posts_per_page'] = count( $pinned_ids );
                $pinned_query                        = new WP_Query( $pinned_query_args );
                $pinned_posts_found                  = (int) ( $pinned_query->found_posts ?? $pinned_query->post_count );

                if ( $pinned_query->have_posts() ) {
                    $effective_pinned_ids = wp_list_pluck( $pinned_query->posts, 'ID' );
                }
            } else {
                $count_query_args = $pinned_query_args;
                $count_query_args['posts_per_page'] = 1;
                $count_query_args['fields']         = 'ids';

                $count_query       = new WP_Query( $count_query_args );
                $pinned_posts_found = (int) $count_query->found_posts;
            }
        }

        $regular_posts_on_page_1 = max( 0, $posts_per_page - $pinned_posts_found );

        if ( $paged > 1 ) {
            $offset = $regular_posts_on_page_1 + ( max( 0, $paged - 2 ) * $posts_per_page );
            $posts_to_fetch = $posts_per_page;
        } else {
            $offset = 0;
            $posts_to_fetch = $regular_posts_on_page_1;
        }
        
        $articles_query = null;
        if ($posts_to_fetch > 0) {
            $regular_query_args = [
                'post_type' => $options['post_type'],
                'post_status' => 'publish',
                'posts_per_page' => $posts_to_fetch,
                'offset' => $offset,
                'post__not_in' => $all_excluded_ids,
                'ignore_sticky_posts' => (int)$options['ignore_native_sticky'],
            ];

            if ( '' !== $resolved_taxonomy && '' !== $options['term'] && 'all' !== $options['term'] ) {
                $regular_query_args['tax_query'] = [
                    [
                        'taxonomy' => $resolved_taxonomy,
                        'field'    => 'slug',
                        'terms'    => $options['term'],
                    ],
                ];
            }
            $articles_query = new WP_Query($regular_query_args);
        }
        
        if ($options['display_mode'] === 'slideshow') { $this->enqueue_swiper_scripts($options, $id); }

        wp_enqueue_style('my-articles-styles');

        ob_start();
        $this->render_inline_styles($options, $id);
        
        $wrapper_class = 'my-articles-wrapper my-articles-' . esc_attr($options['display_mode']);
        
        echo '<div id="my-articles-wrapper-' . esc_attr($id) . '" class="' . esc_attr($wrapper_class) . '" data-instance-id="' . esc_attr($id) . '">';

        if ( !empty($options['show_category_filter']) ) {
            if ( !empty( $resolved_taxonomy ) ) {
                $get_terms_args = [
                    'taxonomy'   => $resolved_taxonomy,
                    'hide_empty' => true,
                ];

                if ( !empty($options['filter_categories']) && is_array($options['filter_categories']) ) {
                    $get_terms_args['include'] = array_map('absint', $options['filter_categories']);
                    $get_terms_args['orderby'] = 'include';
                }

                $categories = get_terms( $get_terms_args );

                if (!is_wp_error($categories) && count($categories) > 0) {
                    $alignment_class = 'filter-align-' . esc_attr($options['filter_alignment']);
                    echo '<nav class="my-articles-filter-nav ' . $alignment_class . '"><ul>';
                    $default_cat = $options['term'] ?? '';
                    $is_all_active = '' === $default_cat || 'all' === $default_cat;
                    echo '<li class="' . ($is_all_active ? 'active' : '') . '"><a href="#" data-category="all">' . __('Tout', 'mon-articles') . '</a></li>';
                    foreach ($categories as $category) {
                        echo '<li class="' . ($default_cat === $category->slug ? 'active' : '') . '"><a href="#" data-category="' .esc_attr($category->slug) . '">' . esc_html($category->name) . '</a></li>';
                    }
                    echo '</ul></nav>';
                }
            }
        }
        if ($options['display_mode'] === 'slideshow') {
            $this->render_slideshow($pinned_query, $articles_query, $options, $posts_per_page);
        } else if ($options['display_mode'] === 'list') {
            $this->render_list($pinned_query, $articles_query, $options, $posts_per_page);
        } else {
            $this->render_grid($pinned_query, $articles_query, $options, $posts_per_page);
        }

        if ($options['display_mode'] === 'grid' || $options['display_mode'] === 'list') {
            $total_regular_posts = 0;

            if ($articles_query instanceof WP_Query) {
                $total_regular_posts = (int) $articles_query->found_posts;
            } else {
                $count_query_args = [
                    'post_type' => $options['post_type'],
                    'post_status' => 'publish',
                    'posts_per_page' => 1,
                    'post__not_in' => $all_excluded_ids,
                    'ignore_sticky_posts' => (int) $options['ignore_native_sticky'],
                    'fields' => 'ids',
                ];

                if ( '' !== $resolved_taxonomy && '' !== $options['term'] && 'all' !== $options['term'] ) {
                    $count_query_args['tax_query'] = [[
                        'taxonomy' => $resolved_taxonomy,
                        'field'    => 'slug',
                        'terms'    => $options['term'],
                    ]];
                }

                $count_query = new WP_Query($count_query_args);
                $total_regular_posts = (int) $count_query->found_posts;
            }

            $pagination_totals = my_articles_calculate_total_pages(
                $pinned_posts_found,
                $total_regular_posts,
                $posts_per_page
            );
            $total_pages = $pagination_totals['total_pages'];

            if ($options['pagination_mode'] === 'load_more') {
                if ( $total_pages > 1 && $paged < $total_pages) {
                    $load_more_pinned_ids = ! empty( $effective_pinned_ids ) ? array_map( 'absint', $effective_pinned_ids ) : array();
                    echo '<div class="my-articles-load-more-container"><button class="my-articles-load-more-btn" data-instance-id="' . esc_attr($id) . '" data-paged="2" data-total-pages="' . esc_attr($total_pages) . '" data-pinned-ids="' . esc_attr(implode(',', $load_more_pinned_ids)) . '" data-category="' . esc_attr($options['term']) . '">' . __('Charger plus', 'mon-articles') . '</button></div>';
                }
            } elseif ($options['pagination_mode'] === 'numbered') {
                $this->render_numbered_pagination($total_pages, $paged, $paged_var);
            }
        }
        
        if (!empty($options['enable_debug_mode'])) {
            echo '<div style="background: #fff; border: 2px solid red; padding: 15px; margin: 20px 0; text-align: left; color: #000; font-family: monospace; line-height: 1.6; clear: both;">';
            echo '<h4 style="margin: 0 0 10px 0;">-- DEBUG MODE --</h4>';
            echo '<ul>';
            echo '<li>Réglage "Lazy Load" activé : <strong>' . (!empty($options['enable_lazy_load']) ? 'Oui' : 'Non') . '</strong></li>';
            echo '<li>Statut du script lazysizes : <strong id="lazysizes-status-'.esc_attr($id).'" style="color: red;">En attente...</strong></li>';
            echo '</ul>';
            echo '</div>';
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    var statusSpan = document.getElementById('lazysizes-status-".esc_attr($id)."');
                    if (statusSpan) {
                        setTimeout(function() {
                            if (window.lazySizes) {
                                statusSpan.textContent = '✅ Chargé et actif !';
                                statusSpan.style.color = 'green';
                            } else {
                                statusSpan.textContent = '❌ ERREUR : Non trouvé !';
                            }
                        }, 500);
                    }
                });
            </script>";
        }
        
        echo '</div>';
        
        wp_reset_postdata();
        return ob_get_clean();
    }
    
    private function render_list($pinned_query, $regular_query, $options, $posts_per_page) {
        $has_rendered_posts = false;
        $render_limit = max(0, (int) $posts_per_page);
        $should_limit = $render_limit > 0;
        $rendered_count = 0;
        echo '<div class="my-articles-list-content">';
        if ( $pinned_query && $pinned_query->have_posts() ) {
            while ( $pinned_query->have_posts() && ( ! $should_limit || $rendered_count < $render_limit ) ) {
                $pinned_query->the_post();
                $this->render_article_item($options, true);
                $has_rendered_posts = true;
                $rendered_count++;
            }
        }
        if ( $regular_query && $regular_query->have_posts() ) {
            while ( $regular_query->have_posts() && ( ! $should_limit || $rendered_count < $render_limit ) ) {
                $regular_query->the_post();
                $this->render_article_item($options, false);
                $has_rendered_posts = true;
                $rendered_count++;
            }
        }
        echo '</div>';

        if ( !$has_rendered_posts ) {
            $this->render_empty_state_message();
        }
    }

    private function render_grid($pinned_query, $regular_query, $options, $posts_per_page) {
        $has_rendered_posts = false;
        $render_limit = max(0, (int) $posts_per_page);
        $should_limit = $render_limit > 0;
        $rendered_count = 0;
        echo '<div class="my-articles-grid-content">';
        if ( $pinned_query && $pinned_query->have_posts() ) {
            while ( $pinned_query->have_posts() && ( ! $should_limit || $rendered_count < $render_limit ) ) {
                $pinned_query->the_post();
                $this->render_article_item($options, true);
                $has_rendered_posts = true;
                $rendered_count++;
            }
        }
        if ( $regular_query && $regular_query->have_posts() ) {
            while ( $regular_query->have_posts() && ( ! $should_limit || $rendered_count < $render_limit ) ) {
                $regular_query->the_post();
                $this->render_article_item($options, false);
                $has_rendered_posts = true;
                $rendered_count++;
            }
        }
        echo '</div>';

        if ( !$has_rendered_posts ) {
            $this->render_empty_state_message();
        }
    }

    private function render_slideshow($pinned_query, $regular_query, $options, $posts_per_page) {
        $total_posts_needed = $posts_per_page;
        echo '<div class="swiper-container"><div class="swiper-wrapper">';
        $post_count = 0;
        if ( $pinned_query && $pinned_query->have_posts() ) { while ( $pinned_query->have_posts() && $post_count < $total_posts_needed ) { $pinned_query->the_post(); echo '<div class="swiper-slide">'; $this->render_article_item($options, true); echo '</div>'; $post_count++; } }
        if ( $regular_query && $regular_query->have_posts() ) { while ( $regular_query->have_posts() && $post_count < $total_posts_needed ) { $regular_query->the_post(); echo '<div class="swiper-slide">'; $this->render_article_item($options, false); echo '</div>'; $post_count++; } }
        echo '</div><div class="swiper-pagination"></div><div class="swiper-button-next"></div><div class="swiper-button-prev"></div></div>';

        if ( 0 === $post_count ) {
            $this->render_empty_state_message();
        }
    }

    private function render_empty_state_message() {
        echo '<p style="text-align: center; width: 100%; padding: 20px;">' . esc_html__( 'Aucun article trouvé dans cette catégorie.', 'mon-articles' ) . '</p>';
    }

    public function render_article_item($options, $is_pinned = false) {
        $item_classes = 'my-article-item';
        if ($is_pinned) { $item_classes .= ' is-pinned'; }
        $display_mode = $options['display_mode'] ?? 'grid';
        $taxonomy = $options['resolved_taxonomy'] ?? $this->resolve_taxonomy( $options );
        $enable_lazy_load = !empty($options['enable_lazy_load']);
        ?>
        <div class="<?php echo esc_attr($item_classes); ?>">
            <?php
            if ($display_mode === 'list') {
                $this->render_article_common_block($options, $is_pinned, $taxonomy, $enable_lazy_load, 'article-content-wrapper', '...');
            } else {
                $this->render_article_common_block($options, $is_pinned, $taxonomy, $enable_lazy_load, 'article-title-wrapper', '');
            }
            ?>
        </div>
        <?php
    }

    private function render_article_common_block($options, $is_pinned, $taxonomy, $enable_lazy_load, $wrapper_class, $excerpt_more) {
        ?>
        <a href="<?php the_permalink(); ?>" class="article-thumbnail-link">
            <div class="article-thumbnail-wrapper">
                <?php if ($is_pinned && !empty($options['pinned_show_badge'])) : ?><span class="my-article-badge"><?php echo esc_html($options['pinned_badge_text']); ?></span><?php endif; ?>
                <?php if (has_post_thumbnail()):
                    $image_id = get_post_thumbnail_id();
                    $image_src = wp_get_attachment_image_url($image_id, 'large');
                    $image_srcset = wp_get_attachment_image_srcset($image_id, 'large');
                    $placeholder_src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
                    if ($enable_lazy_load) {
                        echo '<img src="' . $placeholder_src . '" data-src="' . esc_url($image_src) . '" data-srcset="' . esc_attr($image_srcset) . '" class="attachment-large size-large wp-post-image lazyload" alt="' . esc_attr(get_the_title()) . '" sizes="auto" />';
                    } else {
                        the_post_thumbnail('large');
                    }
                else: ?>
                    <?php $fallback_placeholder = MY_ARTICLES_PLUGIN_URL . 'assets/images/placeholder.svg'; ?>
                    <img src="<?php echo esc_url($fallback_placeholder); ?>" alt="<?php esc_attr_e('Image non disponible', 'mon-articles'); ?>">
                <?php endif; ?>
            </div>
        </a>
        <div class="<?php echo esc_attr($wrapper_class); ?>">
            <h2 class="article-title"><a href="<?php the_permalink(); ?>"><?php echo esc_html(get_the_title()); ?></a></h2>
            <?php if ($options['show_category'] || $options['show_author'] || $options['show_date']) : ?>
                <div class="article-meta">
                    <?php if ($options['show_category'] && !empty($taxonomy)) echo '<span class="article-category">' . wp_kses_post(get_the_term_list(get_the_ID(), $taxonomy, '', ', ')) . '</span>'; ?>
                    <?php if ($options['show_author']) echo '<span class="article-author">par <a href="' . esc_url(get_author_posts_url(get_the_author_meta('ID'))) . '">' . esc_html(get_the_author()) . '</a></span>'; ?>
                    <?php if ($options['show_date']) echo '<span class="article-date">' . esc_html(get_the_date()) . '</span>'; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($options['show_excerpt'])): ?>
                <div class="my-article-excerpt">
                    <?php echo wp_kses_post(wp_trim_words(get_the_excerpt(), (int)$options['excerpt_length'], $excerpt_more)); ?>
                    <?php if (!empty($options['excerpt_more_text'])): ?>
                        <a href="<?php the_permalink(); ?>" class="my-article-read-more"><?php echo esc_html($options['excerpt_more_text']); ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function enqueue_swiper_scripts($options, $instance_id) {
        wp_enqueue_style('swiper-css');
        wp_enqueue_script('swiper-js');
        wp_enqueue_script('my-articles-swiper-init', MY_ARTICLES_PLUGIN_URL . 'assets/js/swiper-init.js', ['swiper-js'], MY_ARTICLES_VERSION, true);
        wp_localize_script('my-articles-swiper-init', 'myArticlesSwiperSettings_' . $instance_id, [ 'columns_mobile' => $options['columns_mobile'], 'columns_tablet' => $options['columns_tablet'], 'columns_desktop' => $options['columns_desktop'], 'columns_ultrawide' => $options['columns_ultrawide'], 'gap_size' => $options['gap_size'], 'container_selector' => '#my-articles-wrapper-' . $instance_id . ' .swiper-container' ]);
    }
    
    private function render_numbered_pagination($total_pages, $paged, $paged_var) {
        if ($total_pages <= 1) { return; }
        global $wp;
        $current_url = home_url( add_query_arg( array(), $wp->request ) );

        if ( ! empty( $_GET ) ) {
            $sanitized_query_args = array_map( 'sanitize_text_field', wp_unslash( $_GET ) );
            if ( ! empty( $sanitized_query_args ) ) {
                $current_url = add_query_arg( $sanitized_query_args, $current_url );
            }
        }

        $base_url = remove_query_arg( $paged_var, $current_url );
        $pagination_links = paginate_links(['base' => $base_url . '%_%', 'format' => (strpos($base_url, '?') ? '&' : '?') . $paged_var . '=%#%', 'current' => max( 1, $paged ), 'total' => $total_pages, 'prev_text' => __('&laquo; Précédent', 'mon-articles'), 'next_text' => __('Suivant &raquo;', 'mon-articles')]);
        if ($pagination_links) { echo '<nav class="my-articles-pagination">' . $pagination_links . '</nav>'; }
    }

    private function render_inline_styles($options, $id) {
        $dynamic_css = "
        #my-articles-wrapper-{$id} {
            --my-articles-cols-mobile: " . intval($options['columns_mobile']) . ";
            --my-articles-cols-tablet: " . intval($options['columns_tablet']) . ";
            --my-articles-cols-desktop: " . intval($options['columns_desktop']) . ";
            --my-articles-cols-ultrawide: " . intval($options['columns_ultrawide']) . ";
            --my-articles-gap: " . intval($options['gap_size']) . "px;
            --my-articles-list-gap: " . intval($options['list_item_gap']) . "px;
            --my-articles-list-padding-top: " . intval($options['list_content_padding_top']) . "px;
            --my-articles-list-padding-right: " . intval($options['list_content_padding_right']) . "px;
            --my-articles-list-padding-bottom: " . intval($options['list_content_padding_bottom']) . "px;
            --my-articles-list-padding-left: " . intval($options['list_content_padding_left']) . "px;
            --my-articles-border-radius: " . intval($options['border_radius']) . "px;
            --my-articles-title-color: " . esc_attr($options['title_color']) . ";
            --my-articles-title-font-size: " . intval($options['title_font_size']) . "px;
            --my-articles-meta-color: " . esc_attr($options['meta_color']) . ";
            --my-articles-meta-hover-color: " . esc_attr($options['meta_color_hover']) . ";
            --my-articles-meta-font-size: " . intval($options['meta_font_size']) . "px;
            --my-articles-excerpt-font-size: " . intval($options['excerpt_font_size']) . "px;
            --my-articles-excerpt-color: " . esc_attr($options['excerpt_color']) . ";
            --my-articles-pagination-color: " . esc_attr($options['pagination_color']) . ";
            --my-articles-shadow-color: " . esc_attr($options['shadow_color']) . ";
            --my-articles-shadow-color-hover: " . esc_attr($options['shadow_color_hover']) . ";
            --my-articles-pinned-border-color: " . esc_attr($options['pinned_border_color']) . ";
            --my-articles-badge-bg-color: " . esc_attr($options['pinned_badge_bg_color']) . ";
            --my-articles-badge-text-color: " . esc_attr($options['pinned_badge_text_color']) . ";
            background-color: " . esc_attr($options['module_bg_color']) . ";
            padding-left: " . intval($options['module_padding_left']) . "px;
            padding-right: " . intval($options['module_padding_right']) . "px;
        }
        #my-articles-wrapper-{$id} .my-article-item { background-color: " . esc_attr($options['vignette_bg_color']) . "; }
        #my-articles-wrapper-{$id} .my-articles-grid .my-article-item .article-title-wrapper,
        #my-articles-wrapper-{$id} .my-articles-slideshow .my-article-item .article-title-wrapper,
        #my-articles-wrapper-{$id} .my-articles-list .my-article-item .article-content-wrapper { background-color: " . esc_attr($options['title_wrapper_bg_color']) . "; }
        ";

        wp_add_inline_style( 'my-articles-styles', $dynamic_css );
    }

    private function resolve_taxonomy( $options ) {
        $post_type = ! empty( $options['post_type'] ) ? $options['post_type'] : 'post';

        if ( ! empty( $options['taxonomy'] ) && taxonomy_exists( $options['taxonomy'] ) && is_object_in_taxonomy( $post_type, $options['taxonomy'] ) ) {
            return $options['taxonomy'];
        }

        if ( 'post' === $post_type && taxonomy_exists( 'category' ) && is_object_in_taxonomy( 'post', 'category' ) ) {
            return 'category';
        }

        return '';
    }
}
