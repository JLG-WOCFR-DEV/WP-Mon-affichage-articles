<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use PHPUnit\Framework\TestCase;

final class NormalizeInternalUrlTest extends TestCase
{
    public function test_accepts_absolute_internal_url(): void
    {
        $result = \my_articles_normalize_internal_url('http://example.com/ma-page');

        $this->assertSame('http://example.com/ma-page', $result);
    }

    public function test_accepts_relative_path(): void
    {
        $result = \my_articles_normalize_internal_url('/ma-page');

        $this->assertSame('http://example.com/ma-page', $result);
    }

    public function test_accepts_relative_query_string(): void
    {
        $result = \my_articles_normalize_internal_url('?foo=bar');

        $this->assertSame('http://example.com?foo=bar', $result);
    }

    public function test_strips_fragment_from_relative_url(): void
    {
        $result = \my_articles_normalize_internal_url('/ma-page#section');

        $this->assertSame('http://example.com/ma-page', $result);
    }

    public function test_rejects_external_domain(): void
    {
        $result = \my_articles_normalize_internal_url('http://foreign.example/ma-page');

        $this->assertSame('', $result);
    }

    public function test_rejects_scheme_mismatch(): void
    {
        $result = \my_articles_normalize_internal_url('https://example.com/ma-page', 'http://example.com');

        $this->assertSame('', $result);
    }
}
