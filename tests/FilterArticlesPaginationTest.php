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

use Mon_Affichage_Articles;
use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_Query;

/**
 * @covers Mon_Affichage_Articles::filter_articles_callback
 */
final class FilterArticlesPaginationTest extends TestCase
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

        global $mon_articles_test_post_meta_map, $mon_articles_test_post_type_map, $mon_articles_test_wp_query_factory;

        $mon_articles_test_post_meta_map = array();
        $mon_articles_test_post_type_map = array();
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
        global $mon_articles_test_post_meta_map, $mon_articles_test_post_type_map, $mon_articles_test_wp_query_factory;

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
        $mon_articles_test_wp_query_factory = $this->wpQueryFactoryBackup;

        $_POST = array();

        parent::tearDown();
    }

    public function test_total_pages_accounts_for_regular_posts_after_pinned_page(): void
    {
        global $mon_articles_test_post_meta_map, $mon_articles_test_post_type_map, $mon_articles_test_wp_query_factory;

        $instanceId = 123;
        $perPage = 3;
        $expectedRegularCount = 5;

        $rawOptions = array(
            'display_mode' => 'grid',
            'posts_per_page' => $perPage,
            'post_type' => 'post',
        );

        $context = array(
            'requested_category' => '',
            'force_collect_terms' => true,
        );

        $normalizedOptions = array(
            'display_mode' => 'grid',
            'resolved_taxonomy' => '',
            'default_term' => '',
            'term' => '',
            'posts_per_page' => $perPage,
            'is_unlimited' => false,
            'pagination_mode' => 'none',
            'post_type' => 'post',
            'ignore_native_sticky' => 0,
            'unlimited_query_cap' => $perPage,
            'show_category_filter' => 0,
            'filter_categories' => array(),
            'all_excluded_ids' => array(),
        );

        $mon_articles_test_post_meta_map = array(
            $instanceId => array(
                '_my_articles_settings' => $rawOptions,
            ),
        );

        $mon_articles_test_post_type_map = array(
            $instanceId => 'mon_affichage',
        );

        $reflection = new ReflectionClass(My_Articles_Shortcode::class);
        $cacheKeyMethod = $reflection->getMethod('build_normalized_options_cache_key');
        $cacheKeyMethod->setAccessible(true);
        $cacheKey = $cacheKeyMethod->invoke(null, $rawOptions, $context);

        $normalizedProperty = $reflection->getProperty('normalized_options_cache');
        $normalizedProperty->setAccessible(true);
        $cache = $normalizedProperty->getValue();
        $cache[$cacheKey] = $normalizedOptions;
        $normalizedProperty->setValue(null, $cache);

        $pinnedPosts = array(
            array('ID' => 10),
            array('ID' => 11),
            array('ID' => 12),
        );

        $pinnedQuery = new WP_Query($pinnedPosts);

        $shortcodeStub = new class($pinnedQuery, $perPage) {
            private WP_Query $pinnedQuery;

            private int $perPage;

            public function __construct(WP_Query $pinnedQuery, int $perPage)
            {
                $this->pinnedQuery = $pinnedQuery;
                $this->perPage = $perPage;
            }

            public function build_display_state(array $options, array $args = array()): array
            {
                return array(
                    'pinned_query' => $this->pinnedQuery,
                    'regular_query' => null,
                    'rendered_pinned_ids' => array(10, 11, 12),
                    'should_limit_display' => true,
                    'render_limit' => $this->perPage,
                    'regular_posts_needed' => 0,
                    'total_pinned_posts' => $this->pinnedQuery->found_posts,
                    'total_regular_posts' => 0,
                    'effective_posts_per_page' => $this->perPage,
                    'is_unlimited' => false,
                    'updated_seen_pinned_ids' => array(),
                );
            }

            public function render_article_item(array $options, bool $is_pinned): void
            {
                echo '<article class="' . ($is_pinned ? 'pinned' : 'regular') . '"></article>';
            }

            public function get_empty_state_html(): string
            {
                return '<div class="empty">Aucun article</div>';
            }

            public function get_empty_state_slide_html(): string
            {
                return '<div class="empty-slide">Aucun article</div>';
            }
        };

        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, $shortcodeStub);

        $mon_articles_test_wp_query_factory = static function (array $args) use ($expectedRegularCount): array {
            if (isset($args['posts_per_page']) && 1 === (int) $args['posts_per_page']) {
                return array(
                    'posts' => array(),
                    'found_posts' => $expectedRegularCount,
                );
            }

            return array(
                'posts' => array(),
                'found_posts' => 0,
            );
        };

        $_POST = array(
            'security' => 'nonce',
            'instance_id' => (string) $instanceId,
            'category' => '',
            'current_url' => 'http://example.com/page',
        );

        $plugin = new Mon_Affichage_Articles();

        try {
            $plugin->filter_articles_callback();
            $this->fail('Expected JSON response to interrupt execution.');
        } catch (\MyArticlesJsonResponse $response) {
            $this->assertTrue($response->success, 'Expected a successful JSON response.');

            $this->assertArrayHasKey('total_pages', $response->data);
            $this->assertSame(3, $response->data['total_pages']);
        }
    }
}

}
