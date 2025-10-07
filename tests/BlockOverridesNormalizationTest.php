<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use My_Articles_Block;
use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;

final class BlockOverridesNormalizationTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        global $mon_articles_test_filters;
        $mon_articles_test_filters = array();
    }

    public function test_prepare_overrides_casts_values_using_schema(): void
    {
        $attributes = array(
            'slideshow_loop' => false,
            'slideshow_delay' => 300,
            'columns_desktop' => '5',
            'module_bg_color' => 123,
            'filter_categories' => array('featured'),
        );

        $overrides = My_Articles_Block::prepare_overrides_from_attributes($attributes);

        $this->assertSame(0, $overrides['slideshow_loop']);
        $this->assertSame(1000, $overrides['slideshow_delay']);
        $this->assertSame(5, $overrides['columns_desktop']);
        $this->assertSame('123', $overrides['module_bg_color']);
        $this->assertSame(array('featured'), $overrides['filter_categories']);
    }

    public function test_override_schema_filter_can_extend_supported_attributes(): void
    {
        $filter = static function (array $schema): array {
            $schema['custom_flag'] = array('type' => 'bool');

            return $schema;
        };

        add_filter('my_articles_block_override_schema', $filter, 10, 2);

        $overrides = My_Articles_Block::prepare_overrides_from_attributes(
            array(
                'custom_flag' => 'yes',
                'slideshow_delay' => 0,
            )
        );

        $this->assertSame(1, $overrides['custom_flag']);
        $this->assertSame(
            My_Articles_Shortcode::get_default_options()['slideshow_delay'],
            $overrides['slideshow_delay']
        );
    }
}
