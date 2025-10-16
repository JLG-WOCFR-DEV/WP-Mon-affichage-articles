<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use Mon_Affichage_Articles;
use My_Articles_Render_Result;
use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_Query;

class RenderArticlesForResponseTest extends TestCase
{
    private function invokeRender(array $options, WP_Query $pinned, WP_Query $regular, array $args = array()): My_Articles_Render_Result
    {
        $plugin = new Mon_Affichage_Articles();
        $reflection = new ReflectionClass(Mon_Affichage_Articles::class);
        $method = $reflection->getMethod('render_articles_for_response');
        $method->setAccessible(true);

        $shortcode = new class {
            /** @var int */
            private $callCount = 0;

            public function render_article_item(array $options, bool $is_pinned): void
            {
                $this->callCount++;
                printf('<article data-index="%d" data-pinned="%s"></article>', $this->callCount, $is_pinned ? '1' : '0');
            }

            public function get_skeleton_placeholder_markup(string $container_class, array $options, int $render_limit): string
            {
                return sprintf('<div class="skeleton" data-class="%s" data-limit="%d"></div>', $container_class, $render_limit);
            }

            public function get_empty_state_html(): string
            {
                return '<div class="empty">Aucun article</div>';
            }

            public function get_empty_state_slide_html(): string
            {
                return '<div class="empty-slide">Aucun article</div>';
            }
        };

        $result = $method->invoke($plugin, $shortcode, $options, $pinned, $regular, $args);

        $this->assertInstanceOf(My_Articles_Render_Result::class, $result);

        return $result;
    }

    public function test_initial_render_outputs_empty_state_markup(): void
    {
        $options = array('display_mode' => 'grid');
        $pinned = new WP_Query(array());
        $regular = new WP_Query(array());

        $result = $this->invokeRender($options, $pinned, $regular);

        $this->assertSame('<div class="empty">Aucun article</div>', $result->get_html());
        $this->assertSame(0, $result->get_displayed_posts_count());
    }

    public function test_slideshow_render_outputs_empty_slide_markup(): void
    {
        $options = array('display_mode' => 'slideshow');
        $pinned = new WP_Query(array());
        $regular = new WP_Query(array());

        $result = $this->invokeRender($options, $pinned, $regular);

        $this->assertSame('<div class="empty-slide">Aucun article</div>', $result->get_html());
        $this->assertSame(0, $result->get_displayed_posts_count());
    }

    public function test_load_more_response_skips_empty_state_markup(): void
    {
        $options = array('display_mode' => 'grid');
        $pinned = new WP_Query(array());
        $regular = new WP_Query(array());

        $result = $this->invokeRender(
            $options,
            $pinned,
            $regular,
            array('skip_empty_state_when_empty' => true)
        );

        $this->assertSame('', $result->get_html());
        $this->assertSame(0, $result->get_displayed_posts_count());
    }

    public function test_renderer_wraps_articles_in_swiper_slide_when_requested(): void
    {
        $options = array('display_mode' => 'slideshow');
        $pinned = new WP_Query(array(array('ID' => 7)));
        $regular = new WP_Query(array(array('ID' => 8)));

        $result = $this->invokeRender(
            $options,
            $pinned,
            $regular,
            array(
                'wrap_slides'  => true,
                'track_pinned' => true,
            )
        );

        $expected = '<div class="swiper-slide"><article data-index="1" data-pinned="1"></article></div>' .
            '<div class="swiper-slide"><article data-index="2" data-pinned="0"></article></div>';

        $this->assertSame($expected, $result->get_html());
        $this->assertSame(array(7), $result->get_displayed_pinned_ids());
        $this->assertSame(2, $result->get_displayed_posts_count());
        $this->assertSame(1, $result->get_pinned_rendered_count());
        $this->assertSame(1, $result->get_regular_rendered_count());
    }

    public function test_renderer_includes_skeleton_markup_when_requested(): void
    {
        $options = array('display_mode' => 'grid');
        $pinned = new WP_Query(array());
        $regular = new WP_Query(array(array('ID' => 21)));

        $result = $this->invokeRender(
            $options,
            $pinned,
            $regular,
            array(
                'include_skeleton' => true,
                'render_limit'     => 2,
            )
        );

        $expected = '<div class="skeleton" data-class="my-articles-grid-content" data-limit="2"></div>' .
            '<article data-index="1" data-pinned="0"></article>';

        $this->assertSame($expected, $result->get_html());
        $this->assertSame(1, $result->get_regular_rendered_count());
        $this->assertSame(0, $result->get_pinned_rendered_count());
    }

