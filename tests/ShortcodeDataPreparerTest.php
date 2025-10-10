<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;

final class ShortcodeDataPreparerTest extends TestCase
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
            $mon_articles_test_options,
            $mon_articles_test_options_store,
            $mon_articles_test_wp_cache,
            $mon_articles_test_transients,
            $mon_articles_test_transients_store;

        $mon_articles_test_post_type_map   = array();
        $mon_articles_test_post_status_map = array();
        $mon_articles_test_post_meta_map   = array();
        $mon_articles_test_options         = array('my_articles_cache_namespace' => 'tests');
        $mon_articles_test_options_store   = $mon_articles_test_options;
        $mon_articles_test_options_store   =& $mon_articles_test_options;
        $mon_articles_test_wp_cache        = array();
        $mon_articles_test_transients      = array();
        $mon_articles_test_transients_store = array();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_GET = array();
    }

    public function test_prepare_includes_sanitized_page_from_request(): void
    {
        $instanceId = 321;

        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map;

        $mon_articles_test_post_type_map[$instanceId]   = 'mon_affichage';
        $mon_articles_test_post_status_map[$instanceId] = 'publish';
        $mon_articles_test_post_meta_map[$instanceId]   = array(
            '_my_articles_settings' => array(
                'pagination_mode'      => 'load_more',
                'display_mode'         => 'grid',
                'posts_per_page'       => 6,
                'show_category_filter' => 1,
            ),
        );

        $shortcode = My_Articles_Shortcode::get_instance();
        $preparer  = $shortcode->get_data_preparer();

        $request = array(
            'paged_' . $instanceId => '7',
            'my_articles_sort_' . $instanceId => 'date',
        );

        $prepared = $preparer->prepare(
            $instanceId,
            array(),
            array(
                'request'       => $request,
                'force_refresh' => true,
            )
        );

        $this->assertIsArray($prepared);
        $this->assertSame(7, $prepared['requested']['page'] ?? null);
        $this->assertSame(
            'paged_' . $instanceId,
            $prepared['request_query_vars']['paged'] ?? null
        );
    }

    public function test_prepare_defaults_page_to_one_when_missing_or_invalid(): void
    {
        $instanceId = 654;

        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map;

        $mon_articles_test_post_type_map[$instanceId]   = 'mon_affichage';
        $mon_articles_test_post_status_map[$instanceId] = 'publish';
        $mon_articles_test_post_meta_map[$instanceId]   = array(
            '_my_articles_settings' => array(
                'pagination_mode' => 'numbered',
                'display_mode'    => 'grid',
                'posts_per_page'  => 4,
            ),
        );

        $shortcode = My_Articles_Shortcode::get_instance();
        $preparer  = $shortcode->get_data_preparer();

        $prepared = $preparer->prepare(
            $instanceId,
            array(),
            array(
                'request'       => array('paged_' . $instanceId => '0'),
                'force_refresh' => true,
            )
        );

        $this->assertIsArray($prepared);
        $this->assertSame(1, $prepared['requested']['page'] ?? null);
    }
}
