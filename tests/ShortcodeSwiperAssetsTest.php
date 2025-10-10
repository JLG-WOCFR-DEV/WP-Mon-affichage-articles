<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use My_Articles_Enqueue;
use My_Articles_Frontend_Data;
use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;

final class ShortcodeSwiperAssetsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('MY_ARTICLES_PLUGIN_URL')) {
            define('MY_ARTICLES_PLUGIN_URL', 'http://example.com/wp-content/plugins/mon-affichage-articles/');
        }

        if (!defined('MY_ARTICLES_VERSION')) {
            define('MY_ARTICLES_VERSION', '1.0.0-test');
        }

        require_once __DIR__ . '/../mon-affichage-article/includes/class-my-articles-frontend-data.php';
        require_once __DIR__ . '/../mon-affichage-article/includes/class-my-articles-enqueue.php';
        require_once __DIR__ . '/../mon-affichage-article/includes/class-my-articles-shortcode.php';
    }

    protected function setUp(): void
    {
        global $mon_articles_test_registered_styles,
            $mon_articles_test_registered_scripts,
            $mon_articles_test_enqueued_styles,
            $mon_articles_test_enqueued_scripts,
            $mon_articles_test_inline_scripts,
            $mon_articles_test_marked_scripts;

        $mon_articles_test_registered_styles  = array();
        $mon_articles_test_registered_scripts = array();
        $mon_articles_test_enqueued_styles    = array();
        $mon_articles_test_enqueued_scripts   = array();
        $mon_articles_test_inline_scripts     = array();
        $mon_articles_test_marked_scripts     = array();
    }

    public function test_enqueue_swiper_scripts_registers_inline_data(): void
    {
        $shortcode = $this->createShortcodeInstance();

        My_Articles_Frontend_Data::get_instance();
        My_Articles_Enqueue::get_instance();

        $options = array(
            'columns_mobile'                   => 1,
            'columns_tablet'                   => 2,
            'columns_desktop'                  => 3,
            'columns_ultrawide'                => 4,
            'gap_size'                         => 16,
            'slideshow_loop'                   => 1,
            'slideshow_autoplay'               => 1,
            'slideshow_delay'                  => 2500,
            'slideshow_pause_on_interaction'   => 1,
            'slideshow_pause_on_mouse_enter'   => 0,
            'slideshow_respect_reduced_motion' => 1,
            'slideshow_show_navigation'        => 1,
            'slideshow_show_pagination'        => 0,
        );

        $instanceId = 42;

        $this->invokePrivateMethod($shortcode, 'enqueue_swiper_scripts', array($options, $instanceId));

        My_Articles_Frontend_Data::get_instance()->maybe_output_registered_data();

        global $mon_articles_test_inline_scripts;

        $this->assertNotEmpty($mon_articles_test_inline_scripts, 'Expected inline scripts to be registered for Swiper.');

        $swiperSnippets = array_values(array_filter(
            $mon_articles_test_inline_scripts,
            static function (array $snippet) use ($instanceId): bool {
                if ($snippet['handle'] !== 'my-articles-swiper-init') {
                    return false;
                }

                return str_contains($snippet['data'], (string) $instanceId);
            }
        ));

        $this->assertNotEmpty($swiperSnippets, 'Swiper settings should be exposed for the targeted instance.');

        $payload = $swiperSnippets[0]['data'];

        $this->assertStringContainsString('window.myArticlesSwiperSettings', $payload);
        $this->assertStringContainsString('"42"', $payload, 'Instance identifier should be part of the payload key.');
        $this->assertStringContainsString('"autoplay"', $payload, 'Autoplay configuration should be serialized.');
    }

    /**
     * @return My_Articles_Shortcode
     */
    private function createShortcodeInstance()
    {
        $reflection = new \ReflectionClass(My_Articles_Shortcode::class);

        return $reflection->newInstanceWithoutConstructor();
    }

    /**
     * @param My_Articles_Shortcode $instance
     * @param string                $method
     * @param array<int, mixed>     $arguments
     */
    private function invokePrivateMethod(My_Articles_Shortcode $instance, string $method, array $arguments = array()): void
    {
        $reflection = new \ReflectionClass($instance);
        $target     = $reflection->getMethod($method);

        $target->setAccessible(true);
        $target->invokeArgs($instance, $arguments);
    }
}
