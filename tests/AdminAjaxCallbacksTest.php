<?php

declare(strict_types=1);

namespace {

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $die = true): void
    {
        // No-op for tests.
    }
}

if (!class_exists('MyArticlesJsonResponse')) {
    class MyArticlesJsonResponse extends \RuntimeException
    {
        /** @var bool */
        public $success;

        /** @var array<string, mixed> */
        public array $data;

        /** @var int|null */
        public $status_code;

        /**
         * @param array<string, mixed> $data
         */
        public function __construct(bool $success, array $data, ?int $status_code)
        {
            parent::__construct('JSON response emitted.');

            $this->success = $success;
            $this->data = $data;
            $this->status_code = $status_code;
        }
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null): void
    {
        $payload = is_array($data) ? $data : array();
        throw new MyArticlesJsonResponse(true, $payload, is_int($status_code) ? $status_code : 200);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null): void
    {
        $payload = is_array($data) ? $data : array();
        throw new MyArticlesJsonResponse(false, $payload, is_int($status_code) ? $status_code : 400);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }

        $filtered = strip_tags((string) $value);

        return preg_replace('/[\r\n\t\0\x0B]+/', '', $filtered);
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability): bool
    {
        global $mon_articles_test_current_user_caps;

        if (!is_array($mon_articles_test_current_user_caps)) {
            return false;
        }

        return in_array((string) $capability, $mon_articles_test_current_user_caps, true);
    }
}

if (!function_exists('get_post_type_object')) {
    function get_post_type_object($post_type)
    {
        global $mon_articles_test_post_type_objects;

        if (!is_array($mon_articles_test_post_type_objects)) {
            return null;
        }

        $post_type = (string) $post_type;

        return $mon_articles_test_post_type_objects[$post_type] ?? null;
    }
}

if (!function_exists('get_object_taxonomies')) {
    function get_object_taxonomies($post_type, $output = 'names')
    {
        global $mon_articles_test_object_taxonomies;

        if (!is_array($mon_articles_test_object_taxonomies)) {
            return array();
        }

        $post_type = (string) $post_type;
        $taxonomies = $mon_articles_test_object_taxonomies[$post_type] ?? array();

        if ('objects' !== $output) {
            return array_map(
                static function ($taxonomy) {
                    return isset($taxonomy->name) ? $taxonomy->name : '';
                },
                $taxonomies
            );
        }

        return $taxonomies;
    }
}

if (!function_exists('taxonomy_exists')) {
    function taxonomy_exists($taxonomy)
    {
        global $mon_articles_test_taxonomies;

        if (!is_array($mon_articles_test_taxonomies)) {
            return false;
        }

        return in_array((string) $taxonomy, $mon_articles_test_taxonomies, true);
    }
}

if (!function_exists('get_taxonomy')) {
    function get_taxonomy($taxonomy)
    {
        global $mon_articles_test_taxonomy_objects;

        if (!is_array($mon_articles_test_taxonomy_objects)) {
            return null;
        }

        return $mon_articles_test_taxonomy_objects[(string) $taxonomy] ?? null;
    }
}

if (!function_exists('get_terms')) {
    function get_terms($args = array())
    {
        global $mon_articles_test_terms_result, $mon_articles_test_last_get_terms_args;

        $mon_articles_test_last_get_terms_args = is_array($args) ? $args : array();

        return $mon_articles_test_terms_result;
    }
}

}

namespace MonAffichageArticles\Tests {

use Mon_Affichage_Articles;
use MyArticlesJsonResponse;
use PHPUnit\Framework\TestCase;

/**
 * @covers Mon_Affichage_Articles::get_post_type_taxonomies_callback
 * @covers Mon_Affichage_Articles::get_taxonomy_terms_callback
 */
final class AdminAjaxCallbacksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_POST = array();
        $_GET  = array();

        global $mon_articles_test_current_user_caps,
            $mon_articles_test_post_type_objects,
            $mon_articles_test_object_taxonomies,
            $mon_articles_test_taxonomies,
            $mon_articles_test_taxonomy_objects,
            $mon_articles_test_terms_result,
            $mon_articles_test_last_get_terms_args,
            $mon_articles_test_wp_query_factory,
            $mon_articles_test_last_wp_query_args;

        $mon_articles_test_current_user_caps = array();
        $mon_articles_test_post_type_objects = array();
        $mon_articles_test_object_taxonomies = array();
        $mon_articles_test_taxonomies = array();
        $mon_articles_test_taxonomy_objects = array();
        $mon_articles_test_terms_result = array();
        $mon_articles_test_last_get_terms_args = null;
        $mon_articles_test_wp_query_factory = null;
        $mon_articles_test_last_wp_query_args = null;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $_POST = array();
        $_GET  = array();

