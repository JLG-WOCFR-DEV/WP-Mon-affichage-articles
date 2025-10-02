<?php

declare(strict_types=1);

namespace {

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $die = true): void
    {
        // No-op for tests.
    }
}

if (!class_exists('MyArticlesJsonResponse')) {
    class MyArticlesJsonResponse extends \RuntimeException
    {
        /** @var bool */
        public $success;

        /** @var array<string, mixed> */
        public array $data;

        /** @var int|null */
        public $status_code;

        /**
         * @param array<string, mixed> $data
         */
        public function __construct(bool $success, array $data, ?int $status_code)
        {
            parent::__construct('JSON response emitted.');

            $this->success = $success;
            $this->data = $data;
            $this->status_code = $status_code;
        }
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null): void
    {
        $payload = is_array($data) ? $data : array();
        throw new MyArticlesJsonResponse(true, $payload, is_int($status_code) ? $status_code : 200);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null): void
    {
        $payload = is_array($data) ? $data : array();
        throw new MyArticlesJsonResponse(false, $payload, is_int($status_code) ? $status_code : 400);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title)
    {
        $title = strtolower((string) $title);
        $title = preg_replace('/[^a-z0-9-_]+/', '-', $title);

        return trim((string) $title, '-');
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }

        $filtered = strip_tags((string) $value);

        return preg_replace('/[\r\n\t\0\x0B]+/', '', $filtered);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value)
    {
        return $value;
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '', $scheme = null)
    {
        return 'http://example.com' . ('' !== $path ? '/' . ltrim((string) $path, '/') : '');
    }
}

if (!function_exists('wp_get_referer')) {
    function wp_get_referer()
    {
        return 'http://example.com/referer';
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url)
    {
        return (string) $url;
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1)
    {
        return parse_url((string) $url, $component);
    }
}

if (!function_exists('maybe_serialize')) {
    function maybe_serialize($data)
    {
        if (is_array($data) || is_object($data)) {
            return serialize($data);
        }

        if (is_scalar($data) || null === $data) {
            return $data;
        }

        return serialize($data);
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post = null)
    {
        global $mon_articles_test_post_type_map;

        if (!is_array($mon_articles_test_post_type_map)) {
            return null;
        }

        $post_id = is_numeric($post) ? (int) $post : 0;

        return $mon_articles_test_post_type_map[$post_id] ?? null;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false)
    {
        global $mon_articles_test_post_meta_map;

        if (!is_array($mon_articles_test_post_meta_map)) {
            return $single ? '' : array();
        }

        $post_id = (int) $post_id;

        if (!isset($mon_articles_test_post_meta_map[$post_id])) {
            return $single ? '' : array();
        }

        if ('' === $key) {
            return $mon_articles_test_post_meta_map[$post_id];
        }

        $value = $mon_articles_test_post_meta_map[$post_id][$key] ?? ($single ? '' : array());

        return $value;
    }
}

if (!function_exists('taxonomy_exists')) {
    function taxonomy_exists($taxonomy)
    {
        return false;
    }
}

if (!function_exists('is_object_in_taxonomy')) {
    function is_object_in_taxonomy($object_type, $taxonomy)
    {
        return false;
    }
}

}

namespace MonAffichageArticles\Tests {

use LCV\MonAffichage\My_Articles_Shortcode;
use Mon_Affichage_Articles;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_Query;

/**
 * @covers Mon_Affichage_Articles::load_more_articles_callback
 */
final class LoadMoreArticlesCallbackTest extends TestCase
{
    /** @var mixed */
    private $shortcodeInstanceBackup;

    /** @var array<string, mixed> */
    private array $normalizedOptionsCacheBackup = array();

    /** @var array<string, mixed> */
    private array $matchingPinnedCacheBackup = array();

    /** @var callable|null */
    private $wpQueryFactoryBackup;

