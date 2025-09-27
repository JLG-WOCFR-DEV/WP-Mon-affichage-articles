<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ShortcodeVisibilityTest extends TestCase
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

        global $mon_articles_test_post_type_map, $mon_articles_test_post_status_map;

        $mon_articles_test_post_type_map   = array();
        $mon_articles_test_post_status_map = array();

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
        global $mon_articles_test_post_type_map, $mon_articles_test_post_status_map;

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

        $mon_articles_test_post_type_map   = array();
        $mon_articles_test_post_status_map = array();

        parent::tearDown();
    }

    public function test_render_shortcode_returns_empty_string_when_instance_unpublished(): void
    {
        global $mon_articles_test_post_type_map, $mon_articles_test_post_status_map;

        $instanceId = 4321;

        $mon_articles_test_post_type_map[$instanceId]   = 'mon_affichage';
        $mon_articles_test_post_status_map[$instanceId] = 'draft';

        $shortcode = My_Articles_Shortcode::get_instance();

        $output = $shortcode->render_shortcode(array('id' => (string) $instanceId));

        $this->assertSame('', $output);
    }
}
