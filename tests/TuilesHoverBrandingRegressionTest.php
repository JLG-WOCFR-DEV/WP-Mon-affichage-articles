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
        echo '<p class="submit"><input type="submit" class="button button-primary" value="Save" /></p>';
    }
}

}

namespace MonAffichageArticles\Tests {

use My_Articles_Settings;
use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Hover + branding contracts after the Tuiles - JLG rename.
 *
 * Card hover is CSS-only. These tests lock the :hover rules, wrapper classes,
 * card links/overlays, admin titles, and the absence of leftover LCV in
 * tooltips / titles / aria labels.
 */
final class TuilesHoverBrandingRegressionTest extends TestCase
{
    /** @var mixed */
    private $shortcodeInstanceBackup;

    /** @var array<string, mixed> */
    private array $normalizedOptionsCacheBackup = array();

    /** @var array<string, mixed> */
    private array $matchingPinnedCacheBackup = array();

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

        require_once $this->pluginRoot() . '/includes/class-my-articles-settings.php';

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

        unset($_GET['tab']);
    }

    protected function tearDown(): void
    {
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

        unset($_GET['tab']);

        parent::tearDown();
    }

    public function test_default_options_enable_desktop_hover_lift(): void
    {
        $defaults = My_Articles_Shortcode::get_default_options();

        $this->assertSame(1, (int) $defaults['hover_lift_desktop']);
        $this->assertSame(0, (int) $defaults['hover_neon_pulse']);
        $this->assertArrayHasKey('meta_color_hover', $defaults);
        $this->assertArrayHasKey('shadow_color_hover', $defaults);
        $this->assertSame(1, (int) $defaults['slideshow_pause_on_mouse_enter']);
    }

    public function test_front_css_keeps_card_hover_lift_overlay_and_link_rules(): void
    {
        $css = (string) file_get_contents($this->pluginRoot() . '/assets/css/styles.css');

        $this->assertStringContainsString('@media (hover: hover) and (pointer: fine)', $css);
        $this->assertStringContainsString(
            '.my-articles-wrapper.my-articles-has-hover-lift .my-article-item:hover',
            $css
        );
        $this->assertStringContainsString(
            'transform: translateY(var(--my-articles-hover-lift-offset, -6px));',
            $css
        );
        $this->assertStringContainsString(
            '.my-articles-wrapper.my-articles-has-hover-lift .my-article-item:hover::before',
            $css
        );
        $this->assertStringContainsString(
            '.my-articles-wrapper.my-articles-has-neon-pulse .my-article-item:hover::after',
            $css
        );
        $this->assertStringContainsString(
            '.my-article-item:hover .article-thumbnail-wrapper::after',
            $css
        );
        $this->assertStringContainsString(
            '.my-article-item:hover .article-thumbnail-link::after',
            $css
        );
        $this->assertStringContainsString(
            '.my-article-item:hover .article-title-link::after',
            $css
        );
        $this->assertStringContainsString(
            '.my-article-item:hover .article-meta',
            $css
        );
        $this->assertStringContainsString('var(--my-articles-meta-hover-color)', $css);
        $this->assertStringContainsString('--my-articles-shadow-color-hover', $css);
        $this->assertStringContainsString('.my-article-item:hover img', $css);
        $this->assertStringContainsString(
            '.my-article-item .my-article-excerpt .my-article-read-more:hover',
            $css
        );
        $this->assertStringContainsString(
            '.my-articles-wrapper.my-articles-has-hover-lift .my-article-item:hover',
            $css
        );
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        $this->assertDoesNotMatchRegularExpression('/\bLCV\b|\blcv\b/', $css);
    }

    public function test_editor_css_keeps_block_prefixed_hover_lift_rules(): void
    {
        $css = (string) file_get_contents($this->pluginRoot() . '/blocks/mon-affichage-articles/editor.css');

        $this->assertStringContainsString('@media (hover: hover) and (pointer: fine)', $css);
        $this->assertStringContainsString(
            '.wp-block-mon-affichage-articles .my-articles-wrapper.my-articles-has-hover-lift .my-article-item:hover',
            $css
        );
        $this->assertStringContainsString(
            'transform: translateY(var(--my-articles-hover-lift-offset, -6px));',
            $css
        );
        $this->assertStringContainsString(
            '.wp-block-mon-affichage-articles .my-articles-wrapper.my-articles-has-neon-pulse .my-article-item:hover::after',
            $css
        );
        $this->assertDoesNotMatchRegularExpression('/\bLCV\b|\blcv\b/', $css);
    }

