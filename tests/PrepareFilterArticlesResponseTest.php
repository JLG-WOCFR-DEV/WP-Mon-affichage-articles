<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use Mon_Affichage_Articles;
use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_Query;

final class PrepareFilterArticlesResponseTest extends TestCase
{
    /**
     * @var array<string, mixed>|null
     */
    private $previousGlobals = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousGlobals = array(
            'post_type_map'    => $GLOBALS['mon_articles_test_post_type_map'] ?? null,
            'post_status_map'  => $GLOBALS['mon_articles_test_post_status_map'] ?? null,
            'post_meta_map'    => $GLOBALS['mon_articles_test_post_meta_map'] ?? null,
            'options'          => $GLOBALS['mon_articles_test_options'] ?? null,
            'options_store'    => $GLOBALS['mon_articles_test_options_store'] ?? null,
            'wp_cache'         => $GLOBALS['mon_articles_test_wp_cache'] ?? null,
            'transients'       => $GLOBALS['mon_articles_test_transients'] ?? null,
            'transients_store' => $GLOBALS['mon_articles_test_transients_store'] ?? null,
            'filters'          => $GLOBALS['mon_articles_test_filters'] ?? null,
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
            $GLOBALS['mon_articles_test_filters'] = $this->previousGlobals['filters'];
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
        $GLOBALS['mon_articles_test_wp_cache'] = array();
        $GLOBALS['mon_articles_test_transients'] = array();
        $GLOBALS['mon_articles_test_transients_store'] = array();
    }

    private function createShortcodeDouble(array $stateOverrides = array()): My_Articles_Shortcode
    {
        return new class($stateOverrides) extends My_Articles_Shortcode {
            /** @var array<int, array<string, mixed>> */
            public $capturedArgs = array();

            /** @var array<string, mixed> */
            private $stateOverrides;

            /**
             * @param array<string, mixed> $stateOverrides
             */
            public function __construct(array $stateOverrides)
            {
                $this->stateOverrides = $stateOverrides;
            }

            public function build_display_state(array $options, array $args = array())
            {
                $this->capturedArgs[] = array(
                    'options' => $options,
                    'args'    => $args,
                );

                $state = array(
                    'pinned_query'             => new WP_Query(array()),
                    'regular_query'            => new WP_Query(array(
                        array('ID' => 101, 'post_title' => 'Article A'),
                        array('ID' => 102, 'post_title' => 'Article B'),
                    )),
                    'updated_seen_pinned_ids'  => array(),
                    'total_pinned_posts'       => 0,
                    'total_regular_posts'      => 2,
                    'effective_posts_per_page' => 2,
                    'render_limit'             => 2,
                    'regular_posts_needed'     => 2,
                    'is_unlimited'             => false,
                    'unlimited_batch_size'     => 0,
                );

                foreach ($this->stateOverrides as $key => $value) {
                    $state[$key] = $value;
                }

                if (!array_key_exists('unlimited_batch_size', $state)) {
                    $state['unlimited_batch_size'] = 0;
                }

                return $state;
            }

            public function render_article_item($options, $is_pinned = false)
            {
                echo '<article data-pinned="' . ($is_pinned ? '1' : '0') . '"></article>';
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

            public function get_numbered_pagination_html($total_pages, $current_page, $query_var, array $query_args, $referer = '')
            {
                return '<nav data-pages="' . (int) $total_pages . '"></nav>';
            }
        };
    }

    public function test_prepare_filter_response_applies_requested_sort(): void
    {
        $instanceId = 321;

        $settings = array(
            'post_type'            => 'post',
            'display_mode'         => 'list',
            'pagination_mode'      => 'numbered',
            'posts_per_page'       => 2,
            'order'                => 'DESC',
            'orderby'              => 'date',
            'search_query'         => '',
            'sort'                 => 'date',
            'show_category_filter' => 1,
            'enable_keyword_search'=> 1,
        );

        $this->primeInstanceMeta($instanceId, $settings);

        $shortcodeDouble = $this->createShortcodeDouble();
        $this->swapShortcodeInstance($shortcodeDouble);

        $plugin = new Mon_Affichage_Articles();

        $response = $plugin->prepare_filter_articles_response(array(
            'instance_id' => $instanceId,
            'category'    => 'actus',
            'search'      => ' Nouveautés ',
            'sort'        => ' COMMENT_Count ',
            'filters'     => array(),
            'current_url' => 'http://example.com/',
        ));

        $this->assertIsArray($response);
        $this->assertSame('comment_count', $response['sort']);
        $this->assertSame('nouveautes', $shortcodeDouble->capturedArgs[0]['options']['search_query']);
        $this->assertSame('comment_count', $shortcodeDouble->capturedArgs[0]['options']['sort']);
        $this->assertSame('comment_count', $response['sort']);
    }

    public function test_prepare_filter_response_sets_pagination_context_current_page(): void
    {
        $instanceId = 654;

        $settings = array(
            'post_type'            => 'post',
            'display_mode'         => 'list',
            'pagination_mode'      => 'numbered',
            'posts_per_page'       => 2,
            'show_category_filter' => 1,
        );

        $this->primeInstanceMeta($instanceId, $settings);

        $shortcodeDouble = $this->createShortcodeDouble();
        $this->swapShortcodeInstance($shortcodeDouble);

        $capturedContext = null;

        add_filter(
            'my_articles_calculate_total_pages',
            function ($result, $totalPinned, $totalRegular, $perPage, $context) use (&$capturedContext) {
                $capturedContext = $context;

                return $result;
            },
            10,
            5
        );

        $plugin = new Mon_Affichage_Articles();

        $response = $plugin->prepare_filter_articles_response(array(
            'instance_id' => $instanceId,
            'category'    => 'actus',
            'current_url' => 'http://example.com/',
        ));

        $this->assertIsArray($response);
        $this->assertNotNull($capturedContext, 'Expected the pagination calculation hook to capture context.');
        $this->assertSame(1, $capturedContext['current_page']);
    }

    public function test_prepare_filter_response_exposes_unlimited_batch_size_in_context(): void
    {
        $instanceId = 987;

        $settings = array(
            'post_type'            => 'post',
            'display_mode'         => 'grid',
            'pagination_mode'      => 'numbered',
            'posts_per_page'       => 0,
            'show_category_filter' => 1,
        );

        $this->primeInstanceMeta($instanceId, $settings);

        $shortcodeDouble = $this->createShortcodeDouble(
            array(
                'is_unlimited'             => true,
                'unlimited_batch_size'     => 7,
                'effective_posts_per_page' => 0,
            )
        );
        $this->swapShortcodeInstance($shortcodeDouble);

        $capturedContext = null;

        add_filter(
            'my_articles_calculate_total_pages',
            function ($result, $totalPinned, $totalRegular, $perPage, $context) use (&$capturedContext) {
                $capturedContext = $context;

                return $result;
            },
            10,
            5
        );

        $plugin = new Mon_Affichage_Articles();

        $response = $plugin->prepare_filter_articles_response(array(
            'instance_id' => $instanceId,
            'category'    => 'actus',
            'current_url' => 'http://example.com/',
        ));

        $this->assertIsArray($response);
        $this->assertNotNull($capturedContext, 'Expected unlimited pagination context to be captured.');
        $this->assertArrayHasKey('unlimited_page_size', $capturedContext);
        $this->assertArrayHasKey('analytics_page_size', $capturedContext);
        $this->assertSame(7, $capturedContext['unlimited_page_size']);
        $this->assertSame(7, $capturedContext['analytics_page_size']);
    }
}
