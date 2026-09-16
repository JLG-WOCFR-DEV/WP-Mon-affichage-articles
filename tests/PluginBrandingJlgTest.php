<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use PHPUnit\Framework\TestCase;

final class PluginBrandingJlgTest extends TestCase
{
    private function pluginRoot(): string
    {
        return dirname(__DIR__) . '/mon-affichage-article';
    }

    private function repoRoot(): string
    {
        return dirname(__DIR__);
    }

    public function test_plugin_header_uses_tuiles_jlg_and_jerome_le_gousse(): void
    {
        $plugin = (string) file_get_contents($this->pluginRoot() . '/mon-affichage-articles.php');

        $this->assertMatchesRegularExpression('/Plugin Name:\s*Tuiles - JLG/', $plugin);
        $this->assertMatchesRegularExpression('/Author:\s*Jérôme Le Gousse/', $plugin);
        $this->assertMatchesRegularExpression('/Text Domain:\s*mon-articles/', $plugin);
        $this->assertDoesNotMatchRegularExpression('/Plugin Name:.*LCV/', $plugin);
        $this->assertDoesNotMatchRegularExpression('/Author:\s*LCV\b/', $plugin);
    }

    public function test_readme_txt_declares_tuiles_jlg(): void
    {
        $readmePath = $this->pluginRoot() . '/readme.txt';
        $this->assertFileExists($readmePath);

        $readme = (string) file_get_contents($readmePath);
        $this->assertStringContainsString('=== Tuiles - JLG ===', $readme);
        $this->assertStringContainsString('Jérôme Le Gousse', $readme);
        $this->assertStringNotContainsString('LCV', $readme);
    }

    public function test_root_readme_uses_tuiles_jlg(): void
    {
        $readme = (string) file_get_contents($this->repoRoot() . '/README.md');

        $this->assertStringContainsString('# Tuiles - JLG', $readme);
        $this->assertStringContainsString('Jérôme Le Gousse', $readme);
        $this->assertStringNotContainsString('Tuiles - LCV', $readme);
        $this->assertStringNotContainsString('Tuiles – LCV', $readme);
        $this->assertStringNotContainsString('Classique LCV', $readme);
        $this->assertStringContainsString('Classique JLG', $readme);
        $this->assertStringContainsString('`lcv-classique`', $readme);
    }

    public function test_block_json_title_keywords_and_description(): void
    {
        $block = json_decode(
            (string) file_get_contents($this->pluginRoot() . '/blocks/mon-affichage-articles/block.json'),
            true
        );

        $this->assertIsArray($block);
        $this->assertSame('Tuiles - JLG', $block['title']);
        $this->assertSame('mon-affichage/articles', $block['name']);
        $this->assertSame('mon-articles', $block['textdomain']);
        $this->assertStringContainsString('Tuiles - JLG', (string) $block['description']);
        $this->assertStringNotContainsString('LCV', (string) $block['description']);
        $this->assertContains('jlg', $block['keywords']);
        $this->assertNotContains('lcv', $block['keywords']);
    }

    public function test_editor_onboarding_and_placeholder_use_tuiles_jlg(): void
    {
        $editJs = (string) file_get_contents($this->pluginRoot() . '/blocks/mon-affichage-articles/edit.js');

        $this->assertStringContainsString("Bienvenue dans Tuiles - JLG", $editJs);
        $this->assertStringContainsString("label: __('Tuiles - JLG', 'mon-articles')", $editJs);
        $this->assertStringNotContainsString('Tuiles – LCV', $editJs);
        $this->assertStringNotContainsString('Tuiles - LCV', $editJs);
    }

    public function test_classic_preset_keeps_slug_and_renames_visible_label(): void
    {
        $manifest = json_decode(
            (string) file_get_contents($this->pluginRoot() . '/config/design-presets/lcv-classique/manifest.json'),
            true
        );

        $this->assertIsArray($manifest);
        $this->assertSame('lcv-classique', $manifest['id']);
        $this->assertSame('Classique JLG', $manifest['label']);
        $this->assertStringNotContainsString('LCV', (string) $manifest['label']);
    }

    public function test_cpt_admin_menu_and_settings_titles_use_tuiles_jlg(): void
    {
        $plugin   = (string) file_get_contents($this->pluginRoot() . '/mon-affichage-articles.php');
        $settings = (string) file_get_contents($this->pluginRoot() . '/includes/class-my-articles-settings.php');

        $this->assertStringContainsString("__( 'Tuiles - JLG', 'mon-articles' )", $plugin);
        $this->assertStringContainsString("register_post_type( 'mon_affichage'", $plugin);
        $this->assertStringContainsString("esc_html_e( 'Réglages Tuiles - JLG', 'mon-articles' )", $settings);
        $this->assertStringContainsString("'my-articles-settings'", $settings);
        $this->assertStringContainsString('my-articles-admin-instrumentation', $settings);
        $this->assertStringNotContainsString('Réglages Tuiles - LCV', $settings);
    }

