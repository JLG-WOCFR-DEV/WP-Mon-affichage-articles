<?php

declare(strict_types=1);

namespace {

if (!function_exists('admin_url')) {
    function admin_url($path = '')
    {
        return 'https://example.com/wp-admin/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '')
    {
        if (!is_array($args)) {
            return (string) $url;
        }

        $separator = (false === strpos((string) $url, '?')) ? '?' : '&';

        return (string) $url . $separator . http_build_query($args);
    }
}

if (!function_exists('settings_errors')) {
    function settings_errors($setting = '', $sanitize = false, $hide_on_update = false): void
    {
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }
}

if (!function_exists('get_settings_errors')) {
    function get_settings_errors($setting = '', $sanitize = false)
    {
        return array();
    }
}

if (!function_exists('settings_fields')) {
    function settings_fields($option_group): void
    {
        echo '<input type="hidden" name="option_page" value="' . htmlspecialchars((string) $option_group, ENT_QUOTES, 'UTF-8') . '" />';
    }
}

if (!function_exists('do_settings_sections')) {
    function do_settings_sections($page): void
    {
        echo '<table class="form-table" role="presentation"><tr><th>Field</th><td>Value</td></tr></table>';
    }
}

if (!function_exists('submit_button')) {
    function submit_button($text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null): void
    {
        $class = 'primary' === $type ? 'button-primary' : 'button-secondary';
        echo '<p class="submit"><input type="submit" class="button ' . $class . '" value="' . htmlspecialchars((string) ($text ?: 'Save'), ENT_QUOTES, 'UTF-8') . '" /></p>';
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true)
    {
        $html = '<input type="hidden" name="_wpnonce" value="nonce" />';
        if ($echo) {
            echo $html;
        }

        return $html;
    }
}

if (!function_exists('esc_js')) {
    function esc_js($text)
    {
        return (string) $text;
    }
}

}

namespace MonAffichageArticles\Tests {

use Mon_Affichage_Articles;
use My_Articles_Enqueue;
use My_Articles_Settings;
use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;

final class Phase2AdminCharterAndWp71Test extends TestCase
{
    private function pluginRoot(): string
    {
        return dirname(__DIR__) . '/mon-affichage-article';
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('MY_ARTICLES_VERSION')) {
            define('MY_ARTICLES_VERSION', 'tests');
        }

        if (!defined('MY_ARTICLES_PLUGIN_URL')) {
            define('MY_ARTICLES_PLUGIN_URL', 'http://example.com/wp-content/plugins/mon-affichage-articles/');
        }

        if (!defined('MY_ARTICLES_PLUGIN_DIR')) {
            define('MY_ARTICLES_PLUGIN_DIR', $this->pluginRoot() . '/');
        }

        require_once $this->pluginRoot() . '/includes/class-my-articles-enqueue.php';
        require_once $this->pluginRoot() . '/includes/class-my-articles-settings.php';

        $this->resetRequestGlobals();

        global $mon_articles_test_enqueued_styles,
            $mon_articles_test_enqueued_scripts,
            $mon_articles_test_registered_styles,
            $mon_articles_test_registered_scripts,
            $mon_articles_test_inline_scripts,
            $mon_articles_test_script_translations,
            $mon_articles_test_marked_scripts,
            $mon_articles_test_script_data;

        $mon_articles_test_enqueued_styles     = array();
        $mon_articles_test_enqueued_scripts    = array();
        $mon_articles_test_registered_styles   = array();
        $mon_articles_test_registered_scripts  = array();
        $mon_articles_test_inline_scripts      = array();
        $mon_articles_test_script_translations = array();
        $mon_articles_test_marked_scripts      = array();
        $mon_articles_test_script_data         = array();
    }

    protected function tearDown(): void
    {
        $this->resetRequestGlobals();
        parent::tearDown();
    }

    private function resetRequestGlobals(): void
    {
        unset($GLOBALS['mon_articles_test_is_admin']);
        unset($_GET['tab']);
        unset($_GET['status']);
        unset($_GET['canvas']);
        unset($_SERVER['REQUEST_URI']);
        unset($_REQUEST['context']);
        unset($GLOBALS['mon_articles_test_post_type_map']);
        unset($GLOBALS['mon_articles_test_post_status_map']);
        unset($GLOBALS['mon_articles_test_post_meta_map']);
        unset($GLOBALS['mon_articles_test_wp_query_factory']);
    }

    public function test_plugin_headers_declare_wordpress_71_compatibility(): void
    {
        $plugin = (string) file_get_contents($this->pluginRoot() . '/mon-affichage-articles.php');

        $this->assertMatchesRegularExpression('/Requires at least:\s*\S+/', $plugin);
        $this->assertMatchesRegularExpression('/Tested up to:\s*7\.1/', $plugin);
        $this->assertMatchesRegularExpression('/Requires PHP:\s*\S+/', $plugin);
    }

