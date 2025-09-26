<?php
declare(strict_types=1);

if (!defined('MY_ARTICLES_DISABLE_AUTOBOOT')) {
    define('MY_ARTICLES_DISABLE_AUTOBOOT', true);
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
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

        /**
         * @param array<int, array<string, mixed>> $posts
         */
        public function __construct(array $posts = array())
        {
            $this->posts = array_values($posts);
            $this->current_index = 0;
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
