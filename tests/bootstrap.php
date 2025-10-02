<?php
declare(strict_types=1);

if (!defined('MY_ARTICLES_DISABLE_AUTOBOOT')) {
    define('MY_ARTICLES_DISABLE_AUTOBOOT', true);
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
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

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
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
        if ('objects' === $output) {
            return array();
        }

        return array('post', 'page');
    }
}

if (!function_exists('post_type_exists')) {
    function post_type_exists($post_type)
    {
        $post_type = (string) $post_type;

        return in_array($post_type, array('post', 'page', 'mon_affichage'), true);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value)
    {
        return $value;
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

        $value = $mon_articles_test_post_meta_map[$post_id][$key] ?? ($single ? '' : array());

        return $value;
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

if (!function_exists('add_action')) {
    function add_action(...$args): void
    {
        // No-op for tests.
    }
}

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false)
    {
        global $mon_articles_test_options_store;

        if (!is_array($mon_articles_test_options_store)) {
            $mon_articles_test_options_store = array();
        }

        return $mon_articles_test_options_store[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, $value)
    {
        global $mon_articles_test_options_store;

        if (!is_array($mon_articles_test_options_store)) {
            $mon_articles_test_options_store = array();
        }

        $mon_articles_test_options_store[$option] = $value;

        return true;
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
        private array $posts;

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
require_once __DIR__ . '/../mon-affichage-article/includes/helpers.php';
require_once __DIR__ . '/../mon-affichage-article/includes/class-my-articles-shortcode.php';
