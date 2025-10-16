<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use My_Articles_Shortcode;
use My_Articles_Shortcode_Data_Preparer;
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
        $this->assertArrayHasKey('filters', $prepared['requested']);
        $this->assertSame(array(), $prepared['requested']['filters']);
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
        $this->assertSame(array(), $prepared['requested']['filters'] ?? null);
    }

    public function test_prepare_cache_key_includes_namespace_when_filters_change(): void
    {
        $instanceId = 777;

        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map,
            $mon_articles_test_wp_cache,
            $mon_articles_test_options,
            $mon_articles_test_options_store;

        $mon_articles_test_post_type_map[$instanceId]   = 'mon_affichage';
        $mon_articles_test_post_status_map[$instanceId] = 'publish';
        $mon_articles_test_post_meta_map[$instanceId]   = array(
            '_my_articles_settings' => array(
                'pagination_mode' => 'none',
                'display_mode'    => 'grid',
                'posts_per_page'  => 3,
            ),
        );

        $mon_articles_test_wp_cache = array();
        $mon_articles_test_options['my_articles_cache_namespace']       = 'alpha';
        $mon_articles_test_options_store['my_articles_cache_namespace'] = 'alpha';

        $shortcode = My_Articles_Shortcode::get_instance();
        $preparer  = $shortcode->get_data_preparer();

        My_Articles_Shortcode_Data_Preparer::reset_runtime_cache();

        $preparedAlpha = $preparer->prepare($instanceId);
        $this->assertIsArray($preparedAlpha);

        $cacheGroup = My_Articles_Shortcode_Data_Preparer::CACHE_GROUP;
        $this->assertSame(
            1,
            isset($mon_articles_test_wp_cache[$cacheGroup])
                ? count($mon_articles_test_wp_cache[$cacheGroup])
                : 0
        );

        $mon_articles_test_options['my_articles_cache_namespace']       = 'beta';
        $mon_articles_test_options_store['my_articles_cache_namespace'] = 'beta';

        My_Articles_Shortcode_Data_Preparer::reset_runtime_cache();

        $preparedBeta = $preparer->prepare($instanceId);
        $this->assertIsArray($preparedBeta);

        $this->assertSame(
            2,
            isset($mon_articles_test_wp_cache[$cacheGroup])
                ? count($mon_articles_test_wp_cache[$cacheGroup])
                : 0
        );
    }

    public function test_prepare_context_overrides_request_payload(): void
    {
        $instanceId = 8888;

        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map;

        $mon_articles_test_post_type_map[$instanceId]   = 'mon_affichage';
        $mon_articles_test_post_status_map[$instanceId] = 'publish';
        $mon_articles_test_post_meta_map[$instanceId]   = array(
            '_my_articles_settings' => array(
                'pagination_mode' => 'numbered',
                'display_mode'    => 'grid',
                'posts_per_page'  => 5,
                'show_category_filter' => 1,
            ),
        );

        $request = array(
            'my_articles_search_' . $instanceId => 'ignored value',
            'my_articles_sort_' . $instanceId   => 'menu_order',
            'paged_' . $instanceId              => '9',
            'my_articles_cat_' . $instanceId    => 'old-category',
        );

        $context = array(
            'search'   => '  Editorial Focus ',
            'sort'     => 'title',
            'page'     => 3,
            'category' => 'Featured Stories',
            'filters'  => array(
                array('taxonomy' => 'category', 'slug' => 'culture'),
            ),
        );

        $shortcode = My_Articles_Shortcode::get_instance();
        $preparer  = $shortcode->get_data_preparer();

        $prepared = $preparer->prepare(
            $instanceId,
            array(),
            array(
                'request'       => $request,
                'context'       => $context,
                'force_refresh' => true,
            )
        );

        $this->assertIsArray($prepared);

        $this->assertSame('Editorial Focus', $prepared['requested']['search']);
        $this->assertSame('title', $prepared['requested']['sort']);
        $this->assertSame(3, $prepared['requested']['page']);
        $this->assertSame('featured-stories', $prepared['requested']['category']);

        $filters = $prepared['requested']['filters'];
        $this->assertIsArray($filters);
        $this->assertCount(1, $filters);
        $this->assertSame(
            array('taxonomy' => 'category', 'slug' => 'culture'),
            $filters[0]
        );
    }

    public function test_prepare_context_can_force_term_collection(): void
    {
        $instanceId = 9999;

        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map;

        $mon_articles_test_post_type_map[$instanceId]   = 'mon_affichage';
        $mon_articles_test_post_status_map[$instanceId] = 'publish';
        $mon_articles_test_post_meta_map[$instanceId]   = array(
            '_my_articles_settings' => array(
                'display_mode'         => 'grid',
                'pagination_mode'      => 'load_more',
                'posts_per_page'       => 4,
                'show_category_filter' => 0,
            ),
        );

        $shortcode = My_Articles_Shortcode::get_instance();
        $preparer  = $shortcode->get_data_preparer();

        $prepared = $preparer->prepare(
            $instanceId,
            array(),
            array(
                'context' => array(
                    'force_collect_terms' => true,
                ),
                'force_refresh' => true,
            )
        );

        $this->assertIsArray($prepared);
        $this->assertTrue($prepared['normalize_context']['force_collect_terms'] ?? false);
    }

    public function test_prepare_cache_key_accounts_for_requested_filters(): void
    {
        $instanceId = 8899;

        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map,
            $mon_articles_test_wp_cache;

        $mon_articles_test_post_type_map[$instanceId]   = 'mon_affichage';
        $mon_articles_test_post_status_map[$instanceId] = 'publish';
        $mon_articles_test_post_meta_map[$instanceId]   = array(
            '_my_articles_settings' => array(
                'pagination_mode'      => 'none',
                'display_mode'         => 'grid',
                'posts_per_page'       => 4,
                'show_category_filter' => 1,
            ),
        );

        $mon_articles_test_wp_cache = array();

        $shortcode = My_Articles_Shortcode::get_instance();
        $preparer  = $shortcode->get_data_preparer();

        My_Articles_Shortcode_Data_Preparer::reset_runtime_cache();

        $first = $preparer->prepare(
            $instanceId,
            array(),
            array(
                'context'       => array(
                    'filters' => array(
                        array('taxonomy' => 'category', 'slug' => 'culture'),
                    ),
                ),
                'force_refresh' => true,
            )
        );

        $this->assertIsArray($first);
        $this->assertSame(
            array(
                array('taxonomy' => 'category', 'slug' => 'culture'),
            ),
            $first['requested']['filters']
        );

        $cacheGroup = My_Articles_Shortcode_Data_Preparer::CACHE_GROUP;
        $this->assertSame(1, isset($mon_articles_test_wp_cache[$cacheGroup]) ? count($mon_articles_test_wp_cache[$cacheGroup]) : 0);

        My_Articles_Shortcode_Data_Preparer::reset_runtime_cache();

        $second = $preparer->prepare(
            $instanceId,
            array(),
            array(
                'context'       => array(
                    'filters' => array(
                        array('taxonomy' => 'category', 'slug' => 'news'),
                    ),
                ),
                'force_refresh' => true,
            )
        );

        $this->assertIsArray($second);
        $this->assertSame(
            array(
                array('taxonomy' => 'category', 'slug' => 'news'),
            ),
            $second['requested']['filters']
        );

        $this->assertSame(2, isset($mon_articles_test_wp_cache[$cacheGroup]) ? count($mon_articles_test_wp_cache[$cacheGroup]) : 0);
    }

    public function test_prepare_cache_key_includes_namespace(): void
    {
        $instanceId = 777;

        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map,
            $mon_articles_test_wp_cache,
            $mon_articles_test_options,
            $mon_articles_test_options_store;

        $mon_articles_test_post_type_map[$instanceId]   = 'mon_affichage';
        $mon_articles_test_post_status_map[$instanceId] = 'publish';
        $mon_articles_test_post_meta_map[$instanceId]   = array(
            '_my_articles_settings' => array(
                'pagination_mode' => 'none',
                'display_mode'    => 'grid',
                'posts_per_page'  => 3,
            ),
        );

        $mon_articles_test_wp_cache = array();
        $mon_articles_test_options['my_articles_cache_namespace']       = 'alpha';
        $mon_articles_test_options_store['my_articles_cache_namespace'] = 'alpha';

        $shortcode = My_Articles_Shortcode::get_instance();
        $preparer  = $shortcode->get_data_preparer();

        My_Articles_Shortcode_Data_Preparer::reset_runtime_cache();

        $preparedAlpha = $preparer->prepare($instanceId);
        $this->assertIsArray($preparedAlpha);

        $cacheGroup = My_Articles_Shortcode_Data_Preparer::CACHE_GROUP;
        $this->assertSame(
            1,
            isset($mon_articles_test_wp_cache[$cacheGroup])
                ? count($mon_articles_test_wp_cache[$cacheGroup])
                : 0
        );

        $mon_articles_test_options['my_articles_cache_namespace']       = 'beta';
        $mon_articles_test_options_store['my_articles_cache_namespace'] = 'beta';

        My_Articles_Shortcode_Data_Preparer::reset_runtime_cache();

        $preparedBeta = $preparer->prepare($instanceId);
        $this->assertIsArray($preparedBeta);

        $this->assertSame(
            2,
            isset($mon_articles_test_wp_cache[$cacheGroup])
                ? count($mon_articles_test_wp_cache[$cacheGroup])
                : 0
        );
    }
}
