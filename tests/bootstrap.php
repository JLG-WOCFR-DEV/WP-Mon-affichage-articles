<?php
declare(strict_types=1);

if (!defined('MY_ARTICLES_DISABLE_AUTOBOOT')) {
    define('MY_ARTICLES_DISABLE_AUTOBOOT', true);
}

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!class_exists('WP_UnitTestCase')) {
    abstract class WP_UnitTestCase extends \PHPUnit\Framework\TestCase
    {
        /** @var WP_UnitTest_Factory|null */
        private static $factory = null;

        public static function factory(): WP_UnitTest_Factory
        {
            if (!self::$factory instanceof WP_UnitTest_Factory) {
                self::$factory = new WP_UnitTest_Factory();
            }

            return self::$factory;
        }

        protected function setUp(): void
        {
            parent::setUp();
        }

        protected function tearDown(): void
        {
            parent::tearDown();
        }

        public static function tearDownAfterClass(): void
        {
            self::$factory = null;
        }
    }

    final class WP_UnitTest_Factory
    {
        /** @var WP_UnitTest_Factory_For_Post */
        public $post;

        public function __construct()
        {
            $this->post = new WP_UnitTest_Factory_For_Post();
        }
    }

    final class WP_UnitTest_Factory_For_Post
    {
        private int $last_id = 0;

        /**
         * @param array<string, mixed> $args
         * @return int
         */
        public function create(array $args = array()): int
        {
            global $mon_articles_test_posts;

            if (!is_array($mon_articles_test_posts)) {
                $mon_articles_test_posts = array();
            }

            $this->last_id++;
            $post_id = isset($args['ID']) ? (int) $args['ID'] : $this->last_id;

            $defaults = array(
                'ID'           => $post_id,
                'post_title'   => 'Post ' . $post_id,
                'post_type'    => 'post',
                'post_status'  => 'publish',
                'post_author'  => 1,
                'post_content' => '',
            );

            $post_data = array_merge($defaults, $args);
            $post_data['ID'] = $post_id;

            $mon_articles_test_posts[$post_id] = (object) $post_data;

            return $post_id;
        }
    }
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!isset($mon_articles_test_terms) || !is_array($mon_articles_test_terms)) {
    $mon_articles_test_terms = array(
        'category' => array(
            (object) array('term_id' => 1, 'slug' => 'actus', 'name' => 'Actualités'),
            (object) array('term_id' => 2, 'slug' => 'culture', 'name' => 'Culture'),
        ),
    );
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        /** @var string */
        private $code;

        /** @var string */
        private $message;

        /** @var mixed */
        private $data;

        public function __construct($code = '', $message = '', $data = null)
        {
            $this->code = (string) $code;
            $this->message = (string) $message;
            $this->data = $data;
        }

        public function get_error_code()
        {
            return $this->code;
        }

        public function get_error_message()
        {
            return $this->message;
        }

        public function get_error_data()
        {
            return $this->data;
        }
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server
    {
        public const READABLE = 'GET';
        public const CREATABLE = 'POST';
    }
}

