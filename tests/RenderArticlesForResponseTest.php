<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

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
}