    public function test_unlimited_slideshow_is_capped_by_query_limit(): void
    {
        global $mon_articles_test_wp_query_factory;

        $factoryBackup = $mon_articles_test_wp_query_factory ?? null;
        $capturedArgs = array();

        $mon_articles_test_wp_query_factory = static function (array $args) use (&$capturedArgs): array {
            $capturedArgs[] = $args;

            return array(
                'posts' => array(),
                'found_posts' => 0,
            );
        };

        $state = array();

        $reflection = new ReflectionClass(My_Articles_Shortcode::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $previousInstance = $instanceProperty->getValue();
        $instanceProperty->setValue(null, null);

        try {
            $shortcode = My_Articles_Shortcode::get_instance();

            $options = array(
                'display_mode' => 'slideshow',
                'is_unlimited' => true,
                'post_type' => 'post',
                'resolved_taxonomy' => '',
                'term' => '',
                'ignore_native_sticky' => 0,
                'all_excluded_ids' => array(),
                'exclude_post_ids' => array(),
                'pinned_posts' => array(),
                'unlimited_query_cap' => 7,
            );

            $state = $shortcode->build_display_state($options);
        } finally {
            $mon_articles_test_wp_query_factory = $factoryBackup;
            $instanceProperty->setValue(null, $previousInstance);
        }

        $this->assertSame(7, $state['effective_posts_per_page']);
        $this->assertSame(7, $state['render_limit']);
        $this->assertTrue($state['should_limit_display']);

        $this->assertNotEmpty($capturedArgs);
        $regularQueryArgs = array_pop($capturedArgs);
        $this->assertSame(7, $regularQueryArgs['posts_per_page']);
    }

    public function test_custom_post_type_update_refreshes_cache_namespace(): void
    {
        global $mon_articles_test_post_type_map, $mon_articles_test_filters, $mon_articles_test_options, $mon_articles_test_options_store;

        $mon_articles_test_filters = array();
        $mon_articles_test_post_type_map = array(123 => 'press_release');
        $mon_articles_test_options = array('my_articles_cache_namespace' => 'initial-namespace');
        $mon_articles_test_options_store = $mon_articles_test_options;
        $mon_articles_test_options_store =& $mon_articles_test_options;

        \add_filter('my_articles_cache_tracked_post_types', static function (array $post_types): array {
            $post_types[] = 'press_release';

            return $post_types;
        });

        $plugin = new Mon_Affichage_Articles();

        $reflection = new ReflectionClass(Mon_Affichage_Articles::class);
        $method = $reflection->getMethod('get_cache_namespace');
        $method->setAccessible(true);

        $initialNamespace = $method->invoke($plugin);
        $this->assertSame('initial-namespace', $initialNamespace);

        $plugin->handle_post_save_cache_invalidation(123, null, true);

        $refreshedNamespace = $method->invoke($plugin);

        $this->assertNotSame($initialNamespace, $refreshedNamespace);
        $this->assertSame($refreshedNamespace, $mon_articles_test_options['my_articles_cache_namespace'] ?? null);

        $mon_articles_test_filters = array();
    }

    public function test_append_active_tax_query_merges_filters(): void
    {
        $args = array();
        $filters = array(
            array('taxonomy' => 'post_tag', 'slug' => 'featured'),
            array('taxonomy' => 'category', 'slug' => 'news'),
        );

        $result = My_Articles_Shortcode::append_active_tax_query($args, 'category', 'news', $filters);

        $this->assertArrayHasKey('tax_query', $result);
        $this->assertIsArray($result['tax_query']);
        $this->assertSame('AND', $result['tax_query']['relation']);

        $clauses = $result['tax_query'];
        unset($clauses['relation']);

        $this->assertCount(2, $clauses);

        $expected = array(
            array('taxonomy' => 'category', 'field' => 'slug', 'terms' => 'news'),
            array('taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => 'featured'),
        );

        $this->assertSame($expected, array_values($clauses));
    }
}
