<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use Mon_Affichage_Articles;
use My_Articles_Display_State_Builder;
use My_Articles_Shortcode;
use My_Articles_Shortcode_Data_Preparer;
use ReflectionClass;
use WP_Query;
use WP_UnitTestCase;

final class DisplayStateBuilderTest extends WP_UnitTestCase
{
    /**
     * @var array<string, mixed>|null
     */
    private $previousGlobals = null;

    protected function setUp(): void
    {
        parent::setUp();

        Mon_Affichage_Articles::get_instance();
        My_Articles_Display_State_Builder::reset_runtime_cache();

        $this->previousGlobals = array(
            'options'       => $GLOBALS['mon_articles_test_options'] ?? null,
            'options_store' => $GLOBALS['mon_articles_test_options_store'] ?? null,
            'wp_cache'      => $GLOBALS['mon_articles_test_wp_cache'] ?? null,
            'query_factory' => $GLOBALS['mon_articles_test_wp_query_factory'] ?? null,
            'filters'       => $GLOBALS['mon_articles_test_filters'] ?? null,
            'post_types'    => $GLOBALS['mon_articles_test_post_type_map'] ?? null,
            'post_statuses' => $GLOBALS['mon_articles_test_post_status_map'] ?? null,
        );

        $GLOBALS['mon_articles_test_options'] = array('my_articles_cache_namespace' => 'buildertests');
        $GLOBALS['mon_articles_test_options_store'] = &$GLOBALS['mon_articles_test_options'];
        $GLOBALS['mon_articles_test_wp_cache'] = array();
        $GLOBALS['mon_articles_test_wp_query_factory'] = null;

        My_Articles_Shortcode_Data_Preparer::reset_runtime_cache();
        $this->resetShortcodeCaches();
    }

    protected function tearDown(): void
    {
        $this->resetShortcodeCaches();

        if (null !== $this->previousGlobals) {
            $GLOBALS['mon_articles_test_options'] = $this->previousGlobals['options'];
            $GLOBALS['mon_articles_test_options_store'] = $this->previousGlobals['options_store'];
            $GLOBALS['mon_articles_test_wp_cache'] = $this->previousGlobals['wp_cache'];
            $GLOBALS['mon_articles_test_wp_query_factory'] = $this->previousGlobals['query_factory'];
            $GLOBALS['mon_articles_test_filters'] = $this->previousGlobals['filters'];
            $GLOBALS['mon_articles_test_post_type_map'] = $this->previousGlobals['post_types'];
            $GLOBALS['mon_articles_test_post_status_map'] = $this->previousGlobals['post_statuses'];
        }

        My_Articles_Display_State_Builder::reset_runtime_cache();
        parent::tearDown();
    }

    public function test_builder_uses_cached_state_when_available(): void
    {
        $pinnedId  = self::factory()->post->create();
        $regularId = self::factory()->post->create();

        $GLOBALS['mon_articles_test_post_type_map'] = array(
            $pinnedId  => 'post',
            $regularId => 'post',
        );

        $GLOBALS['mon_articles_test_post_status_map'] = array(
            $pinnedId  => 'publish',
            $regularId => 'publish',
        );

        $pinnedPosts  = array(array('ID' => $pinnedId));
        $regularPosts = array(array('ID' => $regularId));

        $callCount = 0;
        $GLOBALS['mon_articles_test_wp_query_factory'] = function (array $args) use (&$callCount, $pinnedPosts, $regularPosts) {
            $callCount++;

            if (isset($args['fields']) && 'ids' === $args['fields']) {
                return array(
                    'posts' => array_map(static function ($post) {
                        return $post['ID'];
                    }, $pinnedPosts),
                );
            }

            if (isset($args['post__in'])) {
                return array(
                    'posts'       => $pinnedPosts,
                    'found_posts' => count($pinnedPosts),
                );
            }

            return array(
                'posts'       => $regularPosts,
                'found_posts' => count($regularPosts),
            );
        };

        $options  = $this->buildOptions(101, array($pinnedId));
        $shortcode = My_Articles_Shortcode::get_instance();

        $builder = new My_Articles_Display_State_Builder($shortcode, $options, array('paged' => 1));
        $state   = $builder->build();

        self::assertGreaterThanOrEqual(2, $callCount);
        self::assertInstanceOf(WP_Query::class, $state['regular_query']);
        self::assertInstanceOf(WP_Query::class, $state['pinned_query']);

        $this->resetShortcodeCaches();
        My_Articles_Display_State_Builder::reset_runtime_cache();

        $callCount = 0;
        $GLOBALS['mon_articles_test_wp_query_factory'] = function () use (&$callCount) {
            $callCount++;
            return array('posts' => array(), 'found_posts' => 0);
        };

        $builder = new My_Articles_Display_State_Builder($shortcode, $options, array('paged' => 1));
        $cached  = $builder->build();

        self::assertSame(0, $callCount, 'Expected cached payload to avoid new queries');
        self::assertSame($state['rendered_pinned_ids'], $cached['rendered_pinned_ids']);
    }