    protected function setUp(): void
    {
        parent::setUp();

        global $mon_articles_test_post_meta_map, $mon_articles_test_post_type_map, $mon_articles_test_post_status_map, $mon_articles_test_wp_query_factory;

        $mon_articles_test_post_meta_map = array();
        $mon_articles_test_post_type_map = array();
        $mon_articles_test_post_status_map = array();
        $this->wpQueryFactoryBackup = $mon_articles_test_wp_query_factory ?? null;
        $mon_articles_test_wp_query_factory = null;

        $reflection = new ReflectionClass(My_Articles_Shortcode::class);

        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $this->shortcodeInstanceBackup = $instanceProperty->getValue();
        $instanceProperty->setValue(null, null);

        $normalizedProperty = $reflection->getProperty('normalized_options_cache');
        $normalizedProperty->setAccessible(true);
        $this->normalizedOptionsCacheBackup = $normalizedProperty->getValue();
        $normalizedProperty->setValue(null, array());

        $matchingProperty = $reflection->getProperty('matching_pinned_ids_cache');
        $matchingProperty->setAccessible(true);
        $this->matchingPinnedCacheBackup = $matchingProperty->getValue();
        $matchingProperty->setValue(null, array());
    }

    protected function tearDown(): void
    {
        global $mon_articles_test_post_meta_map, $mon_articles_test_post_type_map, $mon_articles_test_post_status_map, $mon_articles_test_wp_query_factory;

        $reflection = new ReflectionClass(My_Articles_Shortcode::class);

        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, $this->shortcodeInstanceBackup);

        $normalizedProperty = $reflection->getProperty('normalized_options_cache');
        $normalizedProperty->setAccessible(true);
        $normalizedProperty->setValue(null, $this->normalizedOptionsCacheBackup);

        $matchingProperty = $reflection->getProperty('matching_pinned_ids_cache');
        $matchingProperty->setAccessible(true);
        $matchingProperty->setValue(null, $this->matchingPinnedCacheBackup);

        $mon_articles_test_post_meta_map = array();
        $mon_articles_test_post_type_map = array();
        $mon_articles_test_post_status_map = array();
        $mon_articles_test_wp_query_factory = $this->wpQueryFactoryBackup;

        $_POST = array();

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $rawOptions
     * @param array<string, mixed> $normalizedOptions
     */
    private function seedInstanceOptions(int $instanceId, array $rawOptions, array $normalizedOptions, string $requestedCategory): void
    {
        global $mon_articles_test_post_meta_map, $mon_articles_test_post_type_map, $mon_articles_test_post_status_map;

        $mon_articles_test_post_meta_map[$instanceId] = array(
            '_my_articles_settings' => $rawOptions,
        );

        $mon_articles_test_post_type_map[$instanceId] = 'mon_affichage';
        $mon_articles_test_post_status_map[$instanceId] = 'publish';

        $reflection = new ReflectionClass(My_Articles_Shortcode::class);
        $cacheKeyMethod = $reflection->getMethod('build_normalized_options_cache_key');
        $cacheKeyMethod->setAccessible(true);

        $context = array(
            'requested_category' => $requestedCategory,
            'force_collect_terms' => true,
        );

        $cacheKey = $cacheKeyMethod->invoke(null, $rawOptions, $context);

        $normalizedProperty = $reflection->getProperty('normalized_options_cache');
        $normalizedProperty->setAccessible(true);
        $cache = $normalizedProperty->getValue();
        $cache[$cacheKey] = $normalizedOptions;
        $normalizedProperty->setValue(null, $cache);
    }

    private function setShortcodeInstance(object $instance): void
    {
        $reflection = new ReflectionClass(My_Articles_Shortcode::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, $instance);
    }

