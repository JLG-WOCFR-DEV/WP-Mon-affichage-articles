<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RenderInlineStylesTest extends TestCase
{
    public function test_render_inline_styles_returns_custom_property_declarations(): void
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

        $styles = $method->invoke($instance, $options, 321);

        $this->assertIsString($styles);
        $this->assertStringContainsString('--my-articles-surface-color: #123456;', $styles);
        $this->assertStringContainsString('--my-articles-card-surface-color: #654321;', $styles);
        $this->assertStringContainsString('--my-articles-title-surface-color: #abcdef;', $styles);
        $this->assertStringContainsString('--my-articles-thumbnail-aspect-ratio: 16/9;', $styles);
    }
}