    public function test_builder_accepts_external_results_from_filter(): void
    {
        $captured = 0;
        $GLOBALS['mon_articles_test_wp_query_factory'] = function () use (&$captured) {
            $captured++;
            return array('posts' => array(), 'found_posts' => 0);
        };

        $pinnedPosts = array(array('ID' => 501, 'post_title' => 'Pinned'));
        $regularPosts = array(array('ID' => 601, 'post_title' => 'Regular'));

        $callback = static function ($value, array $options) use ($pinnedPosts, $regularPosts) {
            return array(
                'pinned_query'             => new WP_Query($pinnedPosts),
                'regular_query'            => new WP_Query($regularPosts),
                'rendered_pinned_ids'      => array_map('absint', wp_list_pluck($pinnedPosts, 'ID')),
                'total_pinned_posts'       => count($pinnedPosts),
                'total_regular_posts'      => count($regularPosts),
                'effective_posts_per_page' => $options['posts_per_page'] ?? 0,
            );
        };

        add_filter('my_articles_display_state_external_results', $callback, 10, 2);

        $options   = $this->buildOptions(202, array(501));
        $shortcode = My_Articles_Shortcode::get_instance();
        $builder   = new My_Articles_Display_State_Builder($shortcode, $options, array('paged' => 1));

        $state = $builder->build();

        self::assertSame(0, $captured, 'Expected no query execution when results are provided externally');
        self::assertSame(array(501), $state['rendered_pinned_ids']);
        self::assertInstanceOf(WP_Query::class, $state['regular_query']);
        $firstPost = $state['regular_query']->posts[0] ?? null;
        if (is_array($firstPost)) {
            $retrievedId = $firstPost['ID'] ?? 0;
        } elseif (is_object($firstPost) && property_exists($firstPost, 'ID')) {
            $retrievedId = $firstPost->ID;
        } else {
            $retrievedId = 0;
        }

        self::assertSame(601, (int) $retrievedId);
    }

    public function test_cache_is_invalidated_when_namespace_changes(): void
    {
        $pinnedId  = self::factory()->post->create();
        $regularId = self::factory()->post->create();

        $pinnedPosts  = array(array('ID' => $pinnedId));
        $regularPosts = array(array('ID' => $regularId));

        $GLOBALS['mon_articles_test_post_type_map'] = array(
            $pinnedId  => 'post',
            $regularId => 'post',
        );

        $GLOBALS['mon_articles_test_post_status_map'] = array(
            $pinnedId  => 'publish',
            $regularId => 'publish',
        );

        $callCount = 0;
        $GLOBALS['mon_articles_test_wp_query_factory'] = function (array $args) use (&$callCount, $pinnedPosts, $regularPosts) {
            $callCount++;

            if (isset($args['fields']) && 'ids' === $args['fields']) {
                return array('posts' => array($pinnedPosts[0]['ID']));
            }

            if (isset($args['post__in'])) {
                return array('posts' => $pinnedPosts, 'found_posts' => 1);
            }

            return array('posts' => $regularPosts, 'found_posts' => 1);
        };

        $options   = $this->buildOptions(303, array($pinnedId));
        $shortcode = My_Articles_Shortcode::get_instance();

        $builder = new My_Articles_Display_State_Builder($shortcode, $options, array('paged' => 1));
        $builder->build();
        self::assertSame(3, $callCount);

        $GLOBALS['mon_articles_test_options']['my_articles_cache_namespace'] = 'buildertests_new';
        $this->resetShortcodeCaches();
        My_Articles_Display_State_Builder::reset_runtime_cache();

        $callCount = 0;
        $GLOBALS['mon_articles_test_wp_query_factory'] = function (array $args) use (&$callCount, $pinnedPosts, $regularPosts) {
            $callCount++;

            if (isset($args['fields']) && 'ids' === $args['fields']) {
                return array('posts' => array($pinnedPosts[0]['ID']));
            }

            if (isset($args['post__in'])) {
                return array('posts' => $pinnedPosts, 'found_posts' => 1);
            }

            return array('posts' => $regularPosts, 'found_posts' => 1);
        };

        self::assertSame('buildertests_new', get_option('my_articles_cache_namespace'));

        $builder = new My_Articles_Display_State_Builder($shortcode, $options, array('paged' => 1));
        $builder->build();

        self::assertGreaterThan(0, $callCount, 'Cache should not be reused after namespace refresh');
    }

    /**
     * @param int   $instanceId
     * @param array<int, int> $pinnedIds
     * @return array<string, mixed>
     */
    private function buildOptions(int $instanceId, array $pinnedIds): array
    {
        return array(
            'instance_id'              => $instanceId,
            'post_type'                => 'post',
            'posts_per_page'           => 2,
            'is_unlimited'             => false,
            'unlimited_query_cap'      => 5,
            'display_mode'             => 'grid',
            'pagination_mode'          => 'load_more',
            'ignore_native_sticky'     => 1,
            'all_excluded_ids'         => array(),
            'orderby'                  => 'date',
            'order'                    => 'DESC',
            'search_query'             => '',
            'meta_key'                 => '',
            'resolved_taxonomy'        => '',
            'term'                     => '',
            'active_tax_filters'       => array(),
            'meta_query'               => array(),
            'meta_query_relation'      => 'AND',
            'content_adapters'         => array(),
            'exclude_post_ids'         => array(),
            'pinned_posts'             => $pinnedIds,
            'pinned_posts_ignore_filter' => 0,
            'requested_filters'        => array(),
        );
    }

    private function resetShortcodeCaches(): void
    {
        $reflection = new ReflectionClass(My_Articles_Shortcode::class);

        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, null);

        $normalizedProperty = $reflection->getProperty('normalized_options_cache');
        $normalizedProperty->setAccessible(true);
        $normalizedProperty->setValue(null, array());

        $matchingProperty = $reflection->getProperty('matching_pinned_ids_cache');
        $matchingProperty->setAccessible(true);
        $matchingProperty->setValue(null, array());
    }
}