    public function test_plugin_js_has_no_card_hover_handlers_and_no_lcv_titles(): void
    {
        $jsDir = $this->pluginRoot() . '/assets/js';
        $files = glob($jsDir . '/*.js');
        $this->assertNotFalse($files);
        $this->assertNotEmpty($files);

        $joined = '';

        foreach ($files as $file) {
            $contents = (string) file_get_contents($file);
            $joined  .= $contents . "\n";

            $this->assertDoesNotMatchRegularExpression(
                '/\bLCV\b|\blcv\b/',
                $contents,
                basename($file) . ' must not expose LCV in hover titles or tooltips.'
            );
            $this->assertStringNotContainsString(
                "addEventListener('mouseenter'",
                $contents,
                basename($file) . ' must not bind card mouseenter handlers; hover is CSS-only.'
            );
            $this->assertStringNotContainsString("addEventListener('mouseover'", $contents);
            $this->assertStringNotContainsString('.on(\'mouseenter\'', $contents);
            $this->assertStringNotContainsString('.on("mouseenter"', $contents);
            $this->assertStringNotContainsString('.my-article-item:hover', $contents);
        }

        $this->assertStringContainsString('pauseOnMouseEnter', $joined);
        $this->assertStringContainsString('pause_on_mouse_enter', $joined);
    }

    public function test_shortcode_default_hover_renders_lift_class_cards_and_links(): void
    {
        $output = $this->renderGridInstance(
            6101,
            array(
                'display_mode'          => 'grid',
                'posts_per_page'        => 2,
                'enable_keyword_search' => 0,
                'show_category_filter'  => 0,
                'pagination_mode'       => 'none',
                'enable_lazy_load'      => 0,
            ),
            array(7101, 7102)
        );

        $this->assertStringContainsString('my-articles-wrapper', $output);
        $this->assertStringContainsString('my-articles-has-hover-lift', $output);
        $this->assertStringNotContainsString('my-articles-has-neon-pulse', $output);
        $this->assertStringContainsString('my-article-item', $output);
        $this->assertStringContainsString('class="my-article-link"', $output);
        $this->assertStringContainsString('href="', $output);
        $this->assertStringContainsString('article-thumbnail-wrapper', $output);
        $this->assertStringContainsString('article-thumbnail-link', $output);
        $this->assertStringContainsString('article-title-link', $output);
        $this->assertSame(2, substr_count($output, '<article'));
        $this->assertDoesNotMatchRegularExpression('/<div\s*>/', $output);
        $this->assertNoLcvInHoverTitles($output);
    }

    public function test_shortcode_can_enable_neon_pulse_and_disable_hover_lift(): void
    {
        $withNeon = $this->renderGridInstance(
            6102,
            array(
                'display_mode'       => 'grid',
                'posts_per_page'     => 1,
                'pagination_mode'    => 'none',
                'enable_lazy_load'   => 0,
                'hover_lift_desktop' => 1,
                'hover_neon_pulse'   => 1,
            ),
            array(7201)
        );

        $this->resetShortcodeCaches();

        $withoutHover = $this->renderGridInstance(
            6103,
            array(
                'display_mode'       => 'grid',
                'posts_per_page'     => 1,
                'pagination_mode'    => 'none',
                'enable_lazy_load'   => 0,
                'hover_lift_desktop' => 0,
                'hover_neon_pulse'   => 0,
            ),
            array(7202)
        );

        $this->assertStringContainsString('my-articles-has-hover-lift', $withNeon);
        $this->assertStringContainsString('my-articles-has-neon-pulse', $withNeon);
        $this->assertStringNotContainsString('my-articles-has-hover-lift', $withoutHover);
        $this->assertStringNotContainsString('my-articles-has-neon-pulse', $withoutHover);
        $this->assertStringContainsString('my-article-item', $withoutHover);
    }

    public function test_inline_styles_keep_hover_color_custom_properties(): void
    {
        $reflection = new ReflectionClass(My_Articles_Shortcode::class);
        $instance   = $reflection->newInstanceWithoutConstructor();
        $method     = $reflection->getMethod('render_inline_styles');
        $method->setAccessible(true);

        $styles = $method->invoke(
            $instance,
            array(
                'meta_color_hover'   => '#112233',
                'shadow_color_hover' => 'rgba(1,2,3,0.4)',
            ),
            88
        );

        $this->assertStringContainsString('--my-articles-meta-hover-color: #112233;', $styles);
        $this->assertStringContainsString('--my-articles-shadow-color-hover: rgba(1, 2, 3, 0.4);', $styles);
        $this->assertDoesNotMatchRegularExpression('/\bLCV\b|\blcv\b/', $styles);
    }