if (!class_exists('WP_REST_Controller')) {
    abstract class WP_REST_Controller
    {
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        /** @var array<string, mixed> */
        private $params = array();

        /** @var array<string, string> */
        private $headers = array();

        /** @var string */
        private $method;

        /** @var string */
        private $route;

        public function __construct($method = 'GET', $route = '')
        {
            $this->method = (string) $method;
            $this->route  = (string) $route;
        }

        public function get_method()
        {
            return $this->method;
        }

        public function get_route()
        {
            return $this->route;
        }

        public function set_param($key, $value): void
        {
            $this->params[(string) $key] = $value;
        }

        public function get_param($key)
        {
            $key = (string) $key;

            return $this->params[$key] ?? null;
        }

        public function get_params(): array
        {
            return $this->params;
        }

        public function get_json_params(): array
        {
            return $this->params;
        }

        public function set_header($key, $value): void
        {
            $this->headers[strtolower((string) $key)] = (string) $value;
        }

        public function get_header($key)
        {
            $key = strtolower((string) $key);

            return $this->headers[$key] ?? '';
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        /** @var mixed */
        private $data;

        /** @var int */
        private $status;

        public function __construct($data = null, $status = 200)
        {
            $this->data   = $data;
            $this->status = (int) $status;
        }

        public function get_data()
        {
            return $this->data;
        }

        public function set_data($data): void
        {
            $this->data = $data;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        public function set_status($status): void
        {
            $this->status = (int) $status;
        }
    }
}

if (!function_exists('rest_ensure_response')) {
    function rest_ensure_response($response)
    {
        if (is_wp_error($response) || $response instanceof WP_REST_Response) {
            return $response;
        }

        return new WP_REST_Response($response);
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1)
    {
        global $mon_articles_test_valid_nonces;

        if (!is_array($mon_articles_test_valid_nonces)) {
            return false;
        }

        $nonce  = (string) $nonce;
        $action = (string) $action;

        if (!isset($mon_articles_test_valid_nonces[$nonce])) {
            return false;
        }

        $actions = (array) $mon_articles_test_valid_nonces[$nonce];

        return in_array($action, array_map('strval', $actions), true);
    }
}

if (!function_exists('__')) {
    function __($text, $domain = null)
    {
        return $text;
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file)
    {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file)
    {
        return 'http://example.com/wp-content/plugins/mon-affichage-articles/';
    }
}

if (!function_exists('get_post_types')) {
    function get_post_types($args = array(), $output = 'names', $operator = 'and')
    {
        $post_types = array(
            'post'          => (object) array('name' => 'post', 'label' => 'Posts'),
            'page'          => (object) array('name' => 'page', 'label' => 'Pages'),
            'mon_affichage' => (object) array('name' => 'mon_affichage', 'label' => 'Affichages'),
        );

        if ('objects' === $output) {
            return $post_types;
        }

        return array_keys($post_types);
    }
}

if (!function_exists('get_post')) {
    function get_post($post = null)
    {
        global $mon_articles_test_posts;

        if (!is_array($mon_articles_test_posts)) {
            $mon_articles_test_posts = array();
        }

        if (is_object($post) || is_array($post)) {
            $post = is_object($post) ? ($post->ID ?? 0) : ($post['ID'] ?? 0);
        }

        $post_id = is_numeric($post) ? (int) $post : 0;

        return $mon_articles_test_posts[$post_id] ?? null;
    }
}

if (!function_exists('get_object_taxonomies')) {
    function get_object_taxonomies($post_type, $output = 'names')
    {
        global $mon_articles_test_taxonomies;

        if (!is_array($mon_articles_test_taxonomies)) {
            return array();
        }

        $post_type = my_articles_normalize_post_type($post_type ?? '');

        if (!isset($mon_articles_test_taxonomies[$post_type])) {
            return array();
        }

        $taxonomies = $mon_articles_test_taxonomies[$post_type];

        if ('objects' === $output) {
            return $taxonomies;
        }

        if (!is_array($taxonomies)) {
            return array();
        }

        return array_keys($taxonomies);
    }
}

if (!function_exists('get_terms')) {
    function get_terms($args = array(), $deprecated = '')
    {
        global $mon_articles_test_terms;

        if (!is_array($args)) {
            $args = array();
        }

        if (!is_array($mon_articles_test_terms)) {
            return array();
        }

        $taxonomy = '';

        if (isset($args['taxonomy'])) {
            $taxonomy = is_array($args['taxonomy']) ? reset($args['taxonomy']) : (string) $args['taxonomy'];
        }

        if (!$taxonomy || !isset($mon_articles_test_terms[$taxonomy])) {
            return array();
        }

        return $mon_articles_test_terms[$taxonomy];
    }
}

if (!function_exists('taxonomy_exists')) {
    function taxonomy_exists($taxonomy)
    {
        global $mon_articles_test_taxonomies;

        $taxonomy = sanitize_key($taxonomy ?? '');

        if (!$taxonomy) {
            return false;
        }

        if (is_array($mon_articles_test_taxonomies)) {
            foreach ($mon_articles_test_taxonomies as $taxonomies) {
                if (is_array($taxonomies) && isset($taxonomies[$taxonomy])) {
                    return true;
                }
            }
        }

        return in_array($taxonomy, array('category', 'post_tag'), true);
    }
}

if (!function_exists('is_object_in_taxonomy')) {
    function is_object_in_taxonomy($post_type, $taxonomy)
    {
        global $mon_articles_test_taxonomies;

        $post_type = my_articles_normalize_post_type($post_type ?? '');
        $taxonomy  = sanitize_key($taxonomy ?? '');

        if (!$post_type || !$taxonomy) {
            return false;
        }

        if (is_array($mon_articles_test_taxonomies) && isset($mon_articles_test_taxonomies[$post_type])) {
            $taxonomies = $mon_articles_test_taxonomies[$post_type];

            if (is_array($taxonomies) && isset($taxonomies[$taxonomy])) {
                return true;
            }
        }

        return in_array($taxonomy, array('category', 'post_tag'), true);
    }
}

if (!function_exists('post_type_exists')) {
    function post_type_exists($post_type)
    {
        $post_type = (string) $post_type;

        return in_array($post_type, array('post', 'page', 'mon_affichage'), true);
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): void
    {
        global $mon_articles_test_filters;

        if (!is_array($mon_articles_test_filters)) {
            $mon_articles_test_filters = array();
        }

        $priority = (int) $priority;

        if (!isset($mon_articles_test_filters[$hook])) {
            $mon_articles_test_filters[$hook] = array();
        }

        if (!isset($mon_articles_test_filters[$hook][$priority])) {
            $mon_articles_test_filters[$hook][$priority] = array();
        }

        $mon_articles_test_filters[$hook][$priority][] = array(
            'callback'      => $callback,
            'accepted_args' => (int) $accepted_args,
        );
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args)
    {
        global $mon_articles_test_filters;

        if (!is_array($mon_articles_test_filters) || empty($mon_articles_test_filters[$hook])) {
            return $value;
        }

        ksort($mon_articles_test_filters[$hook]);

        foreach ($mon_articles_test_filters[$hook] as $callbacks) {
            foreach ($callbacks as $callback_data) {
                $accepted_args = isset($callback_data['accepted_args']) ? (int) $callback_data['accepted_args'] : 1;
                $parameters    = array($value);

                if ($accepted_args > 1) {
                    $extra_args = array_slice($args, 0, $accepted_args - 1);
                    $parameters = array_merge($parameters, $extra_args);
                }

                $value = call_user_func_array($callback_data['callback'], $parameters);
            }
        }

        return $value;
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title)
    {
        $title = strtolower((string) $title);
        $title = preg_replace('/[^a-z0-9-_]+/', '-', $title);

        return trim((string) $title, '-');
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

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string, $remove_breaks = false)
    {
        $string = strip_tags((string) $string);

        if ($remove_breaks) {
            $string = preg_replace('/[\r\n\t]+/', '', $string);
        }

        return $string;
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '', $scheme = null)
    {
        return 'http://example.com' . ('' !== $path ? '/' . ltrim((string) $path, '/') : '');
    }
}

if (!function_exists('wp_get_referer')) {
    function wp_get_referer()
    {
        return 'http://example.com/referer';
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url)
    {
        return (string) $url;
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1)
    {
        return parse_url((string) $url, $component);
    }
}

if (!function_exists('maybe_serialize')) {
    function maybe_serialize($data)
    {
        if (is_array($data) || is_object($data)) {
            return serialize($data);
        }

        if (is_scalar($data) || null === $data) {
            return $data;
        }

        return serialize($data);
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

if (!function_exists('do_action')) {
    function do_action($hook, ...$args): void
    {
        // No-op for tests.
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post = null)
    {
        global $mon_articles_test_post_type_map;

        if (!is_array($mon_articles_test_post_type_map)) {
            return null;
        }

        $post_id = is_numeric($post) ? (int) $post : 0;

        return $mon_articles_test_post_type_map[$post_id] ?? null;
    }
}

if (!function_exists('get_post_status')) {
    function get_post_status($post = null)
    {
        global $mon_articles_test_post_status_map;

        if (!is_array($mon_articles_test_post_status_map)) {
            return false;
        }

        $post_id = is_numeric($post) ? (int) $post : 0;

        return $mon_articles_test_post_status_map[$post_id] ?? false;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false)
    {
        global $mon_articles_test_post_meta_map;

        if (!is_array($mon_articles_test_post_meta_map)) {
            return $single ? '' : array();
        }

        $post_id = (int) $post_id;

        if (!isset($mon_articles_test_post_meta_map[$post_id])) {
            return $single ? '' : array();
        }

        if ('' === $key) {
            return $mon_articles_test_post_meta_map[$post_id];
        }

        if (!array_key_exists($key, $mon_articles_test_post_meta_map[$post_id])) {
            return $single ? '' : array();
        }

        $value = $mon_articles_test_post_meta_map[$post_id][$key];

        if ($single) {
            if (is_array($value) && array_keys($value) === range(0, count($value) - 1)) {
                return $value[0] ?? '';
            }

            return $value;
        }

        return $value;
    }
}

if (!function_exists('add_post_meta')) {
    function add_post_meta($post_id, $meta_key, $meta_value)
    {
        global $mon_articles_test_post_meta_map;

        if (!is_array($mon_articles_test_post_meta_map)) {
            $mon_articles_test_post_meta_map = array();
        }

        $post_id = (int) $post_id;

        if (!isset($mon_articles_test_post_meta_map[$post_id])) {
            $mon_articles_test_post_meta_map[$post_id] = array();
        }

        if (!isset($mon_articles_test_post_meta_map[$post_id][$meta_key])) {
            $mon_articles_test_post_meta_map[$post_id][$meta_key] = array();
        }

        if (!is_array($mon_articles_test_post_meta_map[$post_id][$meta_key])) {
            $mon_articles_test_post_meta_map[$post_id][$meta_key] = array($mon_articles_test_post_meta_map[$post_id][$meta_key]);
        }

        $mon_articles_test_post_meta_map[$post_id][$meta_key][] = $meta_value;

        return true;
    }
}

if (!function_exists('shortcode_atts')) {
    function shortcode_atts($pairs, $atts, $shortcode = '')
    {
        $atts = (array) $atts;
        $out = array();

        foreach ((array) $pairs as $name => $default) {
            if (array_key_exists($name, $atts)) {
                $out[$name] = $atts[$name];
            } else {
                $out[$name] = $default;
            }
            unset($atts[$name]);
        }

        foreach ($atts as $name => $value) {
            $out[$name] = $value;
        }

        return $out;
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback): void
    {
        // No-op for tests.
    }
}

if (!function_exists('remove_all_filters')) {
    function remove_all_filters($hook = null): void
    {
        global $mon_articles_test_filters;

        if (!is_array($mon_articles_test_filters)) {
            $mon_articles_test_filters = array();
        }

        if (null === $hook) {
            $mon_articles_test_filters = array();

            return;
        }

        unset($mon_articles_test_filters[$hook]);
    }
}

if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color)
    {
        if (!is_string($color)) {
            return null;
        }

        $color = trim($color);

        if ('' === $color || '#' !== $color[0]) {
            return null;
        }

        $hex = substr($color, 1);

        if (!preg_match('/^([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex)) {
            return null;
        }

        return '#' . strtolower($hex);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key)
    {
        if (!is_scalar($key)) {
            return '';
        }

        $key = strtolower((string) $key);

        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('wp_list_pluck')) {
    function wp_list_pluck($list, $field)
    {
        $values = array();

        if (!is_iterable($list)) {
            return $values;
        }

        foreach ($list as $item) {
            if (is_array($item) && array_key_exists($field, $item)) {
                $values[] = $item[$field];
            } elseif (is_object($item) && isset($item->{$field})) {
                $values[] = $item->{$field};
            }
        }

        return $values;
    }
}

if (!function_exists('wp_is_post_revision')) {
    function wp_is_post_revision($post_id)
    {
        return false;
    }
}

if (!function_exists('wp_is_post_autosave')) {
    function wp_is_post_autosave($post_id)
    {
        return false;
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false)
    {
        static $counter = 0;

        $counter++;

        if ($length <= 0) {
            $length = 12;
        }

        $hash = md5('mon-articles-' . $counter);

        if ($length > strlen($hash)) {
            $hash = str_repeat($hash, (int) ceil($length / strlen($hash)));
        }

        return substr($hash, 0, (int) $length);
    }
}

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '', $force = false, &$found = null)
    {
        global $mon_articles_test_wp_cache;

        if (!is_array($mon_articles_test_wp_cache)) {
            $mon_articles_test_wp_cache = array();
        }

        $group = (string) $group;

        if (!isset($mon_articles_test_wp_cache[$group])) {
            $mon_articles_test_wp_cache[$group] = array();
        }

        if (array_key_exists($key, $mon_articles_test_wp_cache[$group])) {
            $found = true;

            return $mon_articles_test_wp_cache[$group][$key];
        }

        $found = false;

        return false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $value, $group = '', $expire = 0)
    {
        global $mon_articles_test_wp_cache;

        if (!is_array($mon_articles_test_wp_cache)) {
            $mon_articles_test_wp_cache = array();
        }

        $group = (string) $group;

        if (!isset($mon_articles_test_wp_cache[$group])) {
            $mon_articles_test_wp_cache[$group] = array();
        }

        $mon_articles_test_wp_cache[$group][$key] = $value;

        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient)
    {
        global $mon_articles_test_transients;

        if (!is_array($mon_articles_test_transients) || !array_key_exists($transient, $mon_articles_test_transients)) {
            return false;
        }

        return $mon_articles_test_transients[$transient]['value'];
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0)
    {
        global $mon_articles_test_transients;

        if (!is_array($mon_articles_test_transients)) {
            $mon_articles_test_transients = array();
        }

        $mon_articles_test_transients[$transient] = array(
            'value'      => $value,
            'expiration' => (int) $expiration,
        );

        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action(...$args): void
    {
        // No-op for tests.
    }
}

if (!function_exists('add_action')) {
    function add_action(...$args): void
    {
        // No-op for tests.
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return !empty($GLOBALS['mon_articles_test_is_admin']);
    }
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax(): bool
    {
        return !empty($GLOBALS['mon_articles_test_doing_ajax']);
    }
}

if (!function_exists('did_action')) {
    function did_action($hook_name)
    {
        return 0;
    }
}

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false)
    {
        global $mon_articles_test_options_store, $mon_articles_test_options;

        if (!is_array($mon_articles_test_options_store)) {
            if (is_array($mon_articles_test_options ?? null)) {
                $mon_articles_test_options_store = $mon_articles_test_options;
            } else {
                $mon_articles_test_options_store = array();
            }
        }

        return $mon_articles_test_options_store[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, $value)
    {
        global $mon_articles_test_options_store, $mon_articles_test_options;

        if (!is_array($mon_articles_test_options_store)) {
            $mon_articles_test_options_store = array();
        }

        $mon_articles_test_options_store[$option] = $value;

        if (is_array($mon_articles_test_options)) {
            $mon_articles_test_options[$option] = $value;
        }

        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $option)
    {
        global $mon_articles_test_options_store, $mon_articles_test_options;

        if (!is_array($mon_articles_test_options_store)) {
            $mon_articles_test_options_store = array();
        }

        $option = (string) $option;

        $existed = array_key_exists($option, $mon_articles_test_options_store);

        unset($mon_articles_test_options_store[$option]);

        if (is_array($mon_articles_test_options) && array_key_exists($option, $mon_articles_test_options)) {
            unset($mon_articles_test_options[$option]);
        }

        return $existed;
    }
}

if (!class_exists('WP_Styles')) {
    class WP_Styles
    {
        /** @var array<string, array<int, string>> */
        public array $inline_styles = array();

        public function add_inline_style(string $handle, string $data): bool
        {
            if ('' === $handle) {
                return false;
            }

            if (!isset($this->inline_styles[$handle])) {
                $this->inline_styles[$handle] = array();
            }

            $this->inline_styles[$handle][] = $data;

            return true;
        }
    }
}

if (!function_exists('wp_styles')) {
    function wp_styles(): WP_Styles
    {
        global $wp_styles;

        if (!$wp_styles instanceof WP_Styles) {
            $wp_styles = new WP_Styles();
        }

        return $wp_styles;
    }
}

if (!function_exists('wp_add_inline_style')) {
    function wp_add_inline_style(string $handle, string $data): bool
    {
        return wp_styles()->add_inline_style($handle, $data);
    }
}

if (!function_exists('wp_register_style')) {
    /**
     * @param string $handle
     * @param string $src
     * @param array<int, string> $deps
     * @param string|bool $ver
     * @param string $media
     */
    function wp_register_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all'): bool
    {
        global $mon_articles_test_registered_styles;

        if (!is_array($mon_articles_test_registered_styles)) {
            $mon_articles_test_registered_styles = array();
        }

        $mon_articles_test_registered_styles[(string) $handle] = array(
            'src'   => (string) $src,
            'deps'  => is_array($deps) ? array_values($deps) : array(),
            'ver'   => is_string($ver) ? $ver : '',
            'media' => (string) $media,
        );

        return true;
    }
}

if (!function_exists('wp_register_script')) {
    /**
     * @param string $handle
     * @param string|false $src
     * @param array<int, string> $deps
     * @param string|bool $ver
     * @param bool $in_footer
     */
    function wp_register_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false): bool
    {
        global $mon_articles_test_registered_scripts;

        if (!is_array($mon_articles_test_registered_scripts)) {
            $mon_articles_test_registered_scripts = array();
        }

        $mon_articles_test_registered_scripts[(string) $handle] = array(
            'src'       => (false === $src) ? false : (string) $src,
            'deps'      => is_array($deps) ? array_values($deps) : array(),
            'ver'       => is_string($ver) ? $ver : '',
            'in_footer' => (bool) $in_footer,
        );

        return true;
    }
}

if (!function_exists('wp_script_add_data')) {
    /**
     * @param string $handle
     * @param string $key
     * @param mixed $value
     */
    function wp_script_add_data($handle, $key, $value): bool
    {
        global $mon_articles_test_script_data;

        if (!is_array($mon_articles_test_script_data)) {
            $mon_articles_test_script_data = array();
        }

        if (!isset($mon_articles_test_script_data[(string) $handle])) {
            $mon_articles_test_script_data[(string) $handle] = array();
        }

        $mon_articles_test_script_data[(string) $handle][(string) $key] = $value;

        return true;
    }
}

if (!function_exists('wp_enqueue_style')) {
    /**
     * @param string $handle
     * @param string $src
     * @param array<int, string> $deps
     * @param string|bool $ver
     * @param string $media
     */
    function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all'): void
    {
        global $mon_articles_test_enqueued_styles;

        if (!is_array($mon_articles_test_enqueued_styles)) {
            $mon_articles_test_enqueued_styles = array();
        }

        $mon_articles_test_enqueued_styles[] = array(
            'handle' => (string) $handle,
            'src'    => (string) $src,
            'deps'   => is_array($deps) ? array_values($deps) : array(),
            'ver'    => is_string($ver) ? $ver : '',
            'media'  => (string) $media,
        );
    }
}

if (!function_exists('wp_enqueue_script')) {
    /**
     * @param string $handle
     * @param string $src
     * @param array<int, string> $deps
     * @param string|bool $ver
     * @param bool $in_footer
     */
    function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false): void
    {
        global $mon_articles_test_enqueued_scripts;

        if (!is_array($mon_articles_test_enqueued_scripts)) {
            $mon_articles_test_enqueued_scripts = array();
        }

        $mon_articles_test_enqueued_scripts[] = array(
            'handle'    => (string) $handle,
            'src'       => (string) $src,
            'deps'      => is_array($deps) ? array_values($deps) : array(),
            'ver'       => is_string($ver) ? $ver : '',
            'in_footer' => (bool) $in_footer,
        );
    }
}