        global $mon_articles_test_current_user_caps,
            $mon_articles_test_post_type_objects,
            $mon_articles_test_object_taxonomies,
            $mon_articles_test_taxonomies,
            $mon_articles_test_taxonomy_objects,
            $mon_articles_test_terms_result,
            $mon_articles_test_last_get_terms_args,
            $mon_articles_test_wp_query_factory,
            $mon_articles_test_last_wp_query_args;

        $mon_articles_test_current_user_caps = array();
        $mon_articles_test_post_type_objects = array();
        $mon_articles_test_object_taxonomies = array();
        $mon_articles_test_taxonomies = array();
        $mon_articles_test_taxonomy_objects = array();
        $mon_articles_test_terms_result = array();
        $mon_articles_test_last_get_terms_args = null;
        $mon_articles_test_wp_query_factory = null;
        $mon_articles_test_last_wp_query_args = null;
    }

    public function test_get_post_type_taxonomies_callback_requires_valid_post_type(): void
    {
        $_POST['post_type'] = 'invalid_type';

        $plugin = new Mon_Affichage_Articles();

        try {
            $plugin->get_post_type_taxonomies_callback();
            $this->fail('Expected MyArticlesJsonResponse to be thrown.');
        } catch (MyArticlesJsonResponse $response) {
            $this->assertFalse($response->success);
            $this->assertSame(array('message' => 'Type de contenu invalide.'), $response->data);
            $this->assertSame(400, $response->status_code);
        }
    }

    public function test_get_post_type_taxonomies_callback_denies_user_without_cap(): void
    {
        $_POST['post_type'] = 'mon_affichage';

        global $mon_articles_test_post_type_objects, $mon_articles_test_current_user_caps, $mon_articles_test_object_taxonomies;

        $mon_articles_test_post_type_objects = array(
            'mon_affichage' => (object) array(
                'cap' => (object) array('edit_posts' => 'edit_mon_affichage'),
            ),
        );
        $mon_articles_test_current_user_caps = array();
        $mon_articles_test_object_taxonomies = array(
            'mon_affichage' => array(),
        );

        $plugin = new Mon_Affichage_Articles();

        try {
            $plugin->get_post_type_taxonomies_callback();
            $this->fail('Expected MyArticlesJsonResponse to be thrown.');
        } catch (MyArticlesJsonResponse $response) {
            $this->assertFalse($response->success);
            $this->assertSame(array('message' => 'Action non autorisée.'), $response->data);
            $this->assertSame(403, $response->status_code);
        }
    }

    public function test_get_post_type_taxonomies_callback_returns_clean_taxonomy_data(): void
    {
        $_POST['post_type'] = 'mon_affichage';

        global $mon_articles_test_post_type_objects, $mon_articles_test_current_user_caps, $mon_articles_test_object_taxonomies;

        $mon_articles_test_post_type_objects = array(
            'mon_affichage' => (object) array(
                'cap' => (object) array('edit_posts' => 'edit_mon_affichage'),
            ),
        );
        $mon_articles_test_current_user_caps = array('edit_mon_affichage');
        $mon_articles_test_object_taxonomies = array(
            'mon_affichage' => array(
                (object) array(
                    'name'   => 'genre <strong>affiche</strong>',
                    'labels' => (object) array(
                        'singular_name' => 'Genre <em>propre</em>',
                        'label'         => 'Genre <strong>brut</strong>',
                    ),
                    'show_ui' => true,
                ),
                (object) array(
                    'name'    => 'hidden_taxonomy',
                    'labels'  => (object) array(
                        'singular_name' => 'Hidden',
                        'label'         => 'Hidden label',
                    ),
                    'show_ui' => false,
                ),
            ),
        );

        $plugin = new Mon_Affichage_Articles();

        try {
            $plugin->get_post_type_taxonomies_callback();
            $this->fail('Expected MyArticlesJsonResponse to be thrown.');
        } catch (MyArticlesJsonResponse $response) {
            $this->assertTrue($response->success);
            $this->assertSame(200, $response->status_code);
            $this->assertSame(
                array(
                    array(
                        'name'  => 'genre affiche',
                        'label' => 'Genre propre',
                    ),
                ),
                $response->data
            );
        }
    }

    public function test_get_taxonomy_terms_callback_requires_valid_taxonomy(): void
    {
        $_POST['taxonomy'] = 'invalid_taxonomy';

        global $mon_articles_test_taxonomies;

        $mon_articles_test_taxonomies = array('category');

        $plugin = new Mon_Affichage_Articles();

        try {
            $plugin->get_taxonomy_terms_callback();
            $this->fail('Expected MyArticlesJsonResponse to be thrown.');
        } catch (MyArticlesJsonResponse $response) {
            $this->assertFalse($response->success);
            $this->assertSame(array('message' => 'Taxonomie invalide.'), $response->data);
            $this->assertSame(400, $response->status_code);
        }
    }

    public function test_get_taxonomy_terms_callback_denies_user_without_capabilities(): void
    {
        $_POST['taxonomy'] = 'category';

        global $mon_articles_test_taxonomies, $mon_articles_test_taxonomy_objects, $mon_articles_test_current_user_caps;

        $mon_articles_test_taxonomies = array('category');
        $mon_articles_test_taxonomy_objects = array(
            'category' => (object) array(
                'cap' => (object) array(
                    'assign_terms' => 'assign_category',
                    'manage_terms' => 'manage_category',
                ),
            ),
        );
        $mon_articles_test_current_user_caps = array('edit_posts');

        $plugin = new Mon_Affichage_Articles();

        try {
            $plugin->get_taxonomy_terms_callback();
            $this->fail('Expected MyArticlesJsonResponse to be thrown.');
        } catch (MyArticlesJsonResponse $response) {
            $this->assertFalse($response->success);
            $this->assertSame(array('message' => 'Action non autorisée.'), $response->data);
            $this->assertSame(403, $response->status_code);
        }
    }

    public function test_get_taxonomy_terms_callback_applies_search_and_pagination(): void
    {
        $_POST['taxonomy'] = 'category';
        $_POST['search']   = 'Foo';
        $_POST['per_page'] = '200';
        $_POST['page']     = '3';

        global $mon_articles_test_taxonomies,
            $mon_articles_test_taxonomy_objects,
            $mon_articles_test_current_user_caps,
            $mon_articles_test_terms_result,
            $mon_articles_test_last_get_terms_args;

        $mon_articles_test_taxonomies = array('category');
        $mon_articles_test_taxonomy_objects = array(
            'category' => (object) array(
                'cap' => (object) array(
                    'assign_terms' => 'assign_category',
                    'manage_terms' => 'manage_category',
                ),
            ),
        );
        $mon_articles_test_current_user_caps = array('assign_category');
        $mon_articles_test_terms_result = array(
            (object) array('term_id' => 10, 'slug' => 'foo', 'name' => 'Foo'),
            (object) array('term_id' => 20, 'slug' => 'foo-bar', 'name' => 'Foo Bar'),
        );

        $plugin = new Mon_Affichage_Articles();

        try {
            $plugin->get_taxonomy_terms_callback();
            $this->fail('Expected MyArticlesJsonResponse to be thrown.');
        } catch (MyArticlesJsonResponse $response) {
            $this->assertTrue($response->success);
            $this->assertSame(200, $response->status_code);
            $this->assertSame(
                array(
                    array('term_id' => 10, 'slug' => 'foo', 'name' => 'Foo'),
                    array('term_id' => 20, 'slug' => 'foo-bar', 'name' => 'Foo Bar'),
                ),
                $response->data
            );

            $this->assertIsArray($mon_articles_test_last_get_terms_args);
            $this->assertSame('category', $mon_articles_test_last_get_terms_args['taxonomy']);
            $this->assertSame('Foo', $mon_articles_test_last_get_terms_args['search']);
            $this->assertSame('Foo', $mon_articles_test_last_get_terms_args['name__like']);
            $this->assertSame(100, $mon_articles_test_last_get_terms_args['number']);
            $this->assertSame(200, $mon_articles_test_last_get_terms_args['offset']);
        }
    }

    public function test_get_taxonomy_terms_callback_respects_include_parameter(): void
    {
        $_POST['taxonomy'] = 'category';
        $_POST['include']  = '5, 7,5 , 0, 9';

        global $mon_articles_test_taxonomies,
            $mon_articles_test_taxonomy_objects,
            $mon_articles_test_current_user_caps,
            $mon_articles_test_terms_result,
            $mon_articles_test_last_get_terms_args;

        $mon_articles_test_taxonomies = array('category');
        $mon_articles_test_taxonomy_objects = array(
            'category' => (object) array(
                'cap' => (object) array(
                    'assign_terms' => 'assign_category',
                    'manage_terms' => 'manage_category',
                ),
            ),
        );
        $mon_articles_test_current_user_caps = array('manage_category');
        $mon_articles_test_terms_result = array(
            (object) array('term_id' => 5, 'slug' => 'five', 'name' => 'Five'),
            (object) array('term_id' => 7, 'slug' => 'seven', 'name' => 'Seven'),
            (object) array('term_id' => 9, 'slug' => 'nine', 'name' => 'Nine'),
        );

        $plugin = new Mon_Affichage_Articles();

        try {
            $plugin->get_taxonomy_terms_callback();
            $this->fail('Expected MyArticlesJsonResponse to be thrown.');
        } catch (MyArticlesJsonResponse $response) {
            $this->assertTrue($response->success);
            $this->assertSame(200, $response->status_code);
            $this->assertSame(
                array(
                    array('term_id' => 5, 'slug' => 'five', 'name' => 'Five'),
                    array('term_id' => 7, 'slug' => 'seven', 'name' => 'Seven'),
                    array('term_id' => 9, 'slug' => 'nine', 'name' => 'Nine'),
                ),
                $response->data
            );

            $this->assertIsArray($mon_articles_test_last_get_terms_args);
            $this->assertSame('include', $mon_articles_test_last_get_terms_args['orderby']);
            $this->assertSame(array(5, 7, 9), $mon_articles_test_last_get_terms_args['include']);
            $this->assertArrayNotHasKey('number', $mon_articles_test_last_get_terms_args);
            $this->assertArrayNotHasKey('offset', $mon_articles_test_last_get_terms_args);
        }
    }

    public function test_get_taxonomy_terms_callback_handles_wp_error_from_get_terms(): void
    {
        $_POST['taxonomy'] = 'category';

        global $mon_articles_test_taxonomies,
            $mon_articles_test_taxonomy_objects,
            $mon_articles_test_current_user_caps,
            $mon_articles_test_terms_result;

        $mon_articles_test_taxonomies = array('category');
        $mon_articles_test_taxonomy_objects = array(
            'category' => (object) array(
                'cap' => (object) array(
                    'assign_terms' => 'assign_category',
                    'manage_terms' => 'manage_category',
                ),
            ),
        );
        $mon_articles_test_current_user_caps = array('assign_category');
        $mon_articles_test_terms_result = new \WP_Error('terms_failed', 'Unable to fetch terms');

        $plugin = new Mon_Affichage_Articles();

        try {
            $plugin->get_taxonomy_terms_callback();
            $this->fail('Expected MyArticlesJsonResponse to be thrown.');
        } catch (MyArticlesJsonResponse $response) {
            $this->assertFalse($response->success);
            $this->assertSame(500, $response->status_code);
            $this->assertSame(array('message' => 'Unable to fetch terms'), $response->data);
        }
    }

    public function test_search_posts_callback_falls_back_to_default_post_type(): void
    {
        $_GET['search'] = 'Breaking';

        global $mon_articles_test_post_type_objects,
            $mon_articles_test_current_user_caps,
            $mon_articles_test_wp_query_factory,
            $mon_articles_test_last_wp_query_args;

        $mon_articles_test_post_type_objects = array(
            'post' => (object) array(
                'cap' => (object) array('edit_posts' => 'edit_posts'),
            ),
        );
        $mon_articles_test_current_user_caps = array('edit_posts');
        $mon_articles_test_wp_query_factory = function ($args) use (&$mon_articles_test_last_wp_query_args) {
            $mon_articles_test_last_wp_query_args = $args;

            return array(
                'posts' => array(
                    array('ID' => 21),
                    array('ID' => 34),
                ),
            );
        };

        $plugin = new Mon_Affichage_Articles();

        try {
            $plugin->search_posts_callback();
            $this->fail('Expected MyArticlesJsonResponse to be thrown.');
        } catch (MyArticlesJsonResponse $response) {
            $this->assertTrue($response->success);
            $this->assertSame(200, $response->status_code);
            $this->assertSame(
                array(
                    array('id' => 21, 'text' => 'Sample Title'),
                    array('id' => 34, 'text' => 'Sample Title'),
                ),
                $response->data
            );
        }

        $this->assertIsArray($mon_articles_test_last_wp_query_args);
        $this->assertSame('Breaking', $mon_articles_test_last_wp_query_args['s']);
        $this->assertSame('post', $mon_articles_test_last_wp_query_args['post_type']);
    }
}

}
