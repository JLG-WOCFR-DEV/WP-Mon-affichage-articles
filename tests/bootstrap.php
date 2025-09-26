<?php
declare(strict_types=1);

if (!defined('MY_ARTICLES_DISABLE_AUTOBOOT')) {
    define('MY_ARTICLES_DISABLE_AUTOBOOT', true);
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
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

if (!function_exists('add_action')) {
    function add_action(...$args): void
    {
        // No-op for tests.
    }
}

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false)
    {
        return $default;
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

if (!function_exists('get_post_thumbnail_id')) {
    function get_post_thumbnail_id($post = null)
    {
        return 0;
    }
}

if (!function_exists('the_post_thumbnail')) {
    function the_post_thumbnail($size = 'post-thumbnail')
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

if (!function_exists('wp_trim_words')) {
    function wp_trim_words($text, $num_words = 55, $more = null)
    {
        return (string) $text;
    }
}

if (!function_exists('wp_reset_postdata')) {
    function wp_reset_postdata(): void
    {
        // No-op for tests.
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
            global $mon_articles_test_current_post_id;

            if (!$this->have_posts()) {
                return;
            }

            $post = $this->posts[$this->current_index];
            $this->current_index++;

            $mon_articles_test_current_post_id = isset($post['ID']) ? (int) $post['ID'] : 0;
        }
    }
}

require_once __DIR__ . '/../mon-affichage-article/mon-affichage-articles.php';
require_once __DIR__ . '/../mon-affichage-article/includes/helpers.php';
require_once __DIR__ . '/../mon-affichage-article/includes/class-my-articles-shortcode.php';