    public function test_wp_cli_help_mentions_tuiles_jlg(): void
    {
        $cli = (string) file_get_contents($this->pluginRoot() . '/includes/class-my-articles-cli.php');

        $this->assertStringContainsString('Tuiles - JLG', $cli);
        $this->assertStringContainsString("WP_CLI::add_command( 'my-articles presets'", $cli);
        $this->assertStringNotContainsString('LCV', $cli);
    }

    public function test_i18n_json_translates_tuiles_jlg(): void
    {
        $translationsPath = $this->pluginRoot() . '/languages/mon-articles-fr_FR-d38246567e142318f24686bcaa0ee4b1.json';
        $decoded          = json_decode((string) file_get_contents($translationsPath), true);

        $this->assertIsArray($decoded);
        $entries = $decoded['locale_data']['mon-articles'] ?? array();
        $this->assertArrayHasKey('Tuiles - JLG', $entries);
        $this->assertSame('Tuiles - JLG', $entries['Tuiles - JLG'][0] ?? null);
        $this->assertArrayNotHasKey('Tuiles – LCV', $entries);
        $this->assertArrayHasKey('Bienvenue dans Tuiles - JLG', $entries);
    }

    public function test_stable_slugs_and_prefixes_are_unchanged(): void
    {
        $plugin    = (string) file_get_contents($this->pluginRoot() . '/mon-affichage-articles.php');
        $shortcode = (string) file_get_contents($this->pluginRoot() . '/includes/class-my-articles-shortcode.php');
        $settings  = (string) file_get_contents($this->pluginRoot() . '/includes/class-my-articles-settings.php');

        $this->assertStringContainsString("add_shortcode( 'mon_affichage_articles'", $shortcode);
        $this->assertStringContainsString("private \$option_name = 'my_articles_options'", $settings);
        $this->assertStringContainsString("register_post_type( 'mon_affichage'", $plugin);
        $this->assertStringContainsString('* Text Domain:       mon-articles', $plugin);
        $this->assertStringContainsString('my-articles-wrapper', $shortcode);
    }

    public function test_preview_adapter_requires_its_content_interface(): void
    {
        $adapter = (string) file_get_contents($this->pluginRoot() . '/includes/class-my-articles-block-preview-adapter.php');

        $this->assertStringContainsString('interface-my-articles-content-adapter.php', $adapter);
    }

    public function test_metabox_uses_native_wp_admin_notice_instead_of_custom_banner(): void
    {
        $metaboxes = (string) file_get_contents($this->pluginRoot() . '/includes/class-my-articles-metaboxes.php');

        $this->assertStringContainsString('notice notice-info inline', $metaboxes);
        $this->assertStringNotContainsString('background-color: #f0f6fc', $metaboxes);
        $this->assertDoesNotMatchRegularExpression('/^\s*\.button-primary\b/m', $metaboxes);
    }

    public function test_user_visible_lcv_branding_is_gone_except_stable_slugs(): void
    {
        $roots = array(
            $this->pluginRoot(),
            $this->repoRoot() . '/README.md',
            $this->repoRoot() . '/docs',
            $this->repoRoot() . '/composer.json',
        );

        $hits = array();

        foreach ($roots as $root) {
            $paths = is_dir($root) ? $this->phpJsJsonMdFiles($root) : array($root);

            foreach ($paths as $path) {
                if (!is_file($path)) {
                    continue;
                }

                $relative = str_replace($this->repoRoot() . '/', '', $path);
                if (str_contains($relative, '/vendor/')) {
                    continue;
                }

                $contents = (string) file_get_contents($path);
                $normalized = str_replace(
                    array('lcv-classique', 'lcv/mon-affichage-articles'),
                    array('{{PRESET_SLUG}}', '{{COMPOSER_PACKAGE}}'),
                    $contents
                );

                if (preg_match('/\bLCV\b|\blcv\b/', $normalized)) {
                    $hits[] = $relative;
                }
            }
        }

        sort($hits);

        $this->assertSame(
            array(),
            $hits,
            'LCV must not remain as user-visible product branding. Stable slug lcv-classique and composer package lcv/mon-affichage-articles are allowed.'
        );
    }

    /**
     * @return array<int, string>
     */
    private function phpJsJsonMdFiles(string $directory): array
    {
        $files    = array();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            if (!in_array($ext, array('php', 'js', 'json', 'md', 'txt', 'css'), true)) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }
}
