<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use LCV\MonAffichage\My_Articles_Shortcode;
use Mon_Affichage_Articles;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_Query;

class RenderArticlesForResponseTest extends TestCase
{
    private function invokeRender(array $options, WP_Query $pinned, WP_Query $regular, array $args = array()): array
    {
        $plugin = new Mon_Affichage_Articles();
        $reflection = new ReflectionClass(Mon_Affichage_Articles::class);
        $method = $reflection->getMethod('render_articles_for_response');
        $method->setAccessible(true);

        $shortcode = new class {
            public function render_article_item(array $options, bool $is_pinned): void
            {
                // no-op for these tests.
            }

            public function get_empty_state_html(): string
            {
                return '<div class="empty">Aucun article</div>';
            }

            public function get_empty_state_slide_html(): string
            {
                return '<div class="empty-slide">Aucun article</div>';
            }

            public function get_skeleton_placeholder_markup(string $containerClass, array $options, int $renderLimit): string
            {
                return '<div class="skeleton">Placeholder</div>';
            }
        };

        return $method->invoke($plugin, $shortcode, $options, $pinned, $regular, $args);
    }

    public function test_initial_render_outputs_empty_state_markup(): void
    {
        $options = array('display_mode' => 'grid');
        $pinned = new WP_Query(array());
        $regular = new WP_Query(array());

        $result = $this->invokeRender($options, $pinned, $regular);

        $this->assertSame('<div class="empty">Aucun article</div>', $result['html']);
        $this->assertSame(0, $result['displayed_posts_count']);
    }

    public function test_slideshow_render_outputs_empty_slide_markup(): void
    {
        $options = array('display_mode' => 'slideshow');
        $pinned = new WP_Query(array());
        $regular = new WP_Query(array());

        $result = $this->invokeRender($options, $pinned, $regular);

        $this->assertSame('<div class="empty-slide">Aucun article</div>', $result['html']);
        $this->assertSame(0, $result['displayed_posts_count']);
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

        $this->assertSame('', $result['html']);
        $this->assertSame(0, $result['displayed_posts_count']);
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
}
