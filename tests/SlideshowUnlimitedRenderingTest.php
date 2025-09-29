<?php

declare(strict_types=1);

namespace {

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all'): void
    {
        // No-op for tests.
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false): void
    {
        // No-op for tests.
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n): bool
    {
        return true;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action): string
    {
        return 'nonce-' . (string) $action;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '', $scheme = 'admin'): string
    {
        $path = ltrim((string) $path, '/');

        return 'http://example.com/wp-admin/' . $path;
    }
}
}

namespace MonAffichageArticles\Tests {

use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SlideshowUnlimitedRenderingTest extends TestCase
{
    /** @var mixed */
    private $shortcodeInstanceBackup;

    /** @var array<string, mixed> */
    private array $normalizedOptionsCacheBackup = array();

    /** @var array<string, mixed> */
    private array $matchingPinnedCacheBackup = array();

    protected function setUp(): void
    {
        parent::setUp();

        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map,
            $mon_articles_test_wp_query_factory;

        $mon_articles_test_post_type_map   = array();
        $mon_articles_test_post_status_map = array();
        $mon_articles_test_post_meta_map   = array();
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
        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map,
            $mon_articles_test_wp_query_factory;

        $mon_articles_test_post_type_map   = array();
        $mon_articles_test_post_status_map = array();
        $mon_articles_test_post_meta_map   = array();
        $mon_articles_test_wp_query_factory = null;

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

        parent::tearDown();
    }

    public function test_slideshow_unlimited_renders_all_posts(): void
    {
        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map,
            $mon_articles_test_wp_query_factory;

        $instanceId = 2468;

        $pinnedPosts = array(
            array('ID' => 501),
            array('ID' => 502),
        );

        $regularPosts = array(
            array('ID' => 601),
            array('ID' => 602),
            array('ID' => 603),
        );

        $mon_articles_test_post_type_map = array(
            $instanceId => 'mon_affichage',
            501 => 'post',
            502 => 'post',
            601 => 'post',
            602 => 'post',
            603 => 'post',
        );

        $mon_articles_test_post_status_map = array(
            $instanceId => 'publish',
        );

        $mon_articles_test_post_meta_map = array(
            $instanceId => array(
                '_my_articles_settings' => array(
                    'display_mode'          => 'slideshow',
                    'posts_per_page'        => 0,
                    'pagination_mode'       => 'none',
                    'pinned_posts'          => array(501, 502),
                    'show_category_filter'  => 0,
                    'enable_lazy_load'      => 0,
                    'show_category'         => 0,
                    'show_author'           => 0,
                    'show_date'             => 0,
                    'show_excerpt'          => 0,
                ),
            ),
        );

        $mon_articles_test_wp_query_factory = static function (array $args) use ($pinnedPosts, $regularPosts) {
            if (!empty($args['post__in'])) {
                $posts = $pinnedPosts;

                if (isset($args['fields']) && 'ids' === $args['fields']) {
                    $posts = array_map(
                        static function (array $post): int {
                            return isset($post['ID']) ? (int) $post['ID'] : 0;
                        },
                        $pinnedPosts
                    );
                }
                return array(
                    'posts'       => $posts,
                    'found_posts' => count($pinnedPosts),
                );
            }

            return array(
                'posts'       => $regularPosts,
                'found_posts' => count($regularPosts),
            );
        };

        $shortcode = My_Articles_Shortcode::get_instance();

        $html = $shortcode->render_shortcode(array('id' => (string) $instanceId));

        $this->assertStringContainsString('swiper-wrapper', $html);
        $expectedSlides = count($pinnedPosts) + count($regularPosts);
        $this->assertSame($expectedSlides, substr_count($html, '<div class="swiper-slide">'));
    }
}

}
