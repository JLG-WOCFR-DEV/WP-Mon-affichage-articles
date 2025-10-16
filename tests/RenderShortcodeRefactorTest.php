<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use My_Articles_Enqueue;
use My_Articles_Frontend_Data;
use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RenderShortcodeRefactorTest extends TestCase
{
    /** @var mixed */
    private $shortcodeInstanceBackup;

    /** @var array<string, mixed> */
    private array $normalizedOptionsCacheBackup = array();

    /** @var array<string, mixed> */
    private array $matchingPinnedCacheBackup = array();

    /** @var mixed */
    private $frontendInstanceBackup;

    /** @var mixed */
    private $enqueueInstanceBackup;

    private bool $lazySizesFlagBackup = false;

    private bool $lazyFallbackFlagBackup = false;

    protected function setUp(): void
    {
        parent::setUp();

        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map,
            $mon_articles_test_inline_scripts,
            $mon_articles_test_wp_query_factory,
            $mon_articles_test_enqueued_scripts,
            $mon_articles_test_enqueued_styles,
            $mon_articles_test_marked_scripts;

        $mon_articles_test_post_type_map   = array();
        $mon_articles_test_post_status_map = array();
        $mon_articles_test_post_meta_map   = array();
        $mon_articles_test_inline_scripts  = array();
        $mon_articles_test_wp_query_factory = null;
        $mon_articles_test_enqueued_scripts = array();
        $mon_articles_test_enqueued_styles  = array();
        $mon_articles_test_marked_scripts   = array();

        $shortcodeReflection = new ReflectionClass(My_Articles_Shortcode::class);

        $instanceProperty = $shortcodeReflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $this->shortcodeInstanceBackup = $instanceProperty->getValue();
        $instanceProperty->setValue(null, null);

        $normalizedProperty = $shortcodeReflection->getProperty('normalized_options_cache');
        $normalizedProperty->setAccessible(true);
        $this->normalizedOptionsCacheBackup = $normalizedProperty->getValue();
        $normalizedProperty->setValue(null, array());

        $matchingProperty = $shortcodeReflection->getProperty('matching_pinned_ids_cache');
        $matchingProperty->setAccessible(true);
        $this->matchingPinnedCacheBackup = $matchingProperty->getValue();
        $matchingProperty->setValue(null, array());

        $lazySizesProperty = $shortcodeReflection->getProperty('lazysizes_enqueued');
        $lazySizesProperty->setAccessible(true);
        $this->lazySizesFlagBackup = (bool) $lazySizesProperty->getValue();
        $lazySizesProperty->setValue(null, false);

        $lazyFallbackProperty = $shortcodeReflection->getProperty('lazyload_fallback_added');
        $lazyFallbackProperty->setAccessible(true);
        $this->lazyFallbackFlagBackup = (bool) $lazyFallbackProperty->getValue();
        $lazyFallbackProperty->setValue(null, false);

        $frontendReflection = new ReflectionClass(My_Articles_Frontend_Data::class);
        $frontendInstance = $frontendReflection->getProperty('instance');
        $frontendInstance->setAccessible(true);
        $this->frontendInstanceBackup = $frontendInstance->getValue();
        $frontendInstance->setValue(null, null);

        $enqueueReflection = new ReflectionClass(My_Articles_Enqueue::class);
        $enqueueInstance = $enqueueReflection->getProperty('instance');
        $enqueueInstance->setAccessible(true);
        $this->enqueueInstanceBackup = $enqueueInstance->getValue();
        $enqueueInstance->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $shortcodeReflection = new ReflectionClass(My_Articles_Shortcode::class);

        $instanceProperty = $shortcodeReflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, $this->shortcodeInstanceBackup);

        $normalizedProperty = $shortcodeReflection->getProperty('normalized_options_cache');
        $normalizedProperty->setAccessible(true);
        $normalizedProperty->setValue(null, $this->normalizedOptionsCacheBackup);

        $matchingProperty = $shortcodeReflection->getProperty('matching_pinned_ids_cache');
        $matchingProperty->setAccessible(true);
        $matchingProperty->setValue(null, $this->matchingPinnedCacheBackup);

        $lazySizesProperty = $shortcodeReflection->getProperty('lazysizes_enqueued');
        $lazySizesProperty->setAccessible(true);
        $lazySizesProperty->setValue(null, $this->lazySizesFlagBackup);

        $lazyFallbackProperty = $shortcodeReflection->getProperty('lazyload_fallback_added');
        $lazyFallbackProperty->setAccessible(true);
        $lazyFallbackProperty->setValue(null, $this->lazyFallbackFlagBackup);

        $frontendReflection = new ReflectionClass(My_Articles_Frontend_Data::class);
        $frontendInstance = $frontendReflection->getProperty('instance');
        $frontendInstance->setAccessible(true);
        $frontendInstance->setValue(null, $this->frontendInstanceBackup);

        $enqueueReflection = new ReflectionClass(My_Articles_Enqueue::class);
        $enqueueInstance = $enqueueReflection->getProperty('instance');
        $enqueueInstance->setAccessible(true);
        $enqueueInstance->setValue(null, $this->enqueueInstanceBackup);

        parent::tearDown();
    }

    public function test_grid_render_html_and_filter_payload(): void
    {
        $instanceId = 1001;
        $settings   = array(
            'display_mode'          => 'grid',
            'posts_per_page'        => 2,
            'enable_keyword_search' => 1,
            'show_category_filter'  => 0,
            'pagination_mode'       => 'none',
            'enable_lazy_load'      => 0,
            'hover_lift_desktop'    => 0,
            'hover_neon_pulse'      => 0,
        );

        $posts = $this->createPosts(array(201, 202, 203));

        $output = $this->renderInstance($instanceId, $settings, $posts);

        $expected = $this->getFixtureHtml('grid');

        $this->assertSame($expected, $output, $output);

        My_Articles_Frontend_Data::get_instance()->maybe_output_registered_data();

        global $mon_articles_test_inline_scripts;

        $filterPayloads = array_filter(
            $mon_articles_test_inline_scripts,
            static function (array $entry): bool {
                return 'my-articles-filter' === ($entry['handle'] ?? '')
                    && str_contains($entry['data'] ?? '', 'window.myArticlesFilter');
            }
        );

        $this->assertNotEmpty($filterPayloads, 'Expected filter payload to be registered.');
    }

    public function test_list_render_html_structure(): void
    {
        $instanceId = 1002;
        $settings   = array(
            'display_mode'          => 'list',
            'posts_per_page'        => 2,
            'enable_keyword_search' => 0,
            'show_category_filter'  => 0,
            'pagination_mode'       => 'none',
            'enable_lazy_load'      => 0,
            'hover_lift_desktop'    => 0,
            'hover_neon_pulse'      => 0,
        );

        $posts   = $this->createPosts(array(301, 302));
        $output  = $this->renderInstance($instanceId, $settings, $posts);
        $expected = $this->getFixtureHtml('list');

        $this->assertSame($expected, $output, $output);
    }

    public function test_slideshow_render_and_swiper_payload(): void
    {
        $instanceId = 1003;
        $settings   = array(
            'display_mode'                 => 'slideshow',
            'posts_per_page'               => 2,
            'enable_keyword_search'        => 0,
            'show_category_filter'         => 0,
            'pagination_mode'              => 'none',
            'enable_lazy_load'             => 0,
            'slideshow_show_navigation'    => 1,
            'slideshow_show_pagination'    => 1,
            'slideshow_loop'               => 1,
            'slideshow_autoplay'           => 1,
            'slideshow_delay'              => 2000,
            'slideshow_pause_on_interaction' => 0,
            'slideshow_pause_on_mouse_enter' => 0,
        );

        $posts  = $this->createPosts(array(401, 402, 403));
        $output = $this->renderInstance($instanceId, $settings, $posts);
        $expected = $this->getFixtureHtml('slideshow');

        $this->assertSame($expected, $output, $output);

        My_Articles_Frontend_Data::get_instance()->maybe_output_registered_data();

        global $mon_articles_test_inline_scripts;

        $swiperPayloads = array_filter(
            $mon_articles_test_inline_scripts,
            static function (array $entry) use ($instanceId): bool {
                if ( 'my-articles-swiper-init' !== ($entry['handle'] ?? '') ) {
                    return false;
                }

                return str_contains($entry['data'] ?? '', (string) $instanceId)
                    && str_contains($entry['data'] ?? '', 'window.myArticlesSwiperSettings');
            }
        );

        $this->assertNotEmpty($swiperPayloads, 'Expected swiper payload to be registered.');
    }

    public function test_load_more_pagination_registers_payload(): void
    {
        $instanceId = 1004;
        $settings   = array(
            'display_mode'          => 'grid',
            'posts_per_page'        => 1,
            'enable_keyword_search' => 0,
            'show_category_filter'  => 0,
            'pagination_mode'       => 'load_more',
            'enable_lazy_load'      => 0,
            'hover_lift_desktop'    => 0,
            'hover_neon_pulse'      => 0,
        );

        $posts   = $this->createPosts(array(501, 502, 503));
        $output  = $this->renderInstance($instanceId, $settings, $posts);
        $expected = $this->getFixtureHtml('load_more');

        $this->assertSame($expected, $output, $output);

        My_Articles_Frontend_Data::get_instance()->maybe_output_registered_data();

        global $mon_articles_test_inline_scripts;

        $loadMorePayloads = array_filter(
            $mon_articles_test_inline_scripts,
            static function (array $entry): bool {
                if ( 'my-articles-load-more' !== ($entry['handle'] ?? '') ) {
                    return false;
                }

                return str_contains($entry['data'] ?? '', 'window.myArticlesLoadMore');
            }
        );

        $this->assertNotEmpty($loadMorePayloads, 'Expected load-more payload to be registered.');
    }

    /**
     * @param array<int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function createPosts(array $ids): array
    {
        $posts = array();

        foreach ($ids as $id) {
            $posts[] = array(
                'ID'           => $id,
                'post_author'  => 1,
                'post_title'   => 'Post ' . $id,
                'post_type'    => 'post',
                'post_status'  => 'publish',
                'post_content' => 'Content ' . $id,
            );
        }

        return $posts;
    }

    /**
     * @param int                                     $instanceId
     * @param array<string, mixed>                    $settings
     * @param array<int, array<string, mixed>>        $posts
     */
    private function configureEnvironment(int $instanceId, array $settings, array $posts): void
    {
        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map,
            $mon_articles_test_wp_query_factory;

        $mon_articles_test_post_type_map[$instanceId]   = 'mon_affichage';
        $mon_articles_test_post_status_map[$instanceId] = 'publish';
        $mon_articles_test_post_meta_map[$instanceId]   = array(
            '_my_articles_settings' => $settings,
        );

        $mon_articles_test_wp_query_factory = static function (array $query_args) use ($posts) {
            $posts_per_page = isset($query_args['posts_per_page']) ? (int) $query_args['posts_per_page'] : count($posts);
            $offset         = isset($query_args['offset']) ? (int) $query_args['offset'] : 0;

            if ($posts_per_page < 0) {
                $slice = array_slice($posts, $offset);
            } else {
                $slice = array_slice($posts, $offset, $posts_per_page);
            }

            return array(
                'posts'       => $slice,
                'found_posts' => count($posts),
            );
        };
    }

    /**
     * @param int                                     $instanceId
     * @param array<string, mixed>                    $settings
     * @param array<int, array<string, mixed>>        $posts
     */
    private function renderInstance(int $instanceId, array $settings, array $posts): string
    {
        $this->configureEnvironment($instanceId, $settings, $posts);

        $shortcode = My_Articles_Shortcode::get_instance();
        $output    = $shortcode->render_shortcode(array('id' => (string) $instanceId));

        return $this->normalizeHtml($output);
    }

    private function getFixtureHtml(string $scenario): string
    {
        $path = __DIR__ . '/fixtures/render/' . $scenario . '.html';

        $contents = file_get_contents($path);

        $this->assertNotFalse(
            $contents,
            sprintf('Fixture missing for scenario "%s" at path %s', $scenario, $path)
        );

        return $this->normalizeHtml((string) $contents);
    }

    private function normalizeHtml(string $html): string
    {
        $html = preg_replace('/\s+/', ' ', trim($html));
        $html = preg_replace('/\sstyle="[^"]*"/', '', $html ?? '');

        return trim((string) $html);
    }
}
