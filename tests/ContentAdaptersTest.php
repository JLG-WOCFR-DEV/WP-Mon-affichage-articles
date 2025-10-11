<?php

class ContentAdaptersTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
    }

    public function tearDown(): void {
        remove_all_filters( 'my_articles_content_adapters' );
        parent::tearDown();
    }

    public function test_sanitize_content_adapters_accepts_json_string() {
        add_filter(
            'my_articles_content_adapters',
            function () {
                return array(
                    'sample' => array(
                        'callback' => '__return_empty_array',
                        'label'    => 'Sample',
                    ),
                );
            }
        );

        $raw = wp_json_encode(
            array(
                array(
                    'id'     => 'sample',
                    'config' => array(
                        'foo'    => 'bar',
                        'nested' => array( 'value' => 10 ),
                    ),
                ),
            )
        );

        $sanitized = My_Articles_Shortcode::sanitize_content_adapters( $raw );

        $this->assertCount( 1, $sanitized );
        $this->assertSame( 'sample', $sanitized[0]['id'] );
        $this->assertSame( 'bar', $sanitized[0]['config']['foo'] );
        $this->assertSame( 10, $sanitized[0]['config']['nested']['value'] );
    }

    public function test_collect_content_adapter_items_filters_duplicates() {
        $post_id = self::factory()->post->create();

        add_filter(
            'my_articles_content_adapters',
            function () use ( $post_id ) {
                return array(
                    'custom' => array(
                        'label'    => 'Custom',
                        'callback' => function () use ( $post_id ) {
                            return array(
                                get_post( $post_id ),
                                get_post( $post_id ),
                                '<div class="external">External</div>',
                            );
                        },
                    ),
                );
            }
        );

        $options = array(
            'content_adapters'   => array(
                array(
                    'id'     => 'custom',
                    'config' => array(),
                ),
            ),
            'all_excluded_ids'   => array(),
            'exclude_post_ids'   => array(),
            'pinned_posts'       => array(),
            'primary_taxonomy_terms' => array(),
        );

        $items = My_Articles_Shortcode::collect_content_adapter_items( $options );

        $this->assertCount( 2, $items );
        $this->assertSame( 'post', $items[0]['type'] );
        $this->assertInstanceOf( WP_Post::class, $items[0]['post'] );
        $this->assertSame( $post_id, $items[0]['post']->ID );
        $this->assertSame( 'html', $items[1]['type'] );
        $this->assertStringContainsString( 'External', $items[1]['html'] );
    }
}
