<?php

class ContentAdaptersTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        my_articles_reset_content_adapter_registry();
        remove_all_filters( 'my_articles_content_adapters' );
    }

    public function tearDown(): void {
        my_articles_reset_content_adapter_registry();
        remove_all_filters( 'my_articles_content_adapters' );
        parent::tearDown();
    }

    public function test_sanitize_content_adapters_accepts_json_string() {
        my_articles_register_content_adapter(
            'sample',
            array(
                'label'    => 'Sample',
                'callback' => static function () {
                    return array();
                },
            )
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

        my_articles_register_content_adapter(
            'custom',
            array(
                'label'    => 'Custom',
                'callback' => function () use ( $post_id ) {
                    return array(
                        get_post( $post_id ),
                        get_post( $post_id ),
                        '<div class="external">External</div>',
                    );
                },
            )
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

    public function test_collect_content_adapter_items_supports_interface_implementations() {
        if ( ! interface_exists( 'My_Articles_Content_Adapter_Interface' ) ) {
            require_once dirname( __DIR__ ) . '/mon-affichage-article/includes/interface-my-articles-content-adapter.php';
        }

        my_articles_register_content_adapter(
            'interface_adapter',
            array(
                'label' => 'Interface based',
                'class' => Sample_Interface_Content_Adapter::class,
            )
        );

        $options = array(
            'content_adapters' => array(
                array(
                    'id'     => 'interface_adapter',
                    'config' => array( 'message' => 'Hello world' ),
                ),
            ),
        );

        $items = My_Articles_Shortcode::collect_content_adapter_items( $options );

        $this->assertCount( 1, $items );
        $this->assertSame( 'html', $items[0]['type'] );
        $this->assertStringContainsString( 'Hello world', $items[0]['html'] );
    }

    public function test_filter_based_registration_still_supported() {
        add_filter(
            'my_articles_content_adapters',
            static function () {
                return array(
                    'legacy' => array(
                        'label'    => 'Legacy',
                        'callback' => static function () {
                            return array(
                                array(
                                    'type' => 'html',
                                    'html' => '<div class="legacy">Legacy</div>',
                                ),
                            );
                        },
                    ),
                );
            }
        );

        $options = array(
            'content_adapters' => array(
                array(
                    'id'     => 'legacy',
                    'config' => array(),
                ),
            ),
        );

        $items = My_Articles_Shortcode::collect_content_adapter_items( $options );

        $this->assertCount( 1, $items );
        $this->assertSame( 'html', $items[0]['type'] );
        $this->assertStringContainsString( 'Legacy', $items[0]['html'] );
    }
}

class Sample_Interface_Content_Adapter implements My_Articles_Content_Adapter_Interface {
    public function get_items( array $options, array $config = array(), array $context = array() ) {
        $message = isset( $config['message'] ) ? (string) $config['message'] : 'Adapter';

        return array(
            array(
                'type' => 'html',
                'html' => '<div class="adapter">' . esc_html( $message ) . '</div>',
            ),
        );
    }
}
