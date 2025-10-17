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
                        'search_query' => 'hello world',
                        'sort'        => 'comment_count',
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
        $request->set_param('search', ' hello   world ');
        $request->set_param('sort', 'comment_count');
        $request->set_param('filters', '[{"taxonomy":"category","slug":"news"}]');

        $response = $controller->filter_articles($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(
            array(
                'html'        => '<div>Rendered</div>',
                'total_pages' => 3,
                'next_page'   => 2,
                'pinned_ids'  => '1,2',
                'search_query' => 'hello world',
                'sort'        => 'comment_count',
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
                'search'       => ' hello   world ',
                'sort'         => 'comment_count',
                'filters'      => array(
                    array('taxonomy' => 'category', 'slug' => 'news'),
                ),
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
                        'search_query' => 'top stories',
                        'sort'        => 'title',
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
        $request->set_param('search', 'top stories');
        $request->set_param('sort', 'title');
        $request->set_param('filters', array(
            array('taxonomy' => 'post_tag', 'slug' => 'highlights'),
        ));

        $response = $controller->load_more_articles($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(
            array(
                'html'        => '<article>More</article>',
                'pinned_ids'  => '1,2,3,4',
                'total_pages' => 5,
                'next_page'   => 4,
                'search_query' => 'top stories',
                'sort'        => 'title',
            ),
            $response->get_data()
        );
        $this->assertSame(
            array(
                'instance_id' => 7,
                'paged'       => 3,
                'pinned_ids'  => '1,2,3',
                'category'    => 'featured',
                'search'      => 'top stories',
                'sort'        => 'title',
                'filters'     => array(
                    array('taxonomy' => 'post_tag', 'slug' => 'highlights'),
                ),
            ),
            $capturedArgs
        );
    }

    public function test_load_more_route_sorts_articles_by_requested_sort(): void
    {
        global $mon_articles_test_post_type_map, $mon_articles_test_post_status_map, $mon_articles_test_post_meta_map;
        global $mon_articles_test_wp_query_factory, $mon_articles_test_wp_cache, $mon_articles_test_transients, $mon_articles_test_transients_store;

        $instanceId = 105;

        $previousPostTypeMap      = $mon_articles_test_post_type_map ?? null;
        $previousPostStatusMap    = $mon_articles_test_post_status_map ?? null;
        $previousPostMetaMap      = $mon_articles_test_post_meta_map ?? null;
        $previousFactory          = $mon_articles_test_wp_query_factory ?? null;
        $previousCache            = $mon_articles_test_wp_cache ?? null;
        $previousTransients       = $mon_articles_test_transients ?? null;
        $previousTransientsStore  = $mon_articles_test_transients_store ?? null;

        $shortcodeReflection = new \ReflectionClass(\My_Articles_Shortcode::class);
        $normalizedProperty  = $shortcodeReflection->getProperty('normalized_options_cache');
        $normalizedProperty->setAccessible(true);
        $previousNormalizedCache = $normalizedProperty->getValue();
        $normalizedProperty->setValue(null, array());

        $matchingProperty = $shortcodeReflection->getProperty('matching_pinned_ids_cache');
        $matchingProperty->setAccessible(true);
        $previousMatchingCache = $matchingProperty->getValue();
        $matchingProperty->setValue(null, array());

        $mon_articles_test_post_type_map = array($instanceId => 'mon_affichage');
        $mon_articles_test_post_status_map = array($instanceId => 'publish');
        $mon_articles_test_post_meta_map = array(
            $instanceId => array(
                '_my_articles_settings' => array(
                    'post_type'            => 'post',
                    'display_mode'         => 'grid',
                    'pagination_mode'      => 'load_more',
                    'posts_per_page'       => 2,
                    'orderby'              => 'date',
                    'order'                => 'DESC',
                    'show_category'        => 0,
                    'show_author'          => 0,
                    'show_date'            => 0,
                    'show_excerpt'         => 0,
                    'enable_keyword_search'=> 0,
                ),
            ),
        );

        $mon_articles_test_wp_cache = array();
        $mon_articles_test_transients = array();
        $mon_articles_test_transients_store = array();

        $fixtures = array(
            array('ID' => 201, 'post_title' => 'Alpha',   'post_date' => '2024-01-01 00:00:00'),
            array('ID' => 202, 'post_title' => 'Bravo',   'post_date' => '2024-01-03 00:00:00'),
            array('ID' => 203, 'post_title' => 'Charlie', 'post_date' => '2024-01-02 00:00:00'),
        );

        $recordedIds      = array();
        $capturedOrderby  = null;

        $mon_articles_test_wp_query_factory = function (array $query_args) use ($fixtures, &$recordedIds, &$capturedOrderby) {
            if (isset($query_args['orderby']) && 'post__in' === $query_args['orderby']) {
                $posts = array();

                if (!empty($query_args['post__in']) && is_array($query_args['post__in'])) {
                    foreach ($query_args['post__in'] as $requestedId) {
                        foreach ($fixtures as $fixture) {
                            if ($fixture['ID'] === $requestedId) {
                                $posts[] = $fixture;
                                break;
                            }
                        }
                    }
                }

                return array(
                    'posts'       => $posts,
                    'found_posts' => count($posts),
                );
            }

            $posts   = $fixtures;
            $orderby = $query_args['orderby'] ?? 'date';
            $order   = strtoupper($query_args['order'] ?? 'DESC');

            if ('title' === $orderby) {
                usort($posts, static function (array $a, array $b) use ($order) {
                    $comparison = strcasecmp($a['post_title'], $b['post_title']);

                    if (0 === $comparison) {
                        return 0;
                    }

                    return 'ASC' === $order ? $comparison : -$comparison;
                });
            } elseif ('date' === $orderby) {
                usort($posts, static function (array $a, array $b) use ($order) {
                    $comparison = strcmp($a['post_date'], $b['post_date']);

                    return 'ASC' === $order ? $comparison : -$comparison;
                });
            }

            $limit = isset($query_args['posts_per_page']) ? (int) $query_args['posts_per_page'] : count($posts);
            if ($limit >= 0) {
                $limited_posts = array_slice($posts, 0, $limit);
            } else {
                $limited_posts = $posts;
            }

            $capturedOrderby = $orderby;
            $recordedIds = array_map(static function (array $post): int {
                return $post['ID'];
            }, $limited_posts);

            return array(
                'posts'       => $limited_posts,
                'found_posts' => count($posts),
            );
        };

        $plugin     = \Mon_Affichage_Articles::get_instance();
        $controller = new My_Articles_Controller($plugin);

        $request = new WP_REST_Request('POST', '/my-articles/v1/load-more');
        $request->set_header('X-WP-Nonce', 'valid-rest-nonce');
        $request->set_param('instance_id', $instanceId);
        $request->set_param('paged', 1);
        $request->set_param('sort', ' TITLE ');

        try {
            $response = $controller->load_more_articles($request);

            $this->assertInstanceOf(WP_REST_Response::class, $response);
            $this->assertSame(200, $response->get_status());

            $payload = $response->get_data();
            $this->assertIsArray($payload);
            $this->assertArrayHasKey('sort', $payload);
            $this->assertSame('title', $payload['sort']);
            $this->assertSame(array(203, 202), $recordedIds);
            $this->assertSame('title', $capturedOrderby);
        } finally {
            $mon_articles_test_post_type_map     = $previousPostTypeMap;
            $mon_articles_test_post_status_map   = $previousPostStatusMap;
            $mon_articles_test_post_meta_map     = $previousPostMetaMap;
            $mon_articles_test_wp_query_factory  = $previousFactory;
            $mon_articles_test_wp_cache          = $previousCache;
            $mon_articles_test_transients        = $previousTransients;
            $mon_articles_test_transients_store  = $previousTransientsStore;
            $normalizedProperty->setValue(null, $previousNormalizedCache);
            $matchingProperty->setValue(null, $previousMatchingCache);
        }
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
        $request->set_header('X-WP-Nonce', 'valid-rest-nonce');

        $response = $controller->get_rest_nonce($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);

        $payload = $response->get_data();
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('success', $payload);
        $this->assertTrue($payload['success']);
        $this->assertArrayHasKey('data', $payload);
        $this->assertSame('nonce-wp_rest', $payload['data']['nonce']);
    }

    public function test_nonce_route_accepts_internal_referer_when_origin_missing(): void
    {
        $controller = $this->createControllerWithHandlers(array());

        $request = new WP_REST_Request('GET', '/my-articles/v1/nonce');
        $request->set_header('referer', '/subdir/page?foo=bar');
        $request->set_header('X-WP-Nonce', 'valid-rest-nonce');

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

    public function test_nonce_route_rejects_missing_nonce_header(): void
    {
        $controller = $this->createControllerWithHandlers(array());

        $request = new WP_REST_Request('GET', '/my-articles/v1/nonce');
        $request->set_header('origin', 'http://example.com/page');

        $response = $controller->get_rest_nonce($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame('my_articles_invalid_nonce', $response->get_error_code());
    }

    public function test_nonce_route_rejects_invalid_nonce_header(): void
    {
        $controller = $this->createControllerWithHandlers(array());

        $request = new WP_REST_Request('GET', '/my-articles/v1/nonce');
        $request->set_header('origin', 'http://example.com/page');
        $request->set_header('X-WP-Nonce', 'nope');

        $response = $controller->get_rest_nonce($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame('my_articles_invalid_nonce', $response->get_error_code());
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
