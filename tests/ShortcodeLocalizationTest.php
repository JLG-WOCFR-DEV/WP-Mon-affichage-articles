<?php

declare(strict_types=1);

namespace {

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

if (!function_exists('admin_url')) {
    function admin_url($path = ''): string
    {
        $path = is_string($path) ? $path : '';

        return 'http://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action): string
    {
        return 'nonce-' . (string) $action;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string)
    {
        return is_string($string) ? strip_tags($string) : '';
    }
}

if (!function_exists('taxonomy_exists')) {
    function taxonomy_exists($taxonomy): bool
    {
        return in_array($taxonomy, array('category'), true);
    }
}

if (!function_exists('is_object_in_taxonomy')) {
    function is_object_in_taxonomy($object_type, $taxonomy): bool
    {
        if ('post' === $object_type && 'category' === $taxonomy) {
            return true;
        }

        return false;
    }
}

}

namespace MonAffichageArticles\Tests {

use My_Articles_Asset_Payload_Registry;
use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;

final class ShortcodeLocalizationTest extends TestCase
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

        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map,
            $mon_articles_test_enqueued_scripts,
            $mon_articles_test_enqueued_styles;

        $mon_articles_test_post_type_map   = array();
        $mon_articles_test_post_status_map = array();
        $mon_articles_test_post_meta_map   = array();
        $mon_articles_test_enqueued_scripts  = array();
        $mon_articles_test_enqueued_styles   = array();

        My_Articles_Asset_Payload_Registry::get_instance()->reset();
    }

    public function test_localized_scripts_include_feedback_labels(): void
    {
        $instanceId = 9001;

        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map;

        $mon_articles_test_post_type_map[$instanceId]   = 'mon_affichage';
        $mon_articles_test_post_status_map[$instanceId] = 'publish';
        $mon_articles_test_post_meta_map[$instanceId]   = array(
            '_my_articles_settings' => array(
                'show_category_filter' => 1,
                'pagination_mode'      => 'load_more',
                'display_mode'         => 'grid',
                'posts_per_page'       => 3,
            ),
        );

        $shortcode = My_Articles_Shortcode::get_instance();

        $shortcode->render_shortcode(array('id' => (string) $instanceId));

        $registry = My_Articles_Asset_Payload_Registry::get_instance();

        $filterData = $registry->get_payload('my-articles-filter', 'myArticlesFilter');
        $loadMoreData = $registry->get_payload('my-articles-load-more', 'myArticlesLoadMore');

        $this->assertIsArray($filterData);
        $this->assertArrayHasKey('countSingle', $filterData);
        $this->assertArrayHasKey('countPlural', $filterData);
        $this->assertArrayHasKey('countNone', $filterData);
        $this->assertArrayHasKey('errorText', $filterData);

        $this->assertIsArray($loadMoreData);
        $this->assertArrayHasKey('totalSingle', $loadMoreData);
        $this->assertArrayHasKey('totalPlural', $loadMoreData);
        $this->assertArrayHasKey('addedSingle', $loadMoreData);
        $this->assertArrayHasKey('addedPlural', $loadMoreData);
        $this->assertArrayHasKey('noAdditional', $loadMoreData);
        $this->assertArrayHasKey('none', $loadMoreData);
        $this->assertArrayHasKey('errorText', $loadMoreData);
    }
}
}

