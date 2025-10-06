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

if (!function_exists('wp_script_is')) {
    function wp_script_is($handle, $list = 'enqueued')
    {
        global $mon_articles_test_registered_scripts, $mon_articles_test_marked_scripts;

        $handle = (string) $handle;
        $list   = (string) $list;

        if ('registered' === $list) {
            if (is_array($mon_articles_test_registered_scripts) && isset($mon_articles_test_registered_scripts[$handle])) {
                return true;
            }

            if (is_array($mon_articles_test_marked_scripts) && in_array($handle, $mon_articles_test_marked_scripts, true)) {
                return true;
            }

            return false;
        }

        return false;
    }
}

if (!function_exists('wp_set_script_translations')) {
    function wp_set_script_translations($handle, $domain, $path)
    {
        global $mon_articles_test_script_translations;

        if (!is_array($mon_articles_test_script_translations)) {
            $mon_articles_test_script_translations = array();
        }

        $mon_articles_test_script_translations[] = array(
            'handle' => (string) $handle,
            'domain' => (string) $domain,
            'path'   => (string) $path,
        );

        return true;
    }
}

if (!function_exists('wp_add_inline_script')) {
    function wp_add_inline_script($handle, $data, $position = 'after')
    {
        global $mon_articles_test_inline_scripts;

        if (!is_array($mon_articles_test_inline_scripts)) {
            $mon_articles_test_inline_scripts = array();
        }

        $mon_articles_test_inline_scripts[] = array(
            'handle'   => (string) $handle,
            'data'     => (string) $data,
            'position' => (string) $position,
        );

        return true;
    }
}

}

namespace MonAffichageArticles\Tests {

use My_Articles_Enqueue;
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

        require_once __DIR__ . '/../mon-affichage-article/includes/class-my-articles-enqueue.php';

        global $mon_articles_test_registered_styles,
            $mon_articles_test_registered_scripts,
            $mon_articles_test_script_data,
            $mon_articles_test_enqueued_styles,
            $mon_articles_test_enqueued_scripts,
            $mon_articles_test_script_translations,
            $mon_articles_test_inline_scripts,
            $mon_articles_test_marked_scripts;

        $mon_articles_test_registered_styles = array();
        $mon_articles_test_registered_scripts = array();
        $mon_articles_test_script_data       = array();
        $mon_articles_test_enqueued_styles   = array();
        $mon_articles_test_enqueued_scripts  = array();
        $mon_articles_test_script_translations = array();
        $mon_articles_test_inline_scripts      = array();
        $mon_articles_test_marked_scripts      = array();
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
        $this->assertArrayHasKey('my-articles-swiper-init', $mon_articles_test_registered_scripts);
        $this->assertContains(
            'swiper-js',
            $mon_articles_test_registered_scripts['my-articles-swiper-init']['deps'],
            'Swiper init script should depend on Swiper core.'
        );
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
        $this->assertNotContains('swiper-css', $enqueued_style_handles, 'Swiper styles should not be enqueued by default in the editor.');

        $this->assertContains('my-articles-responsive-layout', $enqueued_script_handles);
        $this->assertContains('my-articles-debug-helper', $enqueued_script_handles);
        $this->assertNotContains('swiper-js', $enqueued_script_handles, 'Swiper script should load on demand in the editor.');
        $this->assertNotContains('lazysizes', $enqueued_script_handles, 'LazySizes should load on demand in the editor.');

        global $mon_articles_test_inline_scripts;
        $this->assertIsArray($mon_articles_test_inline_scripts);

        $preview_snippets = array_filter(
            $mon_articles_test_inline_scripts,
            static function (array $entry): bool {
                return $entry['handle'] === 'mon-affichage-articles-preview';
            }
        );

        $editor_snippets = array_filter(
            $mon_articles_test_inline_scripts,
            static function (array $entry): bool {
                return $entry['handle'] === 'mon-affichage-articles-editor-script';
            }
        );

        $this->assertNotEmpty($preview_snippets, 'Dynamic asset manifest should be exposed to the preview script.');
        $this->assertNotEmpty($editor_snippets, 'Dynamic asset manifest should be exposed to the editor script.');

        foreach (array_merge($preview_snippets, $editor_snippets) as $snippet) {
            $this->assertStringContainsString('myArticlesAssets', $snippet['data']);
            $this->assertStringContainsString('"swiper"', $snippet['data']);
        }
    }

    public function test_block_editor_translations_use_plugin_directory(): void
    {
        global $mon_articles_test_registered_scripts, $mon_articles_test_script_translations;

        $enqueue = My_Articles_Enqueue::get_instance();
        $enqueue->register_plugin_styles_scripts();

        $mon_articles_test_registered_scripts['mon-affichage-articles-preview'] = array(
            'src'       => '',
            'deps'      => array(),
            'ver'       => '',
            'in_footer' => false,
        );

        $mon_articles_test_registered_scripts['mon-affichage-articles-editor-script'] = array(
            'src'       => '',
            'deps'      => array(),
            'ver'       => '',
            'in_footer' => false,
        );

        $mon_articles_test_script_translations = array();

        $enqueue->enqueue_block_editor_assets();

        $expected_path = MY_ARTICLES_PLUGIN_DIR . 'languages';

        $this->assertNotEmpty(
            $mon_articles_test_script_translations,
            'Translations should be registered for block editor scripts.'
        );

        $handles = array_column($mon_articles_test_script_translations, 'handle');

        $this->assertContains('mon-affichage-articles-preview', $handles);
        $this->assertContains('mon-affichage-articles-editor-script', $handles);

        foreach ($mon_articles_test_script_translations as $translation) {
            $this->assertSame('mon-articles', $translation['domain']);
            $this->assertSame($expected_path, $translation['path']);
        }
    }
}
}