if (!function_exists('wp_script_is')) {
    function wp_script_is($handle, $list = 'enqueued')
    {
        global $mon_articles_test_registered_scripts, $mon_articles_test_enqueued_scripts, $mon_articles_test_marked_scripts;

        $handle = (string) $handle;
        $list   = (string) $list;

        if ('registered' === $list) {
            if (is_array($mon_articles_test_registered_scripts) && isset($mon_articles_test_registered_scripts[$handle])) {
                return true;
            }

            if (is_array($mon_articles_test_marked_scripts) && in_array($handle, $mon_articles_test_marked_scripts, true)) {
                return true;
            }

            return false;
        }

        if ('enqueued' === $list) {
            if (is_array($mon_articles_test_enqueued_scripts)) {
                foreach ($mon_articles_test_enqueued_scripts as $script) {
                    if (($script['handle'] ?? '') === $handle) {
                        return true;
                    }
                }
            }

            if (is_array($mon_articles_test_marked_scripts) && in_array($handle, $mon_articles_test_marked_scripts, true)) {
                return true;
            }

            return false;
        }

        return false;
    }
}

if (!function_exists('wp_add_inline_script')) {
    function wp_add_inline_script($handle, $data, $position = 'after')
    {
        global $mon_articles_test_inline_scripts;

        if (!is_array($mon_articles_test_inline_scripts)) {
            $mon_articles_test_inline_scripts = array();
        }

        $mon_articles_test_inline_scripts[] = array(
            'handle'   => (string) $handle,
            'data'     => (string) $data,
            'position' => (string) $position,
        );

        return true;
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = array())
    {
        if (is_object($args)) {
            $args = get_object_vars($args);
        } elseif (!is_array($args)) {
            parse_str((string) $args, $args);
        }

        return array_merge($defaults, $args);
    }
}

