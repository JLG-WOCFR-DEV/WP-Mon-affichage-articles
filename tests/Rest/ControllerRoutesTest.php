<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests\Rest;

use My_Articles_Controller;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * @covers My_Articles_Controller
 */
final class ControllerRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $mon_articles_test_valid_nonces;
        $mon_articles_test_valid_nonces = array(
            'valid-rest-nonce' => array('wp_rest'),
        );
    }

    protected function tearDown(): void
    {
        global $mon_articles_test_valid_nonces;
        $mon_articles_test_valid_nonces = null;

        parent::tearDown();
    }

    public function test_filter_route_returns_rest_response(): void
    {
        $capturedArgs = null;
        $controller = $this->createControllerWithHandlers(
            array(
                'prepare_filter_articles_response' => function ($args) use (&$capturedArgs) {
                    $capturedArgs = $args;

                    return array(
                        'html'        => '<div>Rendered</div>',
                        'total_pages' => 3,
                        'next_page'   => 2,
                        'pinned_ids'  => '1,2',
                    );
                },
            )
        );

        $request = new WP_REST_Request('POST', '/my-articles/v1/filter');
        $request->set_header('X-WP-Nonce', 'valid-rest-nonce');
        $request->set_header('referer', 'http://example.com/ref');
        $request->set_param('instance_id', 42);
        $request->set_param('category', 'news');
        $request->set_param('current_url', 'http://example.com/page');

        $response = $controller->filter_articles($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(
            array(
                'html'        => '<div>Rendered</div>',
                'total_pages' => 3,
                'next_page'   => 2,
                'pinned_ids'  => '1,2',
            ),
            $response->get_data()
        );
        $this->assertSame(200, $response->get_status());
        $this->assertSame(
            array(
                'instance_id'  => 42,
                'category'     => 'news',
                'current_url'  => 'http://example.com/page',
                'http_referer' => 'http://example.com/ref',
            ),
            $capturedArgs
        );
    }

    public function test_filter_route_rejects_invalid_nonce(): void
    {
        $controller = $this->createControllerWithHandlers(array());

        $request = new WP_REST_Request('POST', '/my-articles/v1/filter');
        $request->set_header('X-WP-Nonce', 'invalid');
        $request->set_param('instance_id', 99);

        $response = $controller->filter_articles($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame('my_articles_invalid_nonce', $response->get_error_code());
        $this->assertSame(
            array('status' => 403),
            $response->get_error_data()
        );
    }

    public function test_filter_route_propagates_wp_error_from_plugin(): void
    {
        $controller = $this->createControllerWithHandlers(
            array(
                'prepare_filter_articles_response' => function () {
                    return new WP_Error(
                        'my_articles_missing_instance_id',
                        'ID d\'instance manquant.',
                        array('status' => 400)
                    );
                },
            )
        );

        $request = new WP_REST_Request('POST', '/my-articles/v1/filter');
        $request->set_header('X-WP-Nonce', 'valid-rest-nonce');
        $request->set_param('instance_id', 0);

        $response = $controller->filter_articles($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame('my_articles_missing_instance_id', $response->get_error_code());
        $this->assertSame(400, $response->get_error_data()['status']);
    }

    public function test_load_more_route_returns_pagination_payload(): void
    {
        $capturedArgs = null;
        $controller = $this->createControllerWithHandlers(
            array(
                'prepare_load_more_articles_response' => function ($args) use (&$capturedArgs) {
                    $capturedArgs = $args;

                    return array(
                        'html'        => '<article>More</article>',
                        'pinned_ids'  => '1,2,3,4',
                        'total_pages' => 5,
                        'next_page'   => 4,
                    );
                },
            )
        );

        $request = new WP_REST_Request('POST', '/my-articles/v1/load-more');
        $request->set_header('X-WP-Nonce', 'valid-rest-nonce');
        $request->set_param('instance_id', 7);
        $request->set_param('paged', 3);
        $request->set_param('pinned_ids', '1,2,3');
        $request->set_param('category', 'featured');

        $response = $controller->load_more_articles($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(
            array(
                'html'        => '<article>More</article>',
                'pinned_ids'  => '1,2,3,4',
                'total_pages' => 5,
                'next_page'   => 4,
            ),
            $response->get_data()
        );
        $this->assertSame(
            array(
                'instance_id' => 7,
                'paged'       => 3,
                'pinned_ids'  => '1,2,3',
                'category'    => 'featured',
            ),
            $capturedArgs
        );
    }

    public function test_load_more_route_returns_error_from_plugin(): void
    {
        $controller = $this->createControllerWithHandlers(
            array(
                'prepare_load_more_articles_response' => function () {
                    return new WP_Error('my_articles_load_more_disabled', 'Disabled', array('status' => 400));
                },
            )
        );

        $request = new WP_REST_Request('POST', '/my-articles/v1/load-more');
        $request->set_header('X-WP-Nonce', 'valid-rest-nonce');
        $request->set_param('instance_id', 9);

        $response = $controller->load_more_articles($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame('my_articles_load_more_disabled', $response->get_error_code());
        $this->assertSame(400, $response->get_error_data()['status']);
    }

    public function test_search_route_returns_results_payload(): void
    {
        $capturedArgs = null;
        $controller = $this->createControllerWithHandlers(
            array(
                'prepare_search_posts_response' => function ($args) use (&$capturedArgs) {
                    $capturedArgs = $args;

                    return array(
                        'results' => array(
                            array('id' => 12, 'text' => 'Newsletter #1'),
                            array('id' => 15, 'text' => 'Newsletter #2'),
                        ),
                    );
                },
            )
        );

        $request = new WP_REST_Request('GET', '/my-articles/v1/search');
        $request->set_header('X-WP-Nonce', 'valid-rest-nonce');
        $request->set_param('search', 'newsletter');
        $request->set_param('post_type', 'post');

        $response = $controller->search_posts($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(
            array(
                'results' => array(
                    array('id' => 12, 'text' => 'Newsletter #1'),
                    array('id' => 15, 'text' => 'Newsletter #2'),
                ),
            ),
            $response->get_data()
        );
        $this->assertSame(
            array(
                'search'    => 'newsletter',
                'post_type' => 'post',
            ),
            $capturedArgs
        );
    }

    public function test_search_route_rejects_invalid_nonce(): void
    {
        $controller = $this->createControllerWithHandlers(array());

        $request = new WP_REST_Request('GET', '/my-articles/v1/search');
        $request->set_header('X-WP-Nonce', 'nope');
        $request->set_param('post_type', 'post');

        $response = $controller->search_posts($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame('my_articles_invalid_nonce', $response->get_error_code());
    }

    public function test_search_route_propagates_plugin_error(): void
    {
        $controller = $this->createControllerWithHandlers(
            array(
                'prepare_search_posts_response' => function () {
                    return new WP_Error('my_articles_invalid_post_type', 'Invalid', array('status' => 400));
                },
            )
        );

        $request = new WP_REST_Request('GET', '/my-articles/v1/search');
        $request->set_header('X-WP-Nonce', 'valid-rest-nonce');
        $request->set_param('post_type', 'invalid');

        $response = $controller->search_posts($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame('my_articles_invalid_post_type', $response->get_error_code());
        $this->assertSame(400, $response->get_error_data()['status']);
    }

    public function test_nonce_route_returns_refreshed_nonce(): void
    {
        $controller = $this->createControllerWithHandlers(array());

        $request = new WP_REST_Request('GET', '/my-articles/v1/nonce');
        $request->set_header('origin', 'http://example.com/page');

        $response = $controller->get_rest_nonce($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);

        $payload = $response->get_data();
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('success', $payload);
        $this->assertTrue($payload['success']);
        $this->assertArrayHasKey('data', $payload);
        $this->assertSame('nonce-wp_rest', $payload['data']['nonce']);
    }

    public function test_nonce_route_rejects_external_origin(): void
    {
        $controller = $this->createControllerWithHandlers(array());

        $request = new WP_REST_Request('GET', '/my-articles/v1/nonce');
        $request->set_header('origin', 'https://malicious.example');
        $request->set_header('referer', 'https://malicious.example/page');

        $response = $controller->get_rest_nonce($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame('my_articles_invalid_request_origin', $response->get_error_code());
        $this->assertSame(403, $response->get_error_data()['status']);
    }

    /**
     * @param array<string, callable> $handlers
     */
    private function createControllerWithHandlers(array $handlers): My_Articles_Controller
    {
        $controller = new My_Articles_Controller(new \Mon_Affichage_Articles());

        $stub = new class($handlers) {
            /** @var array<string, callable> */
            private $handlers;

            /**
             * @param array<string, callable> $handlers
             */
            public function __construct(array $handlers)
            {
                $this->handlers = $handlers;
            }

            public function __call(string $name, array $arguments)
            {
                if (!isset($this->handlers[$name])) {
                    throw new \BadMethodCallException(sprintf('Handler not defined for method %s', $name));
                }

                $handler = $this->handlers[$name];

                return $handler(...$arguments);
            }
        };

        $property = new \ReflectionProperty(My_Articles_Controller::class, 'plugin');
        $property->setAccessible(true);
        $property->setValue($controller, $stub);

        return $controller;
    }
}
