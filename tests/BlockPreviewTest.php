<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use My_Articles_Block;
use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class BlockPreviewTest extends TestCase
{
    private ?ReflectionProperty $shortcodeInstanceProperty = null;

    /** @var mixed */
    private $originalShortcodeInstance;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\My_Articles_Block::class)) {
            require_once __DIR__ . '/../mon-affichage-article/includes/class-my-articles-block-preview-adapter.php';
            require_once __DIR__ . '/../mon-affichage-article/includes/class-my-articles-block.php';
        }

        $reflection = new ReflectionClass(My_Articles_Shortcode::class);
        $this->shortcodeInstanceProperty = $reflection->getProperty('instance');
        $this->shortcodeInstanceProperty->setAccessible(true);
        $this->originalShortcodeInstance = $this->shortcodeInstanceProperty->getValue();
    }

    protected function tearDown(): void
    {
        if ($this->shortcodeInstanceProperty instanceof ReflectionProperty) {
            $this->shortcodeInstanceProperty->setValue(null, $this->originalShortcodeInstance);
        }

        $this->setContext(false, false);

        global $mon_articles_test_wp_query_factory;
        $mon_articles_test_wp_query_factory = null;

        remove_all_filters('my_articles_block_preview_data');

        parent::tearDown();
    }

    public function test_editor_preview_uses_synthetic_dataset_and_skips_expensive_calls(): void
    {
        global $mon_articles_test_wp_query_factory;

        $this->setContext(true, false);

        $queryCalls = 0;
        $previousFactory = $mon_articles_test_wp_query_factory ?? null;
        $mon_articles_test_wp_query_factory = static function (array $args) use (&$queryCalls): array {
            $queryCalls++;

            return array(
                'posts'       => array(),
                'found_posts' => 0,
            );
        };

        $shortcodeStub = new class {
            public int $renderCalls = 0;

            public function render_shortcode($atts)
            {
                $this->renderCalls++;

                return '<div>front</div>';
            }
        };

        $this->shortcodeInstanceProperty->setValue(null, $shortcodeStub);

        try {
            $output = My_Articles_Block::get_instance()->render_block(array('instanceId' => 12));

            $this->assertStringContainsString('my-articles-block-preview', $output);
            $this->assertStringContainsString('Article de démonstration 1', $output);
            $this->assertSame(0, $shortcodeStub->renderCalls);
            $this->assertSame(0, $queryCalls);
        } finally {
            $mon_articles_test_wp_query_factory = $previousFactory;
        }
    }

    public function test_preview_dataset_can_be_customized_via_filter(): void
    {
        $this->setContext(true, false);

        $shortcodeStub = new class {
            public int $renderCalls = 0;

            public function render_shortcode($atts)
            {
                $this->renderCalls++;

                return '<div>front</div>';
            }
        };
        $this->shortcodeInstanceProperty->setValue(null, $shortcodeStub);

        add_filter(
            'my_articles_block_preview_data',
            static function (array $data): array {
                $data['items'][0]['title'] = 'Titre personnalisé';
                $data['cta']['label']      = 'Découvrir la sélection';

                return $data;
            },
            10,
            3
        );

        $output = My_Articles_Block::get_instance()->render_block(array('instanceId' => 3));

        $this->assertStringContainsString('Titre personnalisé', $output);
        $this->assertStringContainsString('Découvrir la sélection', $output);
        $this->assertSame(0, $shortcodeStub->renderCalls);
    }

    public function test_front_render_still_delegates_to_shortcode(): void
    {
        $this->setContext(false, false);

        $shortcodeStub = new class {
            public int $renderCalls = 0;
            /** @var array<string, mixed>|null */
            public $lastAtts = null;

            public function render_shortcode($atts)
            {
                $this->renderCalls++;
                $this->lastAtts = $atts;

                return '<div>from shortcode</div>';
            }
        };
        $this->shortcodeInstanceProperty->setValue(null, $shortcodeStub);

        $output = My_Articles_Block::get_instance()->render_block(
            array(
                'instanceId'     => 42,
                'slideshow_loop' => true,
            )
        );

        $this->assertSame('<div>from shortcode</div>', $output);
        $this->assertSame(1, $shortcodeStub->renderCalls);
        $this->assertIsArray($shortcodeStub->lastAtts);
        $this->assertSame(42, $shortcodeStub->lastAtts['id']);
        $this->assertSame(1, $shortcodeStub->lastAtts['overrides']['slideshow_loop']);
    }

    private function setContext(bool $isAdmin, bool $doingAjax): void
    {
        if ($isAdmin) {
            $GLOBALS['mon_articles_test_is_admin'] = true;
        } else {
            unset($GLOBALS['mon_articles_test_is_admin']);
        }

        if ($doingAjax) {
            $GLOBALS['mon_articles_test_doing_ajax'] = true;
        } else {
            unset($GLOBALS['mon_articles_test_doing_ajax']);
        }
    }
}
