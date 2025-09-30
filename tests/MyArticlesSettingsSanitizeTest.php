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
}
