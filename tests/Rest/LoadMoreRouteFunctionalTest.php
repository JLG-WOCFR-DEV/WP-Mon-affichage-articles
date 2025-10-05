<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests\Rest;

use Mon_Affichage_Articles;
use My_Articles_Controller;
use My_Articles_Shortcode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_REST_Request;
use WP_REST_Response;

final class LoadMoreRouteFunctionalTest extends TestCase
{
    public function test_load_more_endpoint_orders_articles_with_requested_sort(): void
    {
        global $mon_articles_test_post_type_map,
            $mon_articles_test_post_status_map,
            $mon_articles_test_post_meta_map,
            $mon_articles_test_valid_nonces,
            $mon_articles_test_wp_query_factory,
            $mon_articles_test_options,
            $mon_articles_test_options_store;

        $postTypeBackup      = $mon_articles_test_post_type_map ?? null;
        $postStatusBackup    = $mon_articles_test_post_status_map ?? null;
        $postMetaBackup      = $mon_articles_test_post_meta_map ?? null;
        $nonceBackup         = $mon_articles_test_valid_nonces ?? null;
        $factoryBackup       = $mon_articles_test_wp_query_factory ?? null;
        $optionsBackup       = $mon_articles_test_options ?? null;
        $optionsStoreBackup  = $mon_articles_test_options_store ?? null;

        $shortcodeReflection = new ReflectionClass(My_Articles_Shortcode::class);
        $instanceProperty    = $shortcodeReflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $normalizedCacheProp = $shortcodeReflection->getProperty('normalized_options_cache');
        $normalizedCacheProp->setAccessible(true);
        $matchingCacheProp   = $shortcodeReflection->getProperty('matching_pinned_ids_cache');
        $matchingCacheProp->setAccessible(true);

        $previousInstance        = $instanceProperty->getValue();
        $previousNormalizedCache = $normalizedCacheProp->getValue();
        $previousMatchingCache   = $matchingCacheProp->getValue();

        $loggingShortcode = new class extends My_Articles_Shortcode {
            /** @var array<int> */
            public $renderedIds = array();

            public function __construct()
            {
                // Skip parent constructor to avoid registering shortcodes during tests.
            }

            public function render_article_item($options, $is_pinned = false)
            {
                $this->renderedIds[] = get_the_ID();
                echo '<article data-id="' . esc_attr((string) get_the_ID()) . '"></article>';
            }

            public function get_skeleton_placeholder_markup($container_class, $options, $render_limit)
            {
                return '';
            }

            public function get_empty_state_html()
            {
                return '';
            }

            public function get_empty_state_slide_html()
            {
                return '';
            }
        };

        try {
            $instanceProperty->setValue(null, $loggingShortcode);
            $normalizedCacheProp->setValue(null, array());
            $matchingCacheProp->setValue(null, array());

            $mon_articles_test_valid_nonces = array(
                'valid-rest-nonce' => array('wp_rest'),
            );

            $mon_articles_test_post_type_map = array(55 => 'mon_affichage');
            $mon_articles_test_post_status_map = array(55 => 'publish');
            $mon_articles_test_post_meta_map = array(
                55 => array(
                    '_my_articles_settings' => array(
                        'post_type'            => 'post',
                        'pagination_mode'      => 'load_more',
                        'display_mode'         => 'list',
                        'posts_per_page'       => 3,
                        'order'                => 'ASC',
                        'sort'                 => 'date',
                        'orderby'              => 'date',
                        'enable_keyword_search'=> 0,
                        'pinned_posts'         => array(),
                        'ignore_native_sticky' => 1,
                        'show_category'        => 0,
                        'show_author'          => 0,
                        'show_date'            => 0,
                    ),
                ),
            );

            $mon_articles_test_options = array('my_articles_cache_namespace' => 'tests');
            $mon_articles_test_options_store = $mon_articles_test_options;

            $allPosts = array(
                array('ID' => 302, 'post_title' => 'Gamma'),
                array('ID' => 104, 'post_title' => 'Alpha'),
                array('ID' => 215, 'post_title' => 'Bravo'),
            );

            $mon_articles_test_wp_query_factory = static function (array $args) use ($allPosts): array {
                $posts = $allPosts;

                $orderby = $args['orderby'] ?? 'date';
                $order   = strtoupper((string) ($args['order'] ?? 'DESC'));

                if ('title' === $orderby) {
                    usort($posts, static function ($left, $right) use ($order) {
                        $comparison = strnatcasecmp((string) ($left['post_title'] ?? ''), (string) ($right['post_title'] ?? ''));

                        return ('DESC' === $order) ? -$comparison : $comparison;
                    });
                }

                $total  = count($posts);
                $offset = isset($args['offset']) ? max(0, (int) $args['offset']) : 0;
                if ($offset > 0) {
                    $posts = array_slice($posts, $offset);
                }

                if (isset($args['posts_per_page'])) {
                    $limit = (int) $args['posts_per_page'];
                    if ($limit >= 0) {
                        $posts = array_slice($posts, 0, $limit);
                    }
                }

                return array(
                    'posts'       => $posts,
                    'found_posts' => $total,
                );
            };

            $plugin     = new Mon_Affichage_Articles();
            $controller = new My_Articles_Controller($plugin);

            $request = new WP_REST_Request('POST', '/my-articles/v1/load-more');
            $request->set_header('X-WP-Nonce', 'valid-rest-nonce');
            $request->set_param('instance_id', 55);
            $request->set_param('paged', 1);
            $request->set_param('sort', array(' title '));

            $response = $controller->load_more_articles($request);

            $this->assertInstanceOf(WP_REST_Response::class, $response);

            $data = $response->get_data();

            $this->assertSame('title', $data['sort']);
            $this->assertSame(1, $data['total_pages']);

            preg_match_all('/data-id="(\d+)"/', $data['html'], $matches);
            $this->assertSame(array('104', '215', '302'), $matches[1]);
            $this->assertSame(array(104, 215, 302), $loggingShortcode->renderedIds);
        } finally {
            $instanceProperty->setValue(null, $previousInstance);
            $normalizedCacheProp->setValue(null, $previousNormalizedCache);
            $matchingCacheProp->setValue(null, $previousMatchingCache);

            $mon_articles_test_post_type_map   = $postTypeBackup;
            $mon_articles_test_post_status_map = $postStatusBackup;
            $mon_articles_test_post_meta_map   = $postMetaBackup;
            $mon_articles_test_valid_nonces    = $nonceBackup;
            $mon_articles_test_wp_query_factory = $factoryBackup;
            $mon_articles_test_options         = $optionsBackup;
            $mon_articles_test_options_store   = $optionsStoreBackup;
        }
    }
}
