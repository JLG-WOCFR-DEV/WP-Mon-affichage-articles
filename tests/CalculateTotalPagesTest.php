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
        yield 'unlimited layout still reports single page' => array(2, 3, 0, 1, 0);
        yield 'pinned fill first page regular still pending' => array(4, 5, 4, 3, 2);
    }

    public function test_calculate_total_pages_can_be_filtered(): void
    {
        global $mon_articles_test_filters;

        $mon_articles_test_filters = array();

        \add_filter(
            'my_articles_calculate_total_pages',
            static function (array $result, int $pinned, int $regular, int $perPage): array {
                if (0 === $perPage) {
                    $result['total_pages'] = $pinned + $regular;
                }

                return $result;
            },
            10,
            4
        );

        $result = \my_articles_calculate_total_pages(2, 3, 0);

        self::assertSame(5, $result['total_pages']);
        self::assertSame(0, $result['next_page']);

        $mon_articles_test_filters = array();
    }
}