    public function test_settings_page_uses_native_wp_admin_chrome(): void
    {
        $settings = (string) file_get_contents($this->pluginRoot() . '/includes/class-my-articles-settings.php');
        $adminCss = (string) file_get_contents($this->pluginRoot() . '/assets/css/admin.css');

        $this->assertStringContainsString('class="wrap"', $settings);
        $this->assertMatchesRegularExpression('/<h1[^>]*>/', $settings);
        $this->assertLessThan(
            (int) strpos($settings, 'nav-tab-wrapper'),
            (int) strpos($settings, '<h1'),
            'Les onglets nav-tab doivent rester sous le h1.'
        );
        $this->assertStringContainsString('nav-tab-wrapper', $settings);
        $this->assertStringContainsString('nav-tab', $settings);
        $this->assertStringContainsString('register_setting', $settings);
        $this->assertStringContainsString('do_settings_sections', $settings);
        $this->assertStringContainsString('submit_button', $settings);
        $this->assertStringContainsString('settings_errors', $settings);

        $this->assertStringNotContainsString('my-articles-admin__header', $settings);
        $this->assertStringNotContainsString('my-articles-admin__badge', $settings);
        $this->assertStringNotContainsString('admin_theme_callback', $settings);
        $this->assertStringNotContainsString("add_settings_field( 'admin_theme'", $settings);

        $this->assertDoesNotMatchRegularExpression('/^\s*\.button-primary\b/m', $adminCss);
        $this->assertDoesNotMatchRegularExpression('/^\s*\.notice-success\b/m', $adminCss);
        $this->assertStringNotContainsString('my-articles-admin__header', $adminCss);
        $this->assertStringNotContainsString('prefers-color-scheme: dark', $adminCss);
    }

    public function test_preview_script_targets_the_iframed_canvas_document(): void
    {
        $preview = (string) file_get_contents($this->pluginRoot() . '/blocks/mon-affichage-articles/preview.js');

        $this->assertStringContainsString('getCanvasDocument', $preview);
        $this->assertStringContainsString('editor-canvas', $preview);
        $this->assertStringContainsString('MY_ARTICLES_IS_EDITOR', $preview);
        $this->assertStringContainsString("data-my-articles-editor", $preview);
    }

    public function test_create_admin_page_renders_wrap_h1_nav_tabs_and_notices(): void
    {
        $page = My_Articles_Settings::get_instance();

        ob_start();
        $page->create_admin_page();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('class="wrap"', $output);
        $this->assertStringContainsString('<h1>', $output);
        $this->assertStringContainsString('nav-tab-wrapper', $output);
        $this->assertStringContainsString('nav-tab-active', $output);
        $this->assertStringContainsString('notice notice-success', $output);
        $this->assertStringContainsString('form-table', $output);
        $this->assertStringContainsString('button-primary', $output);
        $this->assertStringNotContainsString('my-articles-admin__header', $output);
        $this->assertStringNotContainsString('data-theme=', $output);
    }

    public function test_block_json_uses_api_version_3(): void
    {
        $block = json_decode(
            (string) file_get_contents($this->pluginRoot() . '/blocks/mon-affichage-articles/block.json'),
            true
        );

        $this->assertIsArray($block);
        $this->assertSame(3, (int) $block['apiVersion']);
    }

    public function test_editor_parent_does_not_enqueue_frontend_scripts(): void
    {
        $enqueue = My_Articles_Enqueue::get_instance();
        $enqueue->register_plugin_styles_scripts();
        $enqueue->enqueue_block_editor_assets();

        $scriptHandles = $this->enqueuedHandles('script');
        $styleHandles  = $this->enqueuedHandles('style');

        $this->assertNotContains('my-articles-responsive-layout', $scriptHandles);
        $this->assertNotContains('my-articles-filter', $scriptHandles);
        $this->assertNotContains('my-articles-load-more', $scriptHandles);
        $this->assertNotContains('my-articles-swiper-init', $scriptHandles);
        $this->assertNotContains('swiper-js', $scriptHandles);
        $this->assertNotContains('lazysizes', $scriptHandles);
        $this->assertNotContains('my-articles-styles', $styleHandles);
        $this->assertNotContains('swiper-css', $styleHandles);
    }

