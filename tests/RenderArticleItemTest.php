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
}

