<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use Mon_Affichage_Articles;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @covers \Mon_Affichage_Articles::sanitize_filters_parameter
 */
final class FilterParameterSanitizerTest extends TestCase
{
    /** @var ReflectionMethod */
    private $sanitizerMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizerMethod = new ReflectionMethod(Mon_Affichage_Articles::class, 'sanitize_filters_parameter');
        $this->sanitizerMethod->setAccessible(true);
    }

    /**
     * @param mixed $input
     * @return array<int, array{taxonomy:string, slug:string}>
     */
    private function sanitize($input): array
    {
        $plugin = Mon_Affichage_Articles::get_instance();

        /** @var array<int, array{taxonomy:string, slug:string}> $result */
        $result = $this->sanitizerMethod->invoke($plugin, $input);

        return $result;
    }

    public function test_sanitize_filters_parameter_handles_json_string(): void
    {
        $input = '[{"taxonomy":"category","slug":"World-News"}]';

        $result = $this->sanitize($input);

        $this->assertSame(
            array(
                array('taxonomy' => 'category', 'slug' => 'world-news'),
            ),
            $result
        );
    }

    public function test_sanitize_filters_parameter_unslashes_nested_arrays(): void
    {
        $input = array(
            array('taxonomy' => 'category', 'slug' => '  Featured  '),
            array('taxonomy' => 'post_tag', 'slug' => 'Top-Story'),
        );

        $result = $this->sanitize($input);

        $this->assertSame(
            array(
                array('taxonomy' => 'category', 'slug' => 'featured'),
                array('taxonomy' => 'post_tag', 'slug' => 'top-story'),
            ),
            $result
        );
    }

    public function test_sanitize_filters_parameter_rejects_non_iterable_values(): void
    {
        $this->assertSame(array(), $this->sanitize(123));
        $this->assertSame(array(), $this->sanitize(null));
    }
}
