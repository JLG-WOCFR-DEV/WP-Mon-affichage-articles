<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;

final class MonAffichageArticlesCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map,
            $mon_articles_test_filters;

        $mon_articles_test_post_type_map   = array();
        $mon_articles_test_post_status_map = array();
        $mon_articles_test_post_meta_map   = array();
        $mon_articles_test_filters         = array();
    }

    public function test_debug_fragment_reflects_instance_setting(): void
    {
        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map,
            $mon_articles_test_filters;

        $instance_id = 987;

        $mon_articles_test_post_type_map[$instance_id]   = 'mon_affichage';
        $mon_articles_test_post_status_map[$instance_id] = 'publish';

        $defaults = My_Articles_Shortcode::get_default_options();
        $defaults['pagination_mode']   = 'load_more';
        $defaults['enable_debug_mode'] = 1;
        $defaults['post_type']         = 'post';

        $mon_articles_test_post_meta_map[$instance_id] = array(
            '_my_articles_settings' => $defaults,
        );

        $captured = null;
        add_filter(
            'my_articles_cache_fragments',
            static function ($fragments) use (&$captured) {
                $captured = $fragments;

                return $fragments;
            },
            10,
            1
        );

        $plugin = my_articles_plugin_run();

        $response = $plugin->prepare_filter_articles_response(
            array(
                'instance_id' => $instance_id,
            )
        );

        self::assertIsArray($response);
        self::assertIsArray($captured);
        self::assertArrayHasKey('debug', $captured);
        self::assertSame('1', $captured['debug']);

        $mon_articles_test_post_meta_map[$instance_id]['_my_articles_settings']['enable_debug_mode'] = 0;
        $captured = null;

        $response = $plugin->prepare_filter_articles_response(
            array(
                'instance_id' => $instance_id,
            )
        );

        self::assertIsArray($response);
        self::assertIsArray($captured);
        self::assertArrayHasKey('debug', $captured);
        self::assertSame('0', $captured['debug']);

        $mon_articles_test_filters = array();
    }
}
