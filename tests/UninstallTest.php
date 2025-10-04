<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UninstallTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $mon_articles_test_options_store, $mon_articles_test_options, $mon_articles_test_cache_store, $mon_articles_test_transients_store, $wp_object_cache, $wpdb;

        $mon_articles_test_options_store   = array();
        $mon_articles_test_options         = array();
        $mon_articles_test_cache_store     = array();
        $mon_articles_test_transients_store = array();
        $wp_object_cache                   = null;
        $wpdb                              = null;
    }

    public function test_uninstall_removes_options_and_cache_and_transients(): void
    {
        global $mon_articles_test_cache_store, $mon_articles_test_options_store, $wp_object_cache, $wpdb;

        update_option('my_articles_options', array('color' => 'blue'));
        update_option('my_articles_cache_namespace', 'namespace123');
        update_option('_transient_my_articles_namespace123_aabb', array('cached' => true));
        update_option('_transient_timeout_my_articles_namespace123_aabb', time() + 60);

        wp_cache_set('my_articles_namespace123_foo', array('cached' => true), 'my_articles_response');
        wp_cache_set('my_articles_namespace123_bar', array('cached' => true), 'my_articles_response');

        $wp_object_cache = new class
        {
            /** @var array<string, array<string, mixed>> */
            public array $cache = array();

            public function delete_group(string $group): void
            {
                if (isset($this->cache[$group]) && is_array($this->cache[$group])) {
                    foreach (array_keys($this->cache[$group]) as $key) {
                        unset($this->cache[$group][$key]);
                    }
                }
            }
        };

        $wp_object_cache->cache =& $mon_articles_test_cache_store;

        $wpdb = new class
        {
            public string $options = 'wp_options';
            public string $sitemeta = 'wp_sitemeta';

            /** @var array<int, string> */
            public array $queries = array();

            public function esc_like(string $text): string
            {
                return addcslashes($text, "_%\\");
            }

            public function prepare(string $query, string ...$args): string
            {
                $quoted = array();

                foreach ($args as $arg) {
                    $quoted[] = "'" . addslashes($arg) . "'";
                }

                return vsprintf($query, $quoted);
            }

            public function query(string $query): bool
            {
                global $mon_articles_test_options_store;

                $this->queries[] = $query;

                if (preg_match_all("/LIKE '([^']+)'/", $query, $matches)) {
                    foreach ($matches[1] as $pattern) {
                        $regex = $this->convert_like_to_regex($pattern);

                        foreach (array_keys($mon_articles_test_options_store) as $option_name) {
                            if (preg_match($regex, $option_name)) {
                                unset($mon_articles_test_options_store[$option_name]);
                            }
                        }
                    }
                }

                return true;
            }

            private function convert_like_to_regex(string $pattern): string
            {
                $pattern = str_replace(array('\\%', '\\_'), array('%', '_'), $pattern);

                $regex = '';
                $length = strlen($pattern);

                for ($i = 0; $i < $length; $i++) {
                    $character = $pattern[$i];

                    if ('%' === $character) {
                        $regex .= '.*';
                        continue;
                    }

                    if ('_' === $character) {
                        $regex .= '.';
                        continue;
                    }

                    $regex .= preg_quote($character, '/');
                }

                return '/^' . $regex . '$/';
            }
        };

        require __DIR__ . '/../mon-affichage-article/uninstall.php';

        $this->assertArrayNotHasKey('my_articles_options', $mon_articles_test_options_store);
        $this->assertArrayNotHasKey('my_articles_cache_namespace', $mon_articles_test_options_store);
        $this->assertArrayNotHasKey('_transient_my_articles_namespace123_aabb', $mon_articles_test_options_store);
        $this->assertArrayNotHasKey('_transient_timeout_my_articles_namespace123_aabb', $mon_articles_test_options_store);
        $this->assertArrayNotHasKey('my_articles_response', $mon_articles_test_cache_store);
    }
}
