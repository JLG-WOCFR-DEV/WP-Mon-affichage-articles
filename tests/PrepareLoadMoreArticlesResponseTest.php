<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use Mon_Affichage_Articles;
use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use \WP_Query;

final class PrepareLoadMoreArticlesResponseTest extends TestCase
{
    /**
     * @var array<string, mixed>|null
     */
    private $previousGlobals = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousGlobals = array(
            'post_type_map'         => $GLOBALS['mon_articles_test_post_type_map'] ?? null,
            'post_status_map'       => $GLOBALS['mon_articles_test_post_status_map'] ?? null,
            'post_meta_map'         => $GLOBALS['mon_articles_test_post_meta_map'] ?? null,
            'options'               => $GLOBALS['mon_articles_test_options'] ?? null,
            'options_store'         => $GLOBALS['mon_articles_test_options_store'] ?? null,
            'wp_cache'              => $GLOBALS['mon_articles_test_wp_cache'] ?? null,
            'transients'            => $GLOBALS['mon_articles_test_transients'] ?? null,
            'transients_store'      => $GLOBALS['mon_articles_test_transients_store'] ?? null,
        );
    }

    protected function tearDown(): void
    {
        $reflection = new ReflectionClass(My_Articles_Shortcode::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, $this->previousGlobals['shortcode_instance'] ?? null);

        $normalizedProperty = $reflection->getProperty('normalized_options_cache');
        $normalizedProperty->setAccessible(true);
        if (array_key_exists('normalized_cache', $this->previousGlobals)) {
            $normalizedProperty->setValue(null, $this->previousGlobals['normalized_cache']);
        }

        $matchingProperty = $reflection->getProperty('matching_pinned_ids_cache');
        $matchingProperty->setAccessible(true);
        if (array_key_exists('matching_cache', $this->previousGlobals)) {
            $matchingProperty->setValue(null, $this->previousGlobals['matching_cache']);
        }

        if (null !== $this->previousGlobals) {
            $GLOBALS['mon_articles_test_post_type_map'] = $this->previousGlobals['post_type_map'];
            $GLOBALS['mon_articles_test_post_status_map'] = $this->previousGlobals['post_status_map'];
            $GLOBALS['mon_articles_test_post_meta_map'] = $this->previousGlobals['post_meta_map'];
            $GLOBALS['mon_articles_test_options'] = $this->previousGlobals['options'];
            $GLOBALS['mon_articles_test_options_store'] = $this->previousGlobals['options_store'];
            $GLOBALS['mon_articles_test_wp_cache'] = $this->previousGlobals['wp_cache'];
            $GLOBALS['mon_articles_test_transients'] = $this->previousGlobals['transients'];
            $GLOBALS['mon_articles_test_transients_store'] = $this->previousGlobals['transients_store'];
        }

        parent::tearDown();
    }

    private function swapShortcodeInstance(My_Articles_Shortcode $double): void
    {
        $reflection = new ReflectionClass(My_Articles_Shortcode::class);

        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $this->previousGlobals['shortcode_instance'] = $instanceProperty->getValue();
        $instanceProperty->setValue(null, $double);

        $normalizedProperty = $reflection->getProperty('normalized_options_cache');
        $normalizedProperty->setAccessible(true);
        $this->previousGlobals['normalized_cache'] = $normalizedProperty->getValue();
        $normalizedProperty->setValue(null, array());

        $matchingProperty = $reflection->getProperty('matching_pinned_ids_cache');
        $matchingProperty->setAccessible(true);
        $this->previousGlobals['matching_cache'] = $matchingProperty->getValue();
        $matchingProperty->setValue(null, array());
    }

    private function createShortcodeDouble(array $pinnedPosts, array $regularPosts, array $stateOverrides = array()): My_Articles_Shortcode
    {
        return new class($pinnedPosts, $regularPosts, $stateOverrides) extends My_Articles_Shortcode {
            /** @var array<int, array<string, mixed>> */
            private $pinnedPosts;

            /** @var array<int, array<string, mixed>> */
            private $regularPosts;

            /** @var array<string, mixed> */
            private $stateOverrides;

            /** @var array<int, mixed> */
            public $capturedArgs = array();

            /** @var array<int, array<string, mixed>> */
            public $renderedSequence = array();

            public function __construct(array $pinnedPosts, array $regularPosts, array $stateOverrides)
            {
                $this->pinnedPosts = $pinnedPosts;
                $this->regularPosts = $regularPosts;
                $this->stateOverrides = $stateOverrides;
            }

            public function render_article_item($options, $is_pinned = false)
            {
                $this->renderedSequence[] = array(
                    'id' => get_the_ID(),
                    'is_pinned' => $is_pinned,
                );

                echo '<article data-id="' . esc_attr((string) get_the_ID()) . '" data-pinned="' . ($is_pinned ? '1' : '0') . '"></article>';
            }

            public function get_skeleton_placeholder_markup($container_class, $options, $render_limit)
            {
                return '';
            }

            public function get_empty_state_html()
            {
                return '<div class="empty">Aucun article</div>';
            }

            public function get_empty_state_slide_html()
            {
                return '<div class="empty-slide">Aucun article</div>';
            }

            public function build_display_state(array $options, array $args = array())
            {
                $this->capturedArgs[] = array(
                    'options' => $options,
                    'args'    => $args,
                );

                return array(
                    'pinned_query'             => new WP_Query($this->pinnedPosts),
                    'regular_query'            => new WP_Query($this->regularPosts),
                    'updated_seen_pinned_ids'  => $this->stateOverrides['updated_seen_pinned_ids'] ?? array(),
                    'total_pinned_posts'       => $this->stateOverrides['total_pinned_posts'] ?? count($this->pinnedPosts),
                    'total_regular_posts'      => $this->stateOverrides['total_regular_posts'] ?? count($this->regularPosts),
                    'effective_posts_per_page' => $this->stateOverrides['effective_posts_per_page'] ?? ($options['posts_per_page'] ?? 0),
                    'is_unlimited'             => $this->stateOverrides['is_unlimited'] ?? false,
                    'unlimited_batch_size'     => $this->stateOverrides['unlimited_batch_size'] ?? 0,
                );
            }
        };
    }

    private function primeInstanceMeta(int $instanceId, array $settings): void
    {
        $GLOBALS['mon_articles_test_post_type_map'] = array($instanceId => 'mon_affichage');
        $GLOBALS['mon_articles_test_post_status_map'] = array($instanceId => 'publish');
        $GLOBALS['mon_articles_test_post_meta_map'] = array(
            $instanceId => array(
                '_my_articles_settings' => $settings,
            ),
        );

        $GLOBALS['mon_articles_test_options'] = array('my_articles_cache_namespace' => 'tests');
        $GLOBALS['mon_articles_test_options_store'] = $GLOBALS['mon_articles_test_options'];
        $GLOBALS['mon_articles_test_options_store'] =& $GLOBALS['mon_articles_test_options'];
        $GLOBALS['mon_articles_test_wp_cache'] = array();
        $GLOBALS['mon_articles_test_transients'] = array();
        $GLOBALS['mon_articles_test_transients_store'] = array();
    }

    public function test_prepare_load_more_response_returns_rendered_payload(): void
    {
        $instanceId = 777;

        $settings = array(
            'post_type'            => 'post',
            'display_mode'         => 'grid',
            'pagination_mode'      => 'load_more',
            'posts_per_page'       => 2,
            'order'                => 'DESC',
            'orderby'              => 'date',
            'search_query'         => '',
            'sort'                 => 'date',
            'pinned_posts'         => array(501),
            'ignore_native_sticky' => 1,
            'show_category'        => 0,
            'show_author'          => 0,
            'show_date'            => 0,
            'enable_keyword_search'=> 0,
            'is_unlimited'         => 0,
        );

        $this->primeInstanceMeta($instanceId, $settings);

        $shortcodeDouble = $this->createShortcodeDouble(
            array(
                array('ID' => 501, 'post_title' => 'Pinned'),
            ),
            array(
                array('ID' => 601, 'post_title' => 'First'),
                array('ID' => 602, 'post_title' => 'Second'),
            ),
            array(
                'updated_seen_pinned_ids'  => array(501),
                'total_pinned_posts'       => 1,
                'total_regular_posts'      => 5,
                'effective_posts_per_page' => 2,
            )
        );

        $this->swapShortcodeInstance($shortcodeDouble);

        $plugin = new Mon_Affichage_Articles();

        $response = $plugin->prepare_load_more_articles_response(array(
            'instance_id' => $instanceId,
            'paged'       => 2,
            'pinned_ids'  => '501',
        ));

        $this->assertIsArray($response);
        $this->assertSame('501', $response['pinned_ids']);
        $this->assertSame('date', $response['sort']);
        $this->assertSame(1, $response['total_pinned']);
        $this->assertSame(5, $response['total_regular']);
        $this->assertSame(6, $response['total_results']);
        $this->assertSame(3, $response['displayed_count']);
        $this->assertSame(3, $response['added_count']);
        $this->assertStringContainsString('data-id="501"', $response['html']);
        $this->assertStringContainsString('data-id="601"', $response['html']);
        $this->assertStringContainsString('data-id="602"', $response['html']);

        $expectedTotals = \my_articles_calculate_total_pages(1, 5, 2, array('current_page' => 2));
        $this->assertSame($expectedTotals['total_pages'], $response['total_pages']);
        $this->assertSame($expectedTotals['next_page'], $response['next_page']);
    }

    public function test_prepare_load_more_response_sanitizes_seen_pinned_ids(): void
    {
        $instanceId = 888;

        $settings = array(
            'post_type'            => 'post',
            'display_mode'         => 'list',
            'pagination_mode'      => 'load_more',
            'posts_per_page'       => 3,
            'order'                => 'ASC',
            'orderby'              => 'title',
            'search_query'         => '',
            'sort'                 => 'title',
            'pinned_posts'         => array(900, 901),
            'ignore_native_sticky' => 1,
            'show_category'        => 0,
            'show_author'          => 0,
            'show_date'            => 0,
            'enable_keyword_search'=> 0,
            'is_unlimited'         => 0,
        );

        $this->primeInstanceMeta($instanceId, $settings);

        $shortcodeDouble = $this->createShortcodeDouble(
            array(),
            array(
                array('ID' => 910, 'post_title' => 'Gamma'),
            ),
            array(
                'updated_seen_pinned_ids'  => array(900, 901, 910),
                'total_pinned_posts'       => 2,
                'total_regular_posts'      => 7,
                'effective_posts_per_page' => 3,
            )
        );

        $this->swapShortcodeInstance($shortcodeDouble);

        $plugin = new Mon_Affichage_Articles();

        $response = $plugin->prepare_load_more_articles_response(array(
            'instance_id' => $instanceId,
            'paged'       => 3,
            'pinned_ids'  => ' 900,foo, 901 , 901 ',
        ));

        $this->assertIsArray($response);
        $this->assertSame('900,901,910', $response['pinned_ids']);
        $this->assertSame(2, $response['total_pinned']);
        $this->assertSame(7, $response['total_regular']);

        $this->assertNotEmpty($shortcodeDouble->capturedArgs);
        $captured = array_pop($shortcodeDouble->capturedArgs);
        $this->assertSame(array(900, 901), $captured['args']['seen_pinned_ids']);
        $this->assertSame('sequential', $captured['args']['pagination_strategy']);
    }

    public function test_prepare_load_more_response_cache_keys_do_not_collide_for_search_and_pinned_ids(): void
    {
        $instanceId = 4242;

        $settings = array(
            'post_type'            => 'post',
            'display_mode'         => 'grid',
            'pagination_mode'      => 'load_more',
            'posts_per_page'       => 2,
            'order'                => 'DESC',
            'orderby'              => 'date',
            'search_query'         => '',
            'sort'                 => 'date',
            'pinned_posts'         => array(),
            'ignore_native_sticky' => 1,
            'enable_keyword_search'=> 1,
            'is_unlimited'         => 0,
        );

        $this->primeInstanceMeta($instanceId, $settings);

        $shortcodeDouble = $this->createShortcodeDouble(
            array(),
            array(
                array('ID' => 3101, 'post_title' => 'Alpha'),
            ),
            array(
                'total_pinned_posts'       => 0,
                'total_regular_posts'      => 4,
                'effective_posts_per_page' => 2,
                'updated_seen_pinned_ids'  => array(),
            )
        );

        $this->swapShortcodeInstance($shortcodeDouble);

        $plugin = new Mon_Affichage_Articles();

        $searchResponse = $plugin->prepare_load_more_articles_response(array(
            'instance_id' => $instanceId,
            'paged'       => 2,
            'search'      => '123',
        ));

        $pinnedResponse = $plugin->prepare_load_more_articles_response(array(
            'instance_id' => $instanceId,
            'paged'       => 2,
            'pinned_ids'  => '123',
        ));

        $this->assertIsArray($searchResponse);
        $this->assertIsArray($pinnedResponse);
        $this->assertSame('123', $searchResponse['search_query']);
        $this->assertSame('123', $pinnedResponse['pinned_ids']);

        $cacheGroup = $GLOBALS['mon_articles_test_wp_cache']['my_articles_response'] ?? array();

        $this->assertCount(2, $cacheGroup);
        $this->assertSame(
            array_keys($cacheGroup),
            array_values(array_unique(array_keys($cacheGroup))),
            'Expected cache keys to be unique for search vs pinned fragments.'
        );
    }
}