if (!function_exists('absint')) {
    function absint($maybeint)
    {
        return abs((int) $maybeint);
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post = null)
    {
        return 'http://example.com/post/' . (is_numeric($post) ? (int) $post : 'current');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url)
    {
        return (string) $url;
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url)
    {
        return (string) $url;
    }
}

if (!function_exists('rest_url')) {
    function rest_url($path = '', $scheme = 'rest')
    {
        $path = is_string($path) ? ltrim($path, '/') : '';

        return 'http://example.com/wp-json/' . $path;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action)
    {
        return 'nonce-' . (string) $action;
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12)
    {
        $length = (int) $length;
        if ($length <= 0) {
            $length = 12;
        }

        return str_repeat('a', $length);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '')
    {
        global $mon_articles_test_cache_store;

        if (!is_array($mon_articles_test_cache_store)) {
            $mon_articles_test_cache_store = array();
        }

        $group = (string) $group;
        if (!isset($mon_articles_test_cache_store[$group])) {
            return false;
        }

        return $mon_articles_test_cache_store[$group][$key] ?? false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $value, $group = '', $expire = 0)
    {
        global $mon_articles_test_cache_store;

        if (!is_array($mon_articles_test_cache_store)) {
            $mon_articles_test_cache_store = array();
        }

        $group = (string) $group;
        if (!isset($mon_articles_test_cache_store[$group])) {
            $mon_articles_test_cache_store[$group] = array();
        }

        $mon_articles_test_cache_store[$group][$key] = $value;

        return true;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '')
    {
        global $mon_articles_test_cache_store;

        if (!is_array($mon_articles_test_cache_store)) {
            return false;
        }

        $group = (string) $group;
        if (!isset($mon_articles_test_cache_store[$group])) {
            return false;
        }

        if (!array_key_exists($key, $mon_articles_test_cache_store[$group])) {
            return false;
        }

        unset($mon_articles_test_cache_store[$group][$key]);

        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient)
    {
        global $mon_articles_test_transients_store;

        if (!is_array($mon_articles_test_transients_store)) {
            $mon_articles_test_transients_store = array();
        }

        $key = (string) $transient;

        if (!isset($mon_articles_test_transients_store[$key])) {
            return false;
        }

        $entry = $mon_articles_test_transients_store[$key];

        if (isset($entry['expires']) && $entry['expires'] > 0 && $entry['expires'] < time()) {
            unset($mon_articles_test_transients_store[$key]);

            return false;
        }

        return $entry['value'];
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0)
    {
        global $mon_articles_test_transients_store;

        if (!is_array($mon_articles_test_transients_store)) {
            $mon_articles_test_transients_store = array();
        }

        $mon_articles_test_transients_store[(string) $transient] = array(
            'value'   => $value,
            'expires' => $expiration > 0 ? time() + (int) $expiration : 0,
        );

        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient)
    {
        global $mon_articles_test_transients_store;

        if (!is_array($mon_articles_test_transients_store)) {
            return false;
        }

        $key = (string) $transient;

        if (!isset($mon_articles_test_transients_store[$key])) {
            return false;
        }

        unset($mon_articles_test_transients_store[$key]);

        return true;
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title($post = 0)
    {
        return 'Sample Title';
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text)
    {
        return (string) $text;
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = null): void
    {
        echo esc_attr($text);
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text)
    {
        return (string) $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null)
    {
        return $text;
    }
}

if (!function_exists('has_post_thumbnail')) {
    function has_post_thumbnail($post = null)
    {
        return false;
    }
}

if (!function_exists('get_the_terms')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function get_the_terms($post_id, $taxonomy)
    {
        return array();
    }
}

if (!function_exists('wp_get_attachment_image_src')) {
    function wp_get_attachment_image_src($attachment_id, $size = 'thumbnail')
    {
        return array('http://example.com/image-' . (int) $attachment_id . '.jpg', 800, 600);
    }
}

if (!function_exists('wp_get_attachment_image_srcset')) {
    function wp_get_attachment_image_srcset($attachment_id, $size = 'thumbnail')
    {
        return 'http://example.com/image-' . (int) $attachment_id . '-1x.jpg 1x';
    }
}

if (!function_exists('wp_get_attachment_image')) {
    function wp_get_attachment_image($attachment_id, $size = 'thumbnail', $icon = false, $attr = '')
    {
        $attributes_string = '';

        if (is_array($attr)) {
            foreach ($attr as $name => $value) {
                if ('' === $name) {
                    continue;
                }

                $attributes_string .= sprintf(' %s="%s"', esc_attr($name), esc_attr((string) $value));
            }
        }

        $size_descriptor = is_array($size) ? 'custom' : (string) $size;

        return sprintf('<img src="http://example.com/image-%d-%s.jpg"%s />', (int) $attachment_id, $size_descriptor, $attributes_string);
    }
}

if (!function_exists('get_post_thumbnail_id')) {
    function get_post_thumbnail_id($post = null)
    {
        return 0;
    }
}

if (!function_exists('the_post_thumbnail')) {
    function the_post_thumbnail($size = 'post-thumbnail', $attr = '')
    {
        // No-op.
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($content)
    {
        return (string) $content;
    }
}

if (!function_exists('get_the_term_list')) {
    function get_the_term_list($post_id, $taxonomy, $before = '', $sep = '', $after = '')
    {
        global $mon_articles_test_term_list_callback;

        if (is_callable($mon_articles_test_term_list_callback)) {
            return $mon_articles_test_term_list_callback($post_id, $taxonomy, $before, $sep, $after);
        }

        return '';
    }
}

if (!function_exists('get_the_terms')) {
    function get_the_terms($post_id, $taxonomy)
    {
        global $mon_articles_test_term_list_callback;

        if (is_callable($mon_articles_test_term_list_callback)) {
            $result = $mon_articles_test_term_list_callback($post_id, $taxonomy, '', '', '');

            if ($result instanceof \WP_Error) {
                return $result;
            }

            if (is_array($result)) {
                return $result;
            }
        }

        return array();
    }
}

if (!function_exists('get_the_author_meta')) {
    function get_the_author_meta($field, $user_id = false)
    {
        if ('ID' === $field) {
            return 1;
        }

        return '';
    }
}

if (!function_exists('get_the_author')) {
    function get_the_author()
    {
        return 'Author Name';
    }
}

if (!function_exists('get_author_posts_url')) {
    function get_author_posts_url($author_id, $author_nicename = '', $author_nickname = '')
    {
        return 'http://example.com/author/' . (int) $author_id;
    }
}

if (!function_exists('get_the_date')) {
    function get_the_date($format = '', $post = null)
    {
        return '2023-01-01';
    }
}

if (!function_exists('get_the_excerpt')) {
    function get_the_excerpt($post = null)
    {
        return 'Excerpt content';
    }
}

if (!function_exists('get_the_terms')) {
    function get_the_terms($post = null, $taxonomy = '')
    {
        return array();
    }
}

if (!function_exists('wp_trim_words')) {
    function wp_trim_words($text, $num_words = 55, $more = null)
    {
        return (string) $text;
    }
}

if (!function_exists('wp_reset_postdata')) {
    function wp_reset_postdata(): void
    {
        global $mon_articles_test_wp_reset_postdata_callback;

        if (is_callable($mon_articles_test_wp_reset_postdata_callback)) {
            call_user_func($mon_articles_test_wp_reset_postdata_callback);
        }
    }
}

if (!function_exists('get_the_ID')) {
    function get_the_ID()
    {
        global $mon_articles_test_current_post_id;

        return $mon_articles_test_current_post_id ?? 0;
    }
}

if (!class_exists('WP_Query')) {
    class WP_Query
    {
        /** @var array<int, array<string, mixed>> */
        public array $posts;

        private int $current_index = 0;

        public int $found_posts = 0;

        /**
         * @param array<int, array<string, mixed>>|array<string, mixed> $posts
         */
        public function __construct(array $posts = array())
        {
            global $mon_articles_test_wp_query_factory;

            $this->current_index = 0;

            if ($this->is_associative($posts)) {
                $factory = $mon_articles_test_wp_query_factory ?? null;

                if (is_callable($factory)) {
                    $result = $factory($posts);
                    $this->posts = array_values($result['posts'] ?? array());
                    $this->found_posts = isset($result['found_posts']) ? (int) $result['found_posts'] : count($this->posts);
                } else {
                    $this->posts = array();
                    $this->found_posts = 0;
                }
            } else {
                $this->posts = array_values($posts);
                $this->found_posts = count($this->posts);
            }
        }

        private function is_associative(array $array): bool
        {
            return $array !== array_values($array);
        }

        public function have_posts(): bool
        {
            return $this->current_index < count($this->posts);
        }

        public function the_post(): void
        {
            global $mon_articles_test_current_post_id, $post;

            if (!$this->have_posts()) {
                return;
            }

            $post_data = $this->posts[$this->current_index];
            $this->current_index++;

            $mon_articles_test_current_post_id = isset($post_data['ID']) ? (int) $post_data['ID'] : 0;
            $post = is_array($post_data) ? (object) $post_data : $post_data;
        }
    }
}

require_once __DIR__ . '/../mon-affichage-article/mon-affichage-articles.php';
require_once __DIR__ . '/../mon-affichage-article/includes/interface-my-articles-content-adapter.php';
require_once __DIR__ . '/../mon-affichage-article/includes/helpers.php';
require_once __DIR__ . '/../mon-affichage-article/includes/class-my-articles-shortcode-data-preparer.php';
require_once __DIR__ . '/../mon-affichage-article/includes/class-my-articles-settings-sanitizer.php';
require_once __DIR__ . '/../mon-affichage-article/includes/class-my-articles-shortcode.php';
require_once __DIR__ . '/../mon-affichage-article/includes/class-my-articles-render-result.php';
require_once __DIR__ . '/../mon-affichage-article/includes/class-my-articles-response-renderer.php';
require_once __DIR__ . '/../mon-affichage-article/includes/class-my-articles-response-cache-key.php';
require_once __DIR__ . '/../mon-affichage-article/includes/rest/class-my-articles-controller.php';