    public function test_canvas_assets_load_in_admin_iframe_only(): void
    {
        $enqueue = My_Articles_Enqueue::get_instance();
        $enqueue->register_plugin_styles_scripts();
        $enqueue->enqueue_block_editor_canvas_assets();

        $this->assertSame(array(), $this->enqueuedHandles('style'));
        $this->assertSame(array(), $this->enqueuedHandles('script'));

        $GLOBALS['mon_articles_test_is_admin'] = true;
        global $mon_articles_test_enqueued_styles, $mon_articles_test_enqueued_scripts, $mon_articles_test_inline_scripts;
        $mon_articles_test_enqueued_styles  = array();
        $mon_articles_test_enqueued_scripts = array();
        $mon_articles_test_inline_scripts   = array();

        $enqueue->enqueue_block_editor_canvas_assets();

        $styleHandles  = $this->enqueuedHandles('style');
        $scriptHandles = $this->enqueuedHandles('script');

        $this->assertContains('my-articles-styles', $styleHandles);
        $this->assertContains('swiper-css', $styleHandles);
        $this->assertContains('my-articles-editor-canvas-guard', $scriptHandles);
        $this->assertNotContains('my-articles-filter', $scriptHandles);
        $this->assertNotContains('my-articles-load-more', $scriptHandles);
        $this->assertNotContains('my-articles-swiper-init', $scriptHandles);
        $this->assertNotContains('my-articles-responsive-layout', $scriptHandles);

        $guardSnippets = array_filter(
            is_array($mon_articles_test_inline_scripts) ? $mon_articles_test_inline_scripts : array(),
            static function (array $entry): bool {
                return $entry['handle'] === 'my-articles-editor-canvas-guard';
            }
        );

        $this->assertNotEmpty($guardSnippets);
        $this->assertStringContainsString('MY_ARTICLES_IS_EDITOR', (string) reset($guardSnippets)['data']);
    }

    public function test_frontend_scripts_are_skipped_in_editor_preview_context(): void
    {
        $this->assertTrue(My_Articles_Enqueue::should_enqueue_frontend_script());

        $GLOBALS['mon_articles_test_is_admin'] = true;
        $this->assertFalse(My_Articles_Enqueue::should_enqueue_frontend_script());
        unset($GLOBALS['mon_articles_test_is_admin']);

        $_GET['canvas'] = 'edit';
        $this->assertTrue(My_Articles_Enqueue::is_block_editor_preview_context());
        $this->assertFalse(My_Articles_Enqueue::should_enqueue_frontend_script());
        unset($_GET['canvas']);

        $script  = __DIR__ . '/phase2-rest-preview-context.php';
        $command = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($script);
        $output  = array();
        $status  = 0;
        exec($command, $output, $status);

        $this->assertSame(0, $status, implode(PHP_EOL, $output));
    }

    public function test_plugin_includes_load_display_state_builder(): void
    {
        $plugin = (string) file_get_contents($this->pluginRoot() . '/mon-affichage-articles.php');

        $this->assertStringContainsString(
            "class-my-articles-display-state-builder.php",
            $plugin,
            'Mon_Affichage_Articles::includes() must load the display-state builder used by the shortcode.'
        );

        $includesPos = strpos($plugin, 'function includes');
        $builderPos  = strpos($plugin, 'class-my-articles-display-state-builder.php');
        $shortcodePos = strpos($plugin, 'class-my-articles-shortcode.php');

        $this->assertNotFalse($includesPos);
        $this->assertNotFalse($builderPos);
        $this->assertGreaterThan($includesPos, $builderPos);
        $this->assertLessThan(
            $shortcodePos,
            $builderPos,
            'The display-state builder must be required before the shortcode class.'
        );
    }

    public function test_render_shortcode_does_not_fatal_without_display_state_builder(): void
    {
        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map,
            $mon_articles_test_wp_query_factory;

        $mon_articles_test_post_type_map   = array();
        $mon_articles_test_post_status_map = array();
        $mon_articles_test_post_meta_map   = array();
        $mon_articles_test_wp_query_factory = null;

        Mon_Affichage_Articles::get_instance();

        $this->assertTrue(
            class_exists('My_Articles_Display_State_Builder'),
            'The shortcode render path requires My_Articles_Display_State_Builder to be loaded.'
        );

        $instanceId = 52;
        $mon_articles_test_post_type_map[$instanceId]   = 'mon_affichage';
        $mon_articles_test_post_status_map[$instanceId] = 'publish';
        $mon_articles_test_post_meta_map[$instanceId]   = array(
            '_my_articles_settings' => array(
                'display_mode'   => 'grid',
                'posts_per_page' => 6,
            ),
        );
        $mon_articles_test_wp_query_factory = static function () {
            return array(
                'posts'       => array(),
                'found_posts' => 0,
            );
        };

        $output = My_Articles_Shortcode::get_instance()->render_shortcode(array('id' => (string) $instanceId));

        $this->assertIsString($output);
        $this->assertNotSame('', $output);
        $this->assertStringContainsString('my-articles', $output);
    }

    /**
     * @param 'style'|'script' $type
     * @return array<int, string>
     */
    private function enqueuedHandles(string $type): array
    {
        global $mon_articles_test_enqueued_styles, $mon_articles_test_enqueued_scripts;

        $entries = 'style' === $type ? $mon_articles_test_enqueued_styles : $mon_articles_test_enqueued_scripts;
        if (!is_array($entries)) {
            return array();
        }

        return array_values(array_map(
            static function (array $entry): string {
                return $entry['handle'];
            },
            $entries
        ));
    }
}
}
