<?php

declare(strict_types=1);

namespace {

if (!function_exists('wp_enqueue_style')) {
    /**
     * @param string $handle
     * @param string $src
     * @param array<int, string> $deps
     * @param string|bool $ver
     * @param string $media
     */
    function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all'): void
    {
        global $mon_articles_test_enqueued_styles;

        if (!is_array($mon_articles_test_enqueued_styles)) {
            $mon_articles_test_enqueued_styles = array();
        }

        $mon_articles_test_enqueued_styles[] = array(
            'handle' => (string) $handle,
            'src'    => (string) $src,
            'deps'   => is_array($deps) ? array_values($deps) : array(),
            'ver'    => is_string($ver) ? $ver : '',
            'media'  => (string) $media,
        );
    }
}

if (!function_exists('wp_enqueue_script')) {
    /**
     * @param string $handle
     * @param string $src
     * @param array<int, string> $deps
     * @param string|bool $ver
     * @param bool $in_footer
     */
    function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false): void
    {
        global $mon_articles_test_enqueued_scripts;

        if (!is_array($mon_articles_test_enqueued_scripts)) {
            $mon_articles_test_enqueued_scripts = array();
        }

        $mon_articles_test_enqueued_scripts[] = array(
            'handle'    => (string) $handle,
            'src'       => (string) $src,
            'deps'      => is_array($deps) ? array_values($deps) : array(),
            'ver'       => is_string($ver) ? $ver : '',
            'in_footer' => (bool) $in_footer,
        );
    }
}

}

namespace MonAffichageArticles\Tests {

use LCV\MonAffichage\My_Articles_Enqueue;
use PHPUnit\Framework\TestCase;

final class MyArticlesEnqueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('MY_ARTICLES_VERSION')) {
            define('MY_ARTICLES_VERSION', 'tests');
        }

        if (!defined('MY_ARTICLES_PLUGIN_URL')) {
            define('MY_ARTICLES_PLUGIN_URL', 'http://example.com/wp-content/plugins/mon-affichage-articles/');
        }

        global $mon_articles_test_registered_styles,
            $mon_articles_test_registered_scripts,
            $mon_articles_test_script_data,
            $mon_articles_test_enqueued_styles,
            $mon_articles_test_enqueued_scripts;

        $mon_articles_test_registered_styles = array();
        $mon_articles_test_registered_scripts = array();
        $mon_articles_test_script_data       = array();
        $mon_articles_test_enqueued_styles   = array();
        $mon_articles_test_enqueued_scripts  = array();
    }

    public function test_registers_assets_during_init(): void
    {
        $enqueue = My_Articles_Enqueue::get_instance();
        $enqueue->register_plugin_styles_scripts();

        global $mon_articles_test_registered_styles,
            $mon_articles_test_registered_scripts,
            $mon_articles_test_script_data,
            $mon_articles_test_enqueued_styles,
            $mon_articles_test_enqueued_scripts;

        $this->assertArrayHasKey('my-articles-styles', $mon_articles_test_registered_styles);
        $this->assertSame(
            'http://example.com/wp-content/plugins/mon-affichage-articles/assets/css/styles.css',
            $mon_articles_test_registered_styles['my-articles-styles']['src']
        );
        $this->assertArrayHasKey('swiper-css', $mon_articles_test_registered_styles);

        $this->assertArrayHasKey('swiper-js', $mon_articles_test_registered_scripts);
        $this->assertArrayHasKey('lazysizes', $mon_articles_test_registered_scripts);
        $this->assertTrue($mon_articles_test_registered_scripts['lazysizes']['in_footer']);
        $this->assertArrayHasKey('my-articles-responsive-layout', $mon_articles_test_registered_scripts);
        $this->assertArrayHasKey('my-articles-debug-helper', $mon_articles_test_registered_scripts);

        $this->assertArrayHasKey('lazysizes', $mon_articles_test_script_data);
        $this->assertArrayHasKey('async', $mon_articles_test_script_data['lazysizes']);
        $this->assertTrue($mon_articles_test_script_data['lazysizes']['async']);

        $this->assertSame(array(), $mon_articles_test_enqueued_styles);
        $this->assertSame(array(), $mon_articles_test_enqueued_scripts);
    }

    public function test_block_editor_enqueue_uses_registered_handles(): void
    {
        $enqueue = My_Articles_Enqueue::get_instance();
        $enqueue->register_plugin_styles_scripts();
        $enqueue->enqueue_block_editor_assets();

        global $mon_articles_test_enqueued_styles, $mon_articles_test_enqueued_scripts;

        $enqueued_style_handles = array_map(
            static function (array $entry): string {
                return $entry['handle'];
            },
            $mon_articles_test_enqueued_styles
        );
        $enqueued_script_handles = array_map(
            static function (array $entry): string {
                return $entry['handle'];
            },
            $mon_articles_test_enqueued_scripts
        );

        $this->assertContains('my-articles-styles', $enqueued_style_handles);
        $this->assertContains('swiper-css', $enqueued_style_handles);

        $this->assertContains('swiper-js', $enqueued_script_handles);
        $this->assertContains('lazysizes', $enqueued_script_handles);
        $this->assertContains('my-articles-responsive-layout', $enqueued_script_handles);
        $this->assertContains('my-articles-debug-helper', $enqueued_script_handles);
    }
}
}
