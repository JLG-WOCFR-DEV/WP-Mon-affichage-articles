<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use PHPUnit\Framework\TestCase;

final class SettingsSanitizerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__) . '/mon-affichage-article/includes/helpers.php';
        require_once dirname(__DIR__) . '/mon-affichage-article/includes/class-my-articles-settings-sanitizer.php';
    }

    public function test_invalid_enum_values_fall_back_to_defaults(): void
    {
        $errors = array();

        $sanitized = \My_Articles_Settings_Sanitizer::sanitize(
            array(
                'display_mode' => 'carousel',
                'instrumentation_channel' => 'ftp',
                'thumbnail_aspect_ratio' => '21/9',
            ),
            function (string $field, string $message) use (&$errors): void {
                $errors[$field] = $message;
            }
        );

        self::assertSame('grid', $sanitized['display_mode']);
        self::assertSame('console', $sanitized['instrumentation_channel']);
        self::assertSame('16/9', $sanitized['thumbnail_aspect_ratio']);

        self::assertArrayHasKey('display_mode', $errors);
        self::assertArrayHasKey('instrumentation_channel', $errors);
        self::assertArrayHasKey('thumbnail_aspect_ratio', $errors);
    }

    public function test_numeric_constraints_are_clamped(): void
    {
        $errors = array();

        $sanitized = \My_Articles_Settings_Sanitizer::sanitize(
            array(
                'posts_per_page'     => 999,
                'module_margin_top'  => -10,
                'title_font_size'    => 5,
                'gap_size'           => 200,
            ),
            function (string $field) use (&$errors): void {
                $errors[] = $field;
            }
        );

        self::assertSame(50, $sanitized['posts_per_page']);
        self::assertSame(0, $sanitized['module_margin_top']);
        self::assertSame(10, $sanitized['title_font_size']);
        self::assertSame(50, $sanitized['gap_size']);

        self::assertContains('posts_per_page', $errors);
        self::assertContains('module_margin_top', $errors);
        self::assertContains('title_font_size', $errors);
        self::assertContains('gap_size', $errors);
    }

    public function test_boolean_and_color_fields_are_sanitized(): void
    {
        $errors = array();

        $sanitized = \My_Articles_Settings_Sanitizer::sanitize(
            array(
                'show_author' => '1',
                'meta_color'  => 'not-a-color',
                'module_bg_color' => 'rgba(255,0,0,0.5)',
            ),
            function (string $field) use (&$errors): void {
                $errors[] = $field;
            }
        );

        self::assertSame(0, $sanitized['show_category']);
        self::assertSame(1, $sanitized['show_author']);
        self::assertSame(0, $sanitized['show_date']);
        self::assertSame('#6b7280', $sanitized['meta_color']);
        self::assertSame('rgba(255, 0, 0, 0.5)', $sanitized['module_bg_color']);

        self::assertContains('meta_color', $errors);
    }
}
