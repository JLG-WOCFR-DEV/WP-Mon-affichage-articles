<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use My_Articles_Settings;
use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MyArticlesSettingsSanitizeTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $normalizedOptionsCacheBackup = array();

    public static function setUpBeforeClass(): void
    {
        if (!defined('MY_ARTICLES_VERSION')) {
            define('MY_ARTICLES_VERSION', 'tests');
        }

        if (!defined('MY_ARTICLES_PLUGIN_URL')) {
            define('MY_ARTICLES_PLUGIN_URL', 'http://example.com/wp-content/plugins/mon-affichage-articles/');
        }

        if (!class_exists(My_Articles_Settings::class)) {
            require_once dirname(__DIR__) . '/mon-affichage-article/includes/class-my-articles-settings.php';
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupShortcodeCache();
    }

    protected function tearDown(): void
    {
        $this->restoreShortcodeCache();
        parent::tearDown();
    }

    private function backupShortcodeCache(): void
    {
        $reflection = new ReflectionClass(My_Articles_Shortcode::class);
        $property = $reflection->getProperty('normalized_options_cache');
        $property->setAccessible(true);
        $this->normalizedOptionsCacheBackup = (array) $property->getValue();
        $property->setValue(null, array());
    }

    private function restoreShortcodeCache(): void
    {
        $reflection = new ReflectionClass(My_Articles_Shortcode::class);
        $property = $reflection->getProperty('normalized_options_cache');
        $property->setAccessible(true);
        $property->setValue(null, $this->normalizedOptionsCacheBackup);
    }

    public function test_sanitize_caps_posts_per_page_at_ui_limit(): void
    {
        $settings = My_Articles_Settings::get_instance();
        $result = $settings->sanitize(array('posts_per_page' => 120));

        self::assertSame(
            50,
            $result['posts_per_page'] ?? null,
            'The sanitize routine should clamp posts_per_page to the UI maximum.'
        );
    }

    public function test_sanitize_preserves_list_display_mode(): void
    {
        $settings = My_Articles_Settings::get_instance();
        $result = $settings->sanitize(array('display_mode' => 'list'));

        self::assertSame(
            'list',
            $result['display_mode'] ?? null,
            'The sanitize routine should allow the list display mode to persist.'
        );
    }

    /**
     * @param non-empty-string $field
     * @param int $input
     * @param int $expected
     *
     * @dataProvider provide_clamped_integer_settings
     */
    public function test_sanitize_clamps_integer_settings(string $field, int $input, int $expected): void
    {
        $settings = My_Articles_Settings::get_instance();

        $result = $settings->sanitize(array($field => $input));

        self::assertSame(
            $expected,
            $result[$field] ?? null,
            sprintf('The sanitize routine should clamp %s values to the UI boundaries.', $field)
        );
    }

    /**
     * @return iterable<string, array{0: non-empty-string, 1: int, 2: int}>
     */
    public function provide_clamped_integer_settings(): iterable
    {
        yield 'posts per page minimum' => array('posts_per_page', -10, 0);
        yield 'desktop columns minimum' => array('desktop_columns', -5, 1);
        yield 'desktop columns maximum' => array('desktop_columns', 99, 6);
        yield 'mobile columns minimum' => array('mobile_columns', 0, 1);
        yield 'mobile columns maximum' => array('mobile_columns', 10, 3);
        yield 'gap size minimum' => array('gap_size', -10, 0);
        yield 'gap size maximum' => array('gap_size', 120, 50);
        yield 'border radius minimum' => array('border_radius', -20, 0);
        yield 'border radius maximum' => array('border_radius', 120, 50);
        yield 'title font size minimum' => array('title_font_size', 1, 10);
        yield 'title font size maximum' => array('title_font_size', 99, 40);
        yield 'meta font size minimum' => array('meta_font_size', 1, 8);
        yield 'meta font size maximum' => array('meta_font_size', 999, 20);
        yield 'module margin left minimum' => array('module_margin_left', -50, 0);
        yield 'module margin left maximum' => array('module_margin_left', 999, 200);
        yield 'module margin right minimum' => array('module_margin_right', -50, 0);
        yield 'module margin right maximum' => array('module_margin_right', 999, 200);
    }

    public function test_normalize_instance_options_treats_zero_as_unlimited(): void
    {
        $options = My_Articles_Shortcode::normalize_instance_options(
            array('posts_per_page' => 0),
            array('source' => __METHOD__)
        );

        self::assertTrue($options['is_unlimited'], 'A posts_per_page value of 0 should be treated as unlimited.');
        self::assertSame(
            -1,
            $options['posts_per_page'],
            'Unlimited instances should forward -1 to WP_Query.'
        );
    }

    public function test_sanitize_thumbnail_aspect_ratio_accepts_only_whitelist(): void
    {
        $settings = My_Articles_Settings::get_instance();
        $defaults = My_Articles_Shortcode::get_default_options();

        $invalid = $settings->sanitize(array('thumbnail_aspect_ratio' => '42/7'));

        self::assertSame(
            $defaults['thumbnail_aspect_ratio'],
            $invalid['thumbnail_aspect_ratio'] ?? null,
            'Unexpected ratios should fall back to the default value.'
        );

        $valid = $settings->sanitize(array('thumbnail_aspect_ratio' => '4/3'));

        self::assertSame(
            '4/3',
            $valid['thumbnail_aspect_ratio'] ?? null,
            'Allowed ratios should be preserved during sanitization.'
        );
    }

    public function test_normalize_instance_options_enforces_thumbnail_aspect_ratio_whitelist(): void
    {
        $defaults = My_Articles_Shortcode::get_default_options();

        $normalizedInvalid = My_Articles_Shortcode::normalize_instance_options(
            array('thumbnail_aspect_ratio' => '7/5'),
            array('source' => __METHOD__)
        );

        self::assertSame(
            $defaults['thumbnail_aspect_ratio'],
            $normalizedInvalid['thumbnail_aspect_ratio'],
            'Invalid ratios should revert to the default thumbnail aspect ratio.'
        );

        $normalizedValid = My_Articles_Shortcode::normalize_instance_options(
            array('thumbnail_aspect_ratio' => '3/2'),
            array('source' => __METHOD__)
        );

        self::assertSame(
            '3/2',
            $normalizedValid['thumbnail_aspect_ratio'],
            'Allowed ratios should survive normalization.'
        );
    }

    public function test_normalize_instance_options_applies_requested_sort_to_orderby(): void
    {
        $normalized = My_Articles_Shortcode::normalize_instance_options(
            array(
                'orderby' => 'date',
                'sort' => 'date',
                'meta_key' => '',
            ),
            array('requested_sort' => 'comment_count')
        );

        self::assertSame('comment_count', $normalized['orderby']);
        self::assertSame('comment_count', $normalized['sort']);
    }

    public function test_normalize_instance_options_falls_back_when_sort_requires_meta_key(): void
    {
        $defaults = My_Articles_Shortcode::get_default_options();

        $normalized = My_Articles_Shortcode::normalize_instance_options(
            array(
                'orderby' => 'date',
                'sort' => 'date',
                'meta_key' => '',
            ),
            array('requested_sort' => 'meta_value')
        );

        self::assertSame($defaults['orderby'], $normalized['orderby']);
        self::assertSame($defaults['sort'], $normalized['sort']);
    }
}