    public function test_load_more_returns_next_page_payload(): void
    {
        $instanceId = 321;
        $perPage = 3;

        $rawOptions = array(
            'display_mode' => 'grid',
            'posts_per_page' => $perPage,
            'post_type' => 'post',
            'pagination_mode' => 'load_more',
        );

        $normalizedOptions = array(
            'display_mode' => 'grid',
            'resolved_taxonomy' => '',
            'default_term' => '',
            'term' => '',
            'posts_per_page' => $perPage,
            'is_unlimited' => false,
            'pagination_mode' => 'load_more',
            'post_type' => 'post',
            'ignore_native_sticky' => 0,
            'unlimited_query_cap' => $perPage,
            'show_category_filter' => 0,
            'filter_categories' => array(),
            'all_excluded_ids' => array(),
            'allowed_filter_term_slugs' => array(),
            'is_requested_category_valid' => true,
        );

        $requestedCategory = 'categorie-test';
        $this->seedInstanceOptions($instanceId, $rawOptions, $normalizedOptions, $requestedCategory);

        $pinnedPosts = array(
            array('ID' => 101),
            array('ID' => 102),
        );
        $regularPosts = array(
            array('ID' => 201),
        );

        $pinnedQuery = new WP_Query($pinnedPosts);
        $regularQuery = new WP_Query($regularPosts);

        $shortcodeStub = new class($pinnedQuery, $regularQuery, array(101, 102), 2, 3, $perPage) {
            private WP_Query $pinnedQuery;

            private WP_Query $regularQuery;

            /** @var array<int, int> */
            private array $renderedPinnedIds;

            private int $totalPinned;

            private int $totalRegular;

            private int $effectivePerPage;

            public array $receivedArgs = array();

            /**
             * @param array<int, int> $renderedPinnedIds
             */
            public function __construct(WP_Query $pinnedQuery, WP_Query $regularQuery, array $renderedPinnedIds, int $totalPinned, int $totalRegular, int $effectivePerPage)
            {
                $this->pinnedQuery = $pinnedQuery;
                $this->regularQuery = $regularQuery;
                $this->renderedPinnedIds = $renderedPinnedIds;
                $this->totalPinned = $totalPinned;
                $this->totalRegular = $totalRegular;
                $this->effectivePerPage = $effectivePerPage;
            }

            public function build_display_state(array $options, array $args = array()): array
            {
                $this->receivedArgs = $args;
                $seen = array_map('absint', $args['seen_pinned_ids'] ?? array());
                $updated = array_values(array_unique(array_merge($seen, $this->renderedPinnedIds)));

                return array(
                    'pinned_query' => $this->pinnedQuery,
                    'regular_query' => $this->regularQuery,
                    'rendered_pinned_ids' => $this->renderedPinnedIds,
                    'should_limit_display' => true,
                    'render_limit' => $this->effectivePerPage,
                    'regular_posts_needed' => 0,
                    'total_pinned_posts' => $this->totalPinned,
                    'total_regular_posts' => $this->totalRegular,
                    'effective_posts_per_page' => $this->effectivePerPage,
                    'is_unlimited' => false,
                    'updated_seen_pinned_ids' => $updated,
                );
            }

            public function render_article_item(array $options, bool $is_pinned): void
            {
                $type = $is_pinned ? 'pinned' : 'regular';
                $id = get_the_ID();
                echo '<article data-id="' . $id . '" data-type="' . $type . '"></article>';
            }

            public function get_empty_state_html(): string
            {
                return '<div class="empty">Aucun article</div>';
            }

            public function get_empty_state_slide_html(): string
            {
                return '<div class="empty-slide">Aucun article</div>';
            }

            public function get_skeleton_placeholder_markup(string $containerClass, array $options, int $renderLimit): string
            {
                return '<div class="skeleton">Placeholder</div>';
            }
        };

        $this->setShortcodeInstance($shortcodeStub);

        $_POST = array(
            'instance_id' => (string) $instanceId,
            'paged' => '1',
            'pinned_ids' => '101',
            'category' => 'Catégorie test',
            'security' => 'nonce',
        );

        $plugin = new Mon_Affichage_Articles();

        try {
            $plugin->load_more_articles_callback();
            $this->fail('Expected MyArticlesJsonResponse to be thrown.');
        } catch (\MyArticlesJsonResponse $response) {
            $this->assertTrue($response->success);
            $payload = $response->data;

            $this->assertSame('<article data-id="101" data-type="pinned"></article><article data-id="102" data-type="pinned"></article><article data-id="201" data-type="regular"></article>', $payload['html']);
            $this->assertSame('101,102', $payload['pinned_ids']);
            $this->assertSame(2, $payload['total_pages']);
            $this->assertSame(2, $payload['next_page']);
        }
    }

