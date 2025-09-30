<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;

final class RenderArticleItemTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        global $mon_articles_test_term_list_callback;

        $mon_articles_test_term_list_callback = null;
    }

    public function test_render_article_item_ignores_wp_error_term_list(): void
    {
        global $mon_articles_test_current_post_id, $mon_articles_test_term_list_callback;

        $mon_articles_test_current_post_id = 123;

        $mon_articles_test_term_list_callback = static function (): \WP_Error {
            return new \WP_Error('term_fetch_failed', 'Failed to fetch terms');
        };

        $reflection = new \ReflectionClass(My_Articles_Shortcode::class);
        $shortcode = $reflection->newInstanceWithoutConstructor();

        $options = array(
            'display_mode'       => 'grid',
            'show_category'      => true,
            'show_author'        => false,
            'show_date'          => false,
            'show_excerpt'       => false,
            'resolved_taxonomy'  => 'category',
            'enable_lazy_load'   => false,
            'pinned_show_badge'  => false,
        );

        ob_start();
        $shortcode->render_article_item($options, false);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringNotContainsString('article-category', $output);
    }

    public function test_thumbnail_loading_attribute_when_lazy_load_disabled(): void
    {
        $reflection = new \ReflectionClass(My_Articles_Shortcode::class);
        $shortcode = $reflection->newInstanceWithoutConstructor();

        $method = $reflection->getMethod('get_article_thumbnail_html');
        $method->setAccessible(true);

        $html = $method->invoke($shortcode, 456, 'Sample Title', false);

        $this->assertStringNotContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('loading="eager"', $html);
    }

    public function test_thumbnail_loading_attribute_when_lazy_load_enabled(): void
    {
        $reflection = new \ReflectionClass(My_Articles_Shortcode::class);
        $shortcode = $reflection->newInstanceWithoutConstructor();

        $method = $reflection->getMethod('get_article_thumbnail_html');
        $method->setAccessible(true);

        $html = $method->invoke($shortcode, 789, 'Lazy Title', true);

        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringNotContainsString('loading="eager"', $html);
    }

    public function test_excerpt_length_zero_omits_ellipsis_but_keeps_read_more(): void
    {
        $reflection = new \ReflectionClass(My_Articles_Shortcode::class);
        $shortcode = $reflection->newInstanceWithoutConstructor();

        $options = array(
            'display_mode'       => 'list',
            'show_category'      => false,
            'show_author'        => false,
            'show_date'          => false,
            'show_excerpt'       => true,
            'excerpt_length'     => 0,
            'excerpt_more_text'  => 'Lire la suite',
            'resolved_taxonomy'  => '',
            'enable_lazy_load'   => false,
            'pinned_show_badge'  => false,
        );

        ob_start();
        $shortcode->render_article_item($options, false);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('<div class="my-article-excerpt">', $output);
        $this->assertStringContainsString('class="my-article-read-more"', $output);
        $this->assertStringNotContainsString('…', $output);
    }
}