    public function test_rendered_article_item_keeps_overlay_targets_and_has_no_lcv_title(): void
    {
        $reflection = new ReflectionClass(My_Articles_Shortcode::class);
        $shortcode  = $reflection->newInstanceWithoutConstructor();

        ob_start();
        $shortcode->render_article_item(
            array(
                'display_mode'      => 'grid',
                'show_category'     => false,
                'show_author'       => true,
                'show_date'         => true,
                'show_excerpt'      => true,
                'excerpt_length'    => 12,
                'excerpt_more_text' => 'Lire la suite',
                'resolved_taxonomy' => '',
                'enable_lazy_load'  => false,
                'pinned_show_badge' => false,
            ),
            false
        );
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('<article class="my-article-item">', $output);
        $this->assertStringContainsString('class="my-article-link"', $output);
        $this->assertStringContainsString('article-thumbnail-wrapper', $output);
        $this->assertStringContainsString('article-title-link', $output);
        $this->assertStringContainsString('Sample Title', $output);
        $this->assertStringNotContainsString('title="Tuiles', $output);
        $this->assertNoLcvInHoverTitles($output);
    }

    public function test_admin_cpt_menu_and_settings_titles_remain_tuiles_jlg(): void
    {
        $plugin   = (string) file_get_contents($this->pluginRoot() . '/mon-affichage-articles.php');
        $settings = (string) file_get_contents($this->pluginRoot() . '/includes/class-my-articles-settings.php');

        $this->assertStringContainsString("'menu_name' => __( 'Tuiles - JLG', 'mon-articles' )", $plugin);
        $this->assertStringContainsString("'name' => _x( 'Affichages Articles'", $plugin);
        $this->assertStringNotContainsString("'menu_name' => __( 'Tuiles - LCV'", $plugin);
        $this->assertStringNotContainsString("'menu_name' => __( 'Tuiles – LCV'", $plugin);

        $this->assertStringContainsString("__( 'Réglages Tuiles - JLG', 'mon-articles' )", $settings);
        $this->assertStringContainsString("esc_html_e( 'Réglages Tuiles - JLG', 'mon-articles' )", $settings);
        $this->assertStringContainsString(
            "esc_attr__( 'Sections des réglages Tuiles - JLG', 'mon-articles' )",
            $settings
        );

        $page = My_Articles_Settings::get_instance();
        ob_start();
        $page->create_admin_page();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('<h1>', $output);
        $this->assertStringContainsString('Réglages Tuiles - JLG', $output);
        $this->assertStringContainsString('aria-label="Sections des réglages Tuiles - JLG"', $output);
        $this->assertStringNotContainsString('LCV', $output);
        $this->assertNoLcvInHoverTitles($output);
    }

    public function test_admin_css_comment_and_plugin_js_titles_have_no_lcv(): void
    {
        $adminCss = (string) file_get_contents($this->pluginRoot() . '/assets/css/admin.css');
        $this->assertStringContainsString('Tuiles - JLG', $adminCss);
        $this->assertStringNotContainsString('LCV', $adminCss);
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<int, int>      $postIds
     */
    private function renderGridInstance(int $instanceId, array $settings, array $postIds): string
    {
        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map,
            $mon_articles_test_wp_query_factory;

        $posts = array();
        foreach ($postIds as $postId) {
            $posts[] = array(
                'ID'           => $postId,
                'post_author'  => 1,
                'post_title'   => 'Post ' . $postId,
                'post_type'    => 'post',
                'post_status'  => 'publish',
                'post_content' => 'Content ' . $postId,
            );
        }

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

        return My_Articles_Shortcode::get_instance()->render_shortcode(array('id' => (string) $instanceId));
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

    private function assertNoLcvInHoverTitles(string $html): void
    {
        $this->assertDoesNotMatchRegularExpression('/\bLCV\b|\blcv\b/', $html);

        if (preg_match_all('/\s(?:title|alt|aria-label|aria-roledescription|aria-describedby)="([^"]*)"/i', $html, $matches)) {
            foreach ($matches[1] as $value) {
                $this->assertDoesNotMatchRegularExpression(
                    '/\bLCV\b|\blcv\b|Tuiles\s+[–-]\s+LCV/',
                    $value,
                    'Hover titles, tooltips and ARIA labels must not keep LCV branding.'
                );
            }
        }
    }
}
}