    public function test_load_more_last_page_returns_zero_next_page(): void
    {
        $instanceId = 654;
        $perPage = 3;

        $rawOptions = array(
            'display_mode' => 'grid',
            'posts_per_page' => $perPage,
            'post_type' => 'post',
            'pagination_mode' => 'load_more',
        );

        $normalizedOptions = array(
            'display_mode' => 'grid',
            'resolved_taxonomy' => '',
            'default_term' => '',
            'term' => '',
            'posts_per_page' => $perPage,
            'is_unlimited' => false,
            'pagination_mode' => 'load_more',
            'post_type' => 'post',
            'ignore_native_sticky' => 0,
            'unlimited_query_cap' => $perPage,
            'show_category_filter' => 0,
            'filter_categories' => array(),
            'all_excluded_ids' => array(),
            'allowed_filter_term_slugs' => array(),
            'is_requested_category_valid' => true,
        );

        $requestedCategory = 'derniere-page';
        $this->seedInstanceOptions($instanceId, $rawOptions, $normalizedOptions, $requestedCategory);

        $pinnedQuery = new WP_Query(array());
        $regularQuery = new WP_Query(
            array(
                array('ID' => 202),
                array('ID' => 203),
            )
        );

        $shortcodeStub = new class($pinnedQuery, $regularQuery, array(), 2, 3, $perPage) {
            private WP_Query $pinnedQuery;

            private WP_Query $regularQuery;

            /** @var array<int, int> */
            private array $renderedPinnedIds;

            private int $totalPinned;

            private int $totalRegular;

            private int $effectivePerPage;

            public array $receivedArgs = array();

            /**
             * @param array<int, int> $renderedPinnedIds
             */
            public function __construct(WP_Query $pinnedQuery, WP_Query $regularQuery, array $renderedPinnedIds, int $totalPinned, int $totalRegular, int $effectivePerPage)
            {
                $this->pinnedQuery = $pinnedQuery;
                $this->regularQuery = $regularQuery;
                $this->renderedPinnedIds = $renderedPinnedIds;
                $this->totalPinned = $totalPinned;
                $this->totalRegular = $totalRegular;
                $this->effectivePerPage = $effectivePerPage;
            }

            public function build_display_state(array $options, array $args = array()): array
            {
                $this->receivedArgs = $args;
                $seen = array_map('absint', $args['seen_pinned_ids'] ?? array());
                $updated = array_values(array_unique(array_merge($seen, $this->renderedPinnedIds)));

                return array(
                    'pinned_query' => $this->pinnedQuery,
                    'regular_query' => $this->regularQuery,
                    'rendered_pinned_ids' => $this->renderedPinnedIds,
                    'should_limit_display' => true,
                    'render_limit' => $this->effectivePerPage,
                    'regular_posts_needed' => 0,
                    'total_pinned_posts' => $this->totalPinned,
                    'total_regular_posts' => $this->totalRegular,
                    'effective_posts_per_page' => $this->effectivePerPage,
                    'is_unlimited' => false,
                    'updated_seen_pinned_ids' => $updated,
                );
            }

            public function render_article_item(array $options, bool $is_pinned): void
            {
                $type = $is_pinned ? 'pinned' : 'regular';
                $id = get_the_ID();
                echo '<article data-id="' . $id . '" data-type="' . $type . '"></article>';
            }

            public function get_empty_state_html(): string
            {
                return '<div class="empty">Aucun article</div>';
            }

            public function get_empty_state_slide_html(): string
            {
                return '<div class="empty-slide">Aucun article</div>';
            }

            public function get_skeleton_placeholder_markup(string $containerClass, array $options, int $renderLimit): string
            {
                return '<div class="skeleton">Placeholder</div>';
            }
        };

        $this->setShortcodeInstance($shortcodeStub);

        $_POST = array(
            'instance_id' => (string) $instanceId,
            'paged' => '2',
            'pinned_ids' => '101,102',
            'category' => 'Dernière page',
            'security' => 'nonce',
        );

        $plugin = new Mon_Affichage_Articles();

        try {
            $plugin->load_more_articles_callback();
            $this->fail('Expected MyArticlesJsonResponse to be thrown.');
        } catch (\MyArticlesJsonResponse $response) {
            $this->assertTrue($response->success);
            $payload = $response->data;

            $this->assertSame('<article data-id="202" data-type="regular"></article><article data-id="203" data-type="regular"></article>', $payload['html']);
            $this->assertSame('101,102', $payload['pinned_ids']);
            $this->assertSame(2, $payload['total_pages']);
            $this->assertSame(0, $payload['next_page']);
        }
    }

