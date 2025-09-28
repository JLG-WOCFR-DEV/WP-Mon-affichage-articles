<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;

final class RenderArticlesContainerTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        global $mon_articles_test_wp_reset_postdata_callback, $post;

        $mon_articles_test_wp_reset_postdata_callback = null;
        $post = null;
    }

    /**
     * @dataProvider renderMethodProvider
     */
    public function test_render_methods_reset_global_post(string $methodName): void
    {
        global $post, $mon_articles_test_wp_reset_postdata_callback;

        $initial_post = (object) array('ID' => 999);
        $post = $initial_post;

        $mon_articles_test_wp_reset_postdata_callback = static function () use ($initial_post): void {
            global $post;
            $post = $initial_post;
        };

        $reflection = new \ReflectionClass(My_Articles_Shortcode::class);
        $shortcode = $reflection->newInstanceWithoutConstructor();

        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        $pinned_query = new \WP_Query(
            array(
                array('ID' => 101),
            )
        );

        $options = array(
            'display_mode'      => 'list',
            'show_category'     => false,
            'show_author'       => false,
            'show_date'         => false,
            'show_excerpt'      => false,
            'resolved_taxonomy' => '',
            'enable_lazy_load'  => false,
            'pinned_show_badge' => false,
        );

        if ('render_grid' === $methodName) {
            $options['display_mode'] = 'grid';
        }

        ob_start();
        $method->invoke($shortcode, $pinned_query, null, $options, 0);
        ob_end_clean();

        $this->assertSame($initial_post, $post);
    }

    /**
     * @return array<int, array{string}>
     */
    public function renderMethodProvider(): array
    {
        return array(
            array('render_list'),
            array('render_grid'),
        );
    }
}
