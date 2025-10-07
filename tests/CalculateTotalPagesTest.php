<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use PHPUnit\Framework\TestCase;

final class CalculateTotalPagesTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!function_exists('my_articles_calculate_total_pages')) {
            require_once dirname(__DIR__) . '/mon-affichage-article/includes/helpers.php';
        }
    }

    /**
     * @param int $pinned
     * @param int $regular
     * @param int $perPage
     * @param int $expectedPages
     * @param int $expectedNext
     *
     * @dataProvider providePaginationScenarios
     */
    public function test_calculate_total_pages_handles_mixed_content(
        int $pinned,
        int $regular,
        int $perPage,
        int $expectedPages,
        int $expectedNext
    ): void {
        $result = \my_articles_calculate_total_pages($pinned, $regular, $perPage);

        self::assertSame(
            $expectedPages,
            $result['total_pages'] ?? null,
            'Unexpected total page count returned by my_articles_calculate_total_pages().'
        );

        self::assertSame(
            $expectedNext,
            $result['next_page'] ?? null,
            'Unexpected next page index returned by my_articles_calculate_total_pages().'
        );

        self::assertIsArray(
            $result['meta'] ?? null,
            'Expected pagination metadata to be exposed.'
        );

        $meta = $result['meta'];

        self::assertSame(
            $pinned + $regular,
            $meta['total_items'] ?? null,
            'Pagination metadata should report the total item count.'
        );

        self::assertArrayHasKey('first_page', $meta);
        self::assertIsArray($meta['first_page']);
        self::assertArrayHasKey('projected_total_pages', $meta);
        self::assertSame(
            $expectedPages,
            $meta['projected_total_pages'] ?? null,
            'Expected projected_total_pages to align with total_pages when no analytics override is provided.'
        );
    }

    /**
     * @return iterable<string, array{0:int,1:int,2:int,3:int,4:int}>
     */
    public function providePaginationScenarios(): iterable
    {
        yield 'no content available' => array(0, 0, 6, 0, 0);
        yield 'pinned and regular fit first page' => array(1, 5, 4, 2, 2);
        yield 'pinned overflow onto extra page' => array(5, 0, 3, 2, 2);
        yield 'regular backlog after pinned content' => array(2, 10, 3, 4, 2);
        yield 'unlimited layout reports unbounded' => array(2, 3, 0, 0, 0);
        yield 'pinned fill first page regular still pending' => array(4, 5, 4, 3, 2);
    }

    public function test_calculate_total_pages_can_be_filtered(): void
    {
        global $mon_articles_test_filters;

        $mon_articles_test_filters = array();

        \add_filter(
            'my_articles_calculate_total_pages',
            static function (array $result, int $pinned, int $regular, int $perPage, array $context = array()): array {
                if (0 === $perPage) {
                    $result['total_pages'] = $pinned + $regular;
                }

                return $result;
            },
            10,
            5
        );

        $result = \my_articles_calculate_total_pages(2, 3, 0);

        self::assertSame(5, $result['total_pages']);
        self::assertSame(0, $result['next_page']);

        $mon_articles_test_filters = array();
    }

    public function test_calculate_total_pages_reports_metadata_breakdown(): void
    {
        $result = \my_articles_calculate_total_pages(3, 7, 4, array('current_page' => 1));

        $meta = $result['meta'] ?? array();

        self::assertSame(10, $meta['total_items'] ?? null);
        self::assertSame(3, $meta['first_page']['pinned'] ?? null);
        self::assertSame(1, $meta['first_page']['regular'] ?? null);
        self::assertSame(6, $meta['remaining_items'] ?? null);
        self::assertSame(6, $meta['remaining_regular'] ?? null);
        self::assertSame(0, $meta['remaining_pinned'] ?? null);
        self::assertArrayHasKey('is_unbounded', $meta);
        self::assertFalse($meta['is_unbounded']);
    }

    public function test_calculate_total_pages_supports_unlimited_projection(): void
    {
        $result = \my_articles_calculate_total_pages(
            2,
            6,
            0,
            array(
                'current_page' => 1,
                'unlimited_page_size' => 4,
            )
        );

        $meta = $result['meta'] ?? array();

        self::assertSame(0, $result['total_pages']);
        self::assertTrue($meta['is_unbounded'] ?? false);
        self::assertSame(8, $meta['total_items'] ?? null);
        self::assertArrayHasKey('projected_page_size', $meta);
        self::assertSame(4, $meta['projected_page_size']);
        self::assertSame(2, $meta['projected_total_pages'] ?? null);
        self::assertSame(4, $meta['projected_remaining_items'] ?? null);
    }
}