    public function test_load_more_fails_when_pagination_mode_not_enabled(): void
    {
        global $mon_articles_test_wp_query_factory;

        $instanceId = 987;

        $rawOptions = array(
            'display_mode' => 'grid',
            'posts_per_page' => 3,
            'post_type' => 'post',
            'pagination_mode' => 'none',
        );

        $normalizedOptions = array(
            'display_mode' => 'grid',
            'resolved_taxonomy' => '',
            'default_term' => '',
            'term' => '',
            'posts_per_page' => 3,
            'is_unlimited' => false,
            'pagination_mode' => 'none',
            'post_type' => 'post',
            'ignore_native_sticky' => 0,
            'unlimited_query_cap' => 3,
            'show_category_filter' => 0,
            'filter_categories' => array(),
            'all_excluded_ids' => array(),
            'allowed_filter_term_slugs' => array(),
            'is_requested_category_valid' => true,
        );

        $requestedCategory = '';
        $this->seedInstanceOptions($instanceId, $rawOptions, $normalizedOptions, $requestedCategory);

        $factoryInvocations = 0;
        $mon_articles_test_wp_query_factory = static function (array $args) use (&$factoryInvocations): array {
            $factoryInvocations++;

            return array(
                'posts' => array(),
                'found_posts' => 0,
            );
        };

        $shortcodeStub = new class {
            public function build_display_state(array $options, array $args = array()): array
            {
                throw new \RuntimeException('build_display_state should not be called when load more is disabled.');
            }
        };

        $this->setShortcodeInstance($shortcodeStub);

        $_POST = array(
            'instance_id' => (string) $instanceId,
            'paged' => '1',
            'pinned_ids' => '',
            'category' => '',
            'security' => 'nonce',
        );

        $plugin = new Mon_Affichage_Articles();

        try {
            $plugin->load_more_articles_callback();
            $this->fail('Expected JSON error response when load more is disabled.');
        } catch (\MyArticlesJsonResponse $response) {
            $this->assertFalse($response->success, 'The response should indicate failure.');
            $this->assertSame(400, $response->status_code);
            $this->assertArrayHasKey('message', $response->data);
            $this->assertNotEmpty($response->data['message']);
        }

        $this->assertSame(0, $factoryInvocations, 'WP_Query should not be triggered when load more is disabled.');
    }

    public function test_load_more_returns_error_for_unpublished_instance(): void
    {
        global $mon_articles_test_post_type_map, $mon_articles_test_post_status_map;

        $instanceId = 777;

        $mon_articles_test_post_type_map = array(
            $instanceId => 'mon_affichage',
        );
        $mon_articles_test_post_status_map = array(
            $instanceId => 'trash',
        );

        $_POST = array(
            'security'    => 'nonce',
            'instance_id' => (string) $instanceId,
            'paged'       => '1',
            'pinned_ids'  => '',
            'category'    => '',
        );

        $plugin = new Mon_Affichage_Articles();

        try {
            $plugin->load_more_articles_callback();
            $this->fail('Expected JSON error response for unpublished instance.');
        } catch (\MyArticlesJsonResponse $response) {
            $this->assertFalse($response->success, 'Expected the JSON response to indicate failure.');
            $this->assertSame(404, $response->status_code);
            $this->assertArrayHasKey('message', $response->data);
            $this->assertNotEmpty($response->data['message']);
        }
    }
}

}
