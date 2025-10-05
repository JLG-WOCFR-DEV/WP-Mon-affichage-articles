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

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return $value;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability, ...$args): bool
    {
        return true;
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value)
    {
        global $mon_articles_test_saved_meta;

        if (!is_array($mon_articles_test_saved_meta)) {
            $mon_articles_test_saved_meta = array();
        }

        if (!isset($mon_articles_test_saved_meta[$post_id])) {
            $mon_articles_test_saved_meta[$post_id] = array();
        }

        $mon_articles_test_saved_meta[$post_id][$meta_key] = $meta_value;

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

use My_Articles_Metaboxes;
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

        if (!class_exists(My_Articles_Metaboxes::class)) {
            require_once dirname(__DIR__) . '/mon-affichage-article/includes/class-my-articles-metaboxes.php';
        }

        if (!function_exists('my_articles_normalize_post_type')) {
            require_once dirname(__DIR__) . '/mon-affichage-article/includes/helpers.php';
        }

        if (!class_exists(\My_Articles_Shortcode::class)) {
            require_once dirname(__DIR__) . '/mon-affichage-article/includes/class-my-articles-shortcode.php';
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

    public function test_save_meta_data_clamps_numeric_fields_to_ui_ranges(): void
    {
        global $mon_articles_test_saved_meta,
            $mon_articles_test_valid_nonces,
            $_POST;

        $mon_articles_test_saved_meta = array();
        $mon_articles_test_valid_nonces = array(
            'valid-nonce' => array('my_articles_save_meta_box_data'),
        );

        $_POST = array(
            'my_articles_meta_box_nonce' => 'valid-nonce',
            '_my_articles_settings'      => array(
                'post_type'                   => 'post',
                'display_mode'                => 'grid',
                'columns_mobile'              => 0,
                'columns_tablet'              => 0,
                'columns_desktop'             => 99,
                'columns_ultrawide'           => 999,
                'module_padding_left'         => 250,
                'module_padding_right'        => 1,
                'gap_size'                    => 200,
                'list_item_gap'               => 75,
                'list_content_padding_top'    => 101,
                'list_content_padding_right'  => 200,
                'list_content_padding_bottom' => 999,
                'list_content_padding_left'   => 150,
                'border_radius'               => 120,
                'title_font_size'             => 5,
                'meta_font_size'              => 40,
                'excerpt_font_size'           => 150,
            ),
        );

        $metaboxes = My_Articles_Metaboxes::get_instance();
        $metaboxes->save_meta_data(123);

        $saved = $mon_articles_test_saved_meta[123]['_my_articles_settings'] ?? null;

        self::assertIsArray($saved, 'Expected the sanitized settings to be stored.');
        self::assertSame(1, $saved['columns_mobile']);
        self::assertSame(1, $saved['columns_tablet']);
        self::assertSame(6, $saved['columns_desktop']);
        self::assertSame(8, $saved['columns_ultrawide']);
        self::assertSame(200, $saved['module_padding_left']);
        self::assertSame(1, $saved['module_padding_right']);
        self::assertSame(50, $saved['gap_size']);
        self::assertSame(50, $saved['list_item_gap']);
        self::assertSame(100, $saved['list_content_padding_top']);
        self::assertSame(100, $saved['list_content_padding_right']);
        self::assertSame(100, $saved['list_content_padding_bottom']);
        self::assertSame(100, $saved['list_content_padding_left']);
        self::assertSame(50, $saved['border_radius']);
        self::assertSame(10, $saved['title_font_size']);
        self::assertSame(20, $saved['meta_font_size']);
        self::assertSame(100, $saved['excerpt_font_size']);

        $_POST = array();
    }

    public function test_save_meta_data_sanitizes_tax_filters(): void
    {
        global $mon_articles_test_saved_meta,
            $mon_articles_test_valid_nonces,
            $mon_articles_test_taxonomies,
            $mon_articles_test_terms,
            $_POST;

        $mon_articles_test_saved_meta = array();
        $mon_articles_test_valid_nonces = array(
            'valid-nonce' => array('my_articles_save_meta_box_data'),
        );

        $mon_articles_test_taxonomies = array(
            'post' => array(
                'category' => (object) array(
                    'name'   => 'category',
                    'labels' => (object) array('name' => 'Catégories'),
                ),
                'post_tag' => (object) array(
                    'name'   => 'post_tag',
                    'labels' => (object) array('name' => 'Étiquettes'),
                ),
            ),
        );

        $mon_articles_test_terms = array(
            'category' => array(
                (object) array('term_id' => 1, 'name' => 'Actualités', 'slug' => 'news'),
            ),
            'post_tag' => array(
                (object) array('term_id' => 2, 'name' => 'À la une', 'slug' => 'featured'),
            ),
        );

        $_POST = array(
            'my_articles_meta_box_nonce' => 'valid-nonce',
            '_my_articles_settings'      => array(
                'post_type'    => 'post',
                'taxonomy'     => 'category',
                'term'         => 'news',
                'tax_filters'  => array('category:news', 'post_tag:featured', 'unknown:term'),
            ),
        );

        $metaboxes = My_Articles_Metaboxes::get_instance();
        $metaboxes->save_meta_data(777);

        $saved = $mon_articles_test_saved_meta[777]['_my_articles_settings'] ?? null;

        self::assertIsArray($saved, 'Expected sanitized settings to be stored.');
        self::assertArrayHasKey('tax_filters', $saved);
        self::assertSame(
            array(
                array('taxonomy' => 'category', 'slug' => 'news'),
                array('taxonomy' => 'post_tag', 'slug' => 'featured'),
            ),
            $saved['tax_filters'],
            'Unexpected sanitized taxonomy filters.'
        );

        $_POST = array();
        $mon_articles_test_taxonomies = array();
        $mon_articles_test_terms = array();
    }
}

}

