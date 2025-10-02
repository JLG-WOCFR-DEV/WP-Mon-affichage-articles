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

if (!function_exists('wp_localize_script')) {
    /**
     * @param string $handle
     * @param string $object_name
     * @param array<string, mixed> $l10n
     */
    function wp_localize_script($handle, $object_name, $l10n): bool
    {
        global $mon_articles_test_localized_scripts;

        if (!is_array($mon_articles_test_localized_scripts)) {
            $mon_articles_test_localized_scripts = array();
        }

        $mon_articles_test_localized_scripts[] = array(
            'handle'      => (string) $handle,
            'object_name' => (string) $object_name,
            'data'        => is_array($l10n) ? $l10n : array(),
        );

        return true;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action): string
    {
        return 'nonce-' . (string) $action;
    }
}

if (!function_exists('get_current_screen')) {
    function get_current_screen()
    {
        global $mon_articles_test_current_screen;

        return $mon_articles_test_current_screen;
    }
}

}

namespace MonAffichageArticles\Tests {

use LCV\MonAffichage\My_Articles_Metaboxes;
use PHPUnit\Framework\TestCase;

final class MyArticlesMetaboxesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $mon_articles_test_enqueued_styles,
            $mon_articles_test_enqueued_scripts,
            $mon_articles_test_localized_scripts,
            $mon_articles_test_current_screen,
            $typenow;

        if (!defined('MY_ARTICLES_VERSION')) {
            define('MY_ARTICLES_VERSION', 'tests');
        }

        if (!defined('MY_ARTICLES_PLUGIN_URL')) {
            define('MY_ARTICLES_PLUGIN_URL', 'http://example.com/wp-content/plugins/mon-affichage-articles/');
        }

        $mon_articles_test_enqueued_styles    = array();
        $mon_articles_test_enqueued_scripts   = array();
        $mon_articles_test_localized_scripts  = array();
        $mon_articles_test_current_screen     = null;
        $typenow                              = null;
    }

    public function test_enqueue_admin_scripts_loads_assets_for_new_mon_affichage_screen(): void
    {
        global $typenow,
            $mon_articles_test_enqueued_styles,
            $mon_articles_test_enqueued_scripts,
            $mon_articles_test_localized_scripts;

        $typenow = 'mon_affichage';

        $metaboxes = My_Articles_Metaboxes::get_instance();
        $metaboxes->enqueue_admin_scripts('post-new.php');

        $style_handles = array_map(
            static fn (array $item): string => $item['handle'] ?? '',
            $mon_articles_test_enqueued_styles
        );

        $script_handles = array_map(
            static fn (array $item): string => $item['handle'] ?? '',
            $mon_articles_test_enqueued_scripts
        );

        $localized_handles = array_map(
            static fn (array $item): string => $item['handle'] ?? '',
            $mon_articles_test_localized_scripts
        );

        self::assertSame(
            array('wp-color-picker', 'select2-css'),
            $style_handles,
            'Expected admin styles to be enqueued for the mon_affichage screen.'
        );

        self::assertSame(
            array(
                'my-articles-admin-script',
                'select2-js',
                'my-articles-admin-select2',
                'my-articles-admin-options',
                'my-articles-dynamic-fields',
            ),
            $script_handles,
            'Expected admin scripts to be enqueued for the mon_affichage screen.'
        );

        sort($localized_handles);

        self::assertSame(
            array(
                'my-articles-admin-options',
                'my-articles-admin-select2',
                'my-articles-dynamic-fields',
            ),
            $localized_handles,
            'Expected localization data to be attached to the required handles.'
        );
    }
}

}

