<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests\Rest;

use Mon_Affichage_Articles;
use My_Articles_Controller;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use WP_Error;
use WP_REST_Request;

final class MyArticlesControllerTest extends TestCase
{
    /** @var My_Articles_Controller */
    private $controller;

    /** @var object */
    private $pluginStub;

    protected function setUp(): void
    {
        parent::setUp();

        global $mon_articles_test_nonces;
        $mon_articles_test_nonces = array();

        $realPlugin = new Mon_Affichage_Articles();
        $this->controller = new My_Articles_Controller($realPlugin);

        $this->pluginStub = new class {
            /** @var array<string, mixed>|null */
            public $filterArgs = null;

            /** @var array<string, mixed>|WP_Error|null */
            public $filterResponse = null;

            /** @var array<string, mixed>|null */
            public $loadMoreArgs = null;

            /** @var array<string, mixed>|WP_Error|null */
            public $loadMoreResponse = null;

            /**
             * @param array<string, mixed> $args
             * @return array<string, mixed>|WP_Error|null
             */
            public function prepare_filter_articles_response(array $args)
            {
                $this->filterArgs = $args;

                if (is_callable($this->filterResponse)) {
                    return ($this->filterResponse)($args);
                }

                return $this->filterResponse;
            }

            /**
             * @param array<string, mixed> $args
             * @return array<string, mixed>|WP_Error|null
             */
            public function prepare_load_more_articles_response(array $args)
            {
                $this->loadMoreArgs = $args;

                if (is_callable($this->loadMoreResponse)) {
                    return ($this->loadMoreResponse)($args);
                }

                return $this->loadMoreResponse;
            }
        };

        $reflection = new ReflectionProperty(My_Articles_Controller::class, 'plugin');
        $reflection->setAccessible(true);
        $reflection->setValue($this->controller, $this->pluginStub);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->controller = null;
        $this->pluginStub = null;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, string> $headers
     */
    private function createRequest(array $params, array $headers = array()): WP_REST_Request
    {
        $request = new WP_REST_Request('POST', '/my-articles/v1/filter');
        $request->set_body_params($params);
        $request->set_headers($headers);

        return $request;
    }

    public function test_filter_route_returns_successful_payload(): void
    {
        global $mon_articles_test_nonces;
        $mon_articles_test_nonces = array('my_articles_filter_nonce' => 'valid');

        $expected = array(
            'html'         => '<div>Payload</div>',
            'total_pages'  => 2,
            'next_page'    => 2,
            'pinned_ids'   => '3,5',
            'pagination_html' => '<nav>Pagination</nav>',
        );

        $this->pluginStub->filterResponse = $expected;

        $request = $this->createRequest(
            array(
                'instance_id' => 42,
                'category'    => 'press',
                'current_url' => 'https://example.com/articles',
                'security'    => 'valid',
            ),
            array('Referer' => 'https://example.com/from')
        );

        $response = $this->controller->filter_articles($request);

        $this->assertSame($expected, $response);
        $this->assertSame(
            array(
                'instance_id' => 42,
                'category'    => 'press',
                'current_url' => 'https://example.com/articles',
                'http_referer' => 'https://example.com/from',
            ),
            $this->pluginStub->filterArgs
        );
    }

    public function test_filter_route_rejects_invalid_nonce(): void
    {
        $this->pluginStub->filterResponse = array('html' => 'should not be returned');

        $request = $this->createRequest(
            array(
                'instance_id' => 99,
                'category'    => 'updates',
                'security'    => 'invalid',
            )
        );

        $response = $this->controller->filter_articles($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame('my_articles_invalid_nonce', $response->get_error_code());
        $this->assertSame('Nonce invalide.', $response->get_error_message());
        $this->assertNull($this->pluginStub->filterArgs);
    }

    public function test_filter_route_propagates_errors(): void
    {
        global $mon_articles_test_nonces;
        $mon_articles_test_nonces = array('my_articles_filter_nonce' => 'valid');

        $error = new WP_Error(
            'my_articles_category_not_allowed',
            'Catégorie non autorisée.',
            array('status' => 403)
        );

        $this->pluginStub->filterResponse = $error;

        $request = $this->createRequest(
            array(
                'instance_id' => 55,
                'category'    => 'restricted',
                'security'    => 'valid',
            )
        );

        $response = $this->controller->filter_articles($request);

        $this->assertSame($error, $response);
        $this->assertSame(55, $this->pluginStub->filterArgs['instance_id']);
        $this->assertSame('restricted', $this->pluginStub->filterArgs['category']);
    }

    public function test_load_more_route_returns_pagination_payload(): void
    {
        global $mon_articles_test_nonces;
        $mon_articles_test_nonces = array('my_articles_load_more_nonce' => 'load-more');

        $expected = array(
            'html'        => '<article>More</article>',
            'pinned_ids'  => '2,8',
            'total_pages' => 4,
            'next_page'   => 3,
        );

        $this->pluginStub->loadMoreResponse = $expected;

        $request = $this->createRequest(
            array(
                'instance_id' => 77,
                'paged'       => 2,
                'pinned_ids'  => '2,8',
                'category'    => 'press',
                'security'    => 'load-more',
            )
        );

        $response = $this->controller->load_more_articles($request);

        $this->assertSame($expected, $response);
        $this->assertSame(77, $this->pluginStub->loadMoreArgs['instance_id']);
        $this->assertSame(2, $this->pluginStub->loadMoreArgs['paged']);
        $this->assertSame('press', $this->pluginStub->loadMoreArgs['category']);
        $this->assertSame('2,8', $this->pluginStub->loadMoreArgs['pinned_ids']);
    }

    public function test_load_more_route_rejects_invalid_nonce(): void
    {
        $this->pluginStub->loadMoreResponse = array('html' => 'ignored');

        $request = $this->createRequest(
            array(
                'instance_id' => 11,
                'paged'       => 3,
                'security'    => 'nope',
            )
        );

        $response = $this->controller->load_more_articles($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame('my_articles_invalid_nonce', $response->get_error_code());
        $this->assertSame('Nonce invalide.', $response->get_error_message());
        $this->assertNull($this->pluginStub->loadMoreArgs);
    }

    public function test_load_more_route_propagates_plugin_errors(): void
    {
        global $mon_articles_test_nonces;
        $mon_articles_test_nonces = array('my_articles_load_more_nonce' => 'load');

        $error = new WP_Error(
            'my_articles_load_more_disabled',
            'Le chargement progressif est désactivé pour cette instance.',
            array('status' => 400)
        );

        $this->pluginStub->loadMoreResponse = $error;

        $request = $this->createRequest(
            array(
                'instance_id' => 21,
                'paged'       => 4,
                'security'    => 'load',
            )
        );

        $response = $this->controller->load_more_articles($request);

        $this->assertSame($error, $response);
        $this->assertSame(21, $this->pluginStub->loadMoreArgs['instance_id']);
        $this->assertSame(4, $this->pluginStub->loadMoreArgs['paged']);
    }
}
