<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use My_Articles_Response_Cache_Key;
use PHPUnit\Framework\TestCase;

final class ResponseCacheKeyTest extends TestCase
{
    public function test_distinct_fragments_produce_distinct_hashes(): void
    {
        $baseContext = array(
            'instance' => 101,
            'category' => 'actus',
            'paged'    => 1,
            'mode'     => 'grid',
        );

        $searchBuilder = new My_Articles_Response_Cache_Key('Tests', $baseContext);
        $searchBuilder->add_fragment('search', 'sort:date');

        $sortBuilder = new My_Articles_Response_Cache_Key('Tests', $baseContext);
        $sortBuilder->add_fragment('sort', 'date');

        $this->assertNotSame(
            $searchBuilder->to_string(),
            $sortBuilder->to_string(),
            'A search fragment should not collide with a sort fragment.'
        );
    }

    public function test_fragment_order_does_not_change_hash(): void
    {
        $baseContext = array(
            'instance' => 77,
            'category' => 'news',
            'paged'    => 2,
            'mode'     => 'list',
        );

        $builderA = new My_Articles_Response_Cache_Key('Tests', $baseContext);
        $builderA->add_fragment('search', 'keyword');
        $builderA->add_fragment('sort', 'date');

        $builderB = new My_Articles_Response_Cache_Key('Tests', $baseContext);
        $builderB->add_fragment('sort', 'date');
        $builderB->add_fragment('search', 'keyword');

        $this->assertSame(
            $builderA->to_string(),
            $builderB->to_string(),
            'Fragments should be sorted internally to keep cache keys stable.'
        );
    }

    public function test_array_fragments_are_normalized(): void
    {
        $baseContext = array(
            'instance' => 55,
            'category' => 'culture',
            'paged'    => 3,
            'mode'     => 'grid',
        );

        $builderA = new My_Articles_Response_Cache_Key('Tests', $baseContext);
        $builderA->add_fragment('filters', array('b', 'a'));

        $builderB = new My_Articles_Response_Cache_Key('Tests', $baseContext);
        $builderB->add_fragment('filters', array('b', '', 'a'));

        $this->assertSame(
            $builderA->to_string(),
            $builderB->to_string(),
            'Empty values should be removed from array fragments to keep hashes aligned.'
        );
    }

    public function test_associative_array_fragments_influence_hash(): void
    {
        $baseContext = array(
            'instance' => 12,
            'category' => 'eco',
            'paged'    => 4,
            'mode'     => 'list',
        );

        $builderType = new My_Articles_Response_Cache_Key('Tests', $baseContext);
        $builderType->add_fragment('filters', array('type' => 'video'));

        $builderCategory = new My_Articles_Response_Cache_Key('Tests', $baseContext);
        $builderCategory->add_fragment('filters', array('category' => 'video'));

        $this->assertNotSame(
            $builderType->to_string(),
            $builderCategory->to_string(),
            'Associative array keys should impact the cache hash for fragments provided by filters.'
        );

        $builderOrdered = new My_Articles_Response_Cache_Key('Tests', $baseContext);
        $builderOrdered->add_fragment('filters', array('type' => 'video', 'category' => 'news'));

        $builderShuffled = new My_Articles_Response_Cache_Key('Tests', $baseContext);
        $builderShuffled->add_fragment('filters', array('category' => 'news', 'type' => 'video'));

        $this->assertSame(
            $builderOrdered->to_string(),
            $builderShuffled->to_string(),
            'Associative fragments should be sorted internally to keep cache keys stable regardless of array order.'
        );
    }
}
