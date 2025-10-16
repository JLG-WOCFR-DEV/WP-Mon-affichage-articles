<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use My_Articles_Shortcode;
use My_Articles_Settings_Sanitizer;
use My_Articles_Display_State_Builder;
use WP_Query;
use WP_UnitTestCase;
use ReflectionClass;

final class MetaQuerySupportTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetShortcodeNormalizationCache();
        My_Articles_Display_State_Builder::reset_runtime_cache();
    }

    protected function tearDown(): void
    {
        $this->resetShortcodeNormalizationCache();
        My_Articles_Display_State_Builder::reset_runtime_cache();
        parent::tearDown();
    }

    public function test_sanitize_meta_queries_accepts_json_payload(): void
    {
        $raw = '[{"key":"feature_flag","value":"yes","compare":"="}]';

        $sanitized = My_Articles_Settings_Sanitizer::sanitize_meta_queries( $raw );

        self::assertIsArray( $sanitized );
        self::assertSame( 'AND', $sanitized['relation'] ?? 'AND' );
        self::assertArrayHasKey( 0, $sanitized );
        self::assertSame( 'feature_flag', $sanitized[0]['key'] );
        self::assertSame( 'yes', $sanitized[0]['value'] );
        self::assertSame( '=', $sanitized[0]['compare'] );
    }

    public function test_build_display_state_applies_meta_query_filters(): void
    {
        $matching_id = self::factory()->post->create();
        add_post_meta( $matching_id, 'feature_flag', 'yes' );

        $excluded_id = self::factory()->post->create();
        add_post_meta( $excluded_id, 'feature_flag', 'no' );

        $options = My_Articles_Shortcode::normalize_instance_options(
            array(
                'post_type'           => 'post',
                'posts_per_page'      => 5,
                'meta_query'          => array(
                    array(
                        'key'     => 'feature_flag',
                        'value'   => 'yes',
                        'compare' => '=',
                    ),
                ),
                'meta_query_relation' => 'AND',
            ),
            array()
        );

        $shortcode = My_Articles_Shortcode::get_instance();
        $state     = $shortcode->build_display_state( $options );

        $this->assertArrayHasKey( 'regular_query', $state );
        $this->assertInstanceOf( WP_Query::class, $state['regular_query'] );

        $retrieved_ids = wp_list_pluck( $state['regular_query']->posts, 'ID' );

        self::assertContains( $matching_id, $retrieved_ids );
        self::assertNotContains( $excluded_id, $retrieved_ids );
    }

    public function test_build_display_state_honours_or_relation(): void
    {
        $first_match  = self::factory()->post->create();
        $second_match = self::factory()->post->create();
        $excluded     = self::factory()->post->create();

        add_post_meta( $first_match, 'score', '5' );
        add_post_meta( $second_match, 'score', '10' );
        add_post_meta( $excluded, 'score', '1' );

        $options = My_Articles_Shortcode::normalize_instance_options(
            array(
                'post_type'           => 'post',
                'posts_per_page'      => 5,
                'meta_query'          => array(
                    array(
                        'key'     => 'score',
                        'value'   => '5',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => 'score',
                        'value'   => '10',
                        'compare' => '=',
                    ),
                ),
                'meta_query_relation' => 'OR',
            ),
            array()
        );

        $shortcode = My_Articles_Shortcode::get_instance();
        $state     = $shortcode->build_display_state( $options );

        $this->assertArrayHasKey( 'regular_query', $state );
        $this->assertInstanceOf( WP_Query::class, $state['regular_query'] );
        $this->assertSame( 'OR', $state['meta_query_relation'] );

        $retrieved_ids = wp_list_pluck( $state['regular_query']->posts, 'ID' );

        self::assertContains( $first_match, $retrieved_ids );
        self::assertContains( $second_match, $retrieved_ids );
        self::assertNotContains( $excluded, $retrieved_ids );

        $query_meta = $state['regular_query']->get( 'meta_query' );
        self::assertIsArray( $query_meta );
        self::assertSame( 'OR', $query_meta['relation'] ?? '' );
    }

    public function test_build_display_state_preserves_meta_query_types(): void
    {
        $low_score_post  = self::factory()->post->create();
        $high_score_post = self::factory()->post->create();

        add_post_meta( $low_score_post, 'score', '2' );
        add_post_meta( $high_score_post, 'score', '9' );

        $options = My_Articles_Shortcode::normalize_instance_options(
            array(
                'post_type'           => 'post',
                'posts_per_page'      => 5,
                'meta_query'          => array(
                    array(
                        'key'     => 'score',
                        'value'   => 5,
                        'compare' => '>=',
                        'type'    => 'NUMERIC',
                    ),
                ),
                'meta_query_relation' => 'AND',
            ),
            array()
        );

        $shortcode = My_Articles_Shortcode::get_instance();
        $state     = $shortcode->build_display_state( $options );

        $this->assertArrayHasKey( 'regular_query', $state );
        $this->assertInstanceOf( WP_Query::class, $state['regular_query'] );

        $retrieved_ids = wp_list_pluck( $state['regular_query']->posts, 'ID' );

        self::assertContains( $high_score_post, $retrieved_ids );
        self::assertNotContains( $low_score_post, $retrieved_ids );

        $meta_query = $state['meta_query'];
        self::assertIsArray( $meta_query );
        self::assertArrayHasKey( 0, $meta_query );
        self::assertSame( 'NUMERIC', $meta_query[0]['type'] ?? '' );

        $query_meta = $state['regular_query']->get( 'meta_query' );
        self::assertIsArray( $query_meta );
        self::assertSame( 'NUMERIC', $query_meta[0]['type'] ?? '' );
    }

    private function resetShortcodeNormalizationCache(): void
    {
        $reflection = new ReflectionClass( My_Articles_Shortcode::class );
        $property   = $reflection->getProperty( 'normalized_options_cache' );
        $property->setAccessible( true );
        $property->setValue( null, array() );
    }
}
