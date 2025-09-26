<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RenderInlineStylesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $wp_styles;

        $wp_styles = new \WP_Styles();
    }

    public function test_render_inline_styles_records_scoped_css(): void
    {
        $options = array(
            'module_bg_color' => '#123456',
            'vignette_bg_color' => '#654321',
            'title_wrapper_bg_color' => '#ABCDEF',
        );

        $reflection = new ReflectionClass(My_Articles_Shortcode::class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('render_inline_styles');
        $method->setAccessible(true);

        $method->invoke($instance, $options, 321);

        global $wp_styles;

        $this->assertArrayHasKey('my-articles-styles', $wp_styles->inline_styles);
        $this->assertNotEmpty($wp_styles->inline_styles['my-articles-styles']);

        $css = implode("\n", $wp_styles->inline_styles['my-articles-styles']);

        $this->assertStringContainsString('#my-articles-wrapper-321 {', $css);
        $this->assertStringContainsString('background-color: #123456;', $css);
        $this->assertStringContainsString('#my-articles-wrapper-321 .my-article-item { background-color: #654321; }', $css);
        $this->assertStringContainsString('#my-articles-wrapper-321 .my-articles-grid .my-article-item .article-title-wrapper,', $css);
        $this->assertStringContainsString('#my-articles-wrapper-321 .my-articles-slideshow .my-article-item .article-title-wrapper,', $css);
        $this->assertStringContainsString('#my-articles-wrapper-321 .my-articles-list .my-article-item .article-content-wrapper { background-color: #abcdef; }', $css);
    }
}
