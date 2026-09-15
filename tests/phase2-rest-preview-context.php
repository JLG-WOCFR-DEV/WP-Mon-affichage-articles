<?php
/**
 * REST render-preview must be treated as an editor canvas request.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

if (!defined('REST_REQUEST')) {
    define('REST_REQUEST', true);
}

if (!defined('MY_ARTICLES_VERSION')) {
    define('MY_ARTICLES_VERSION', 'tests');
}

if (!defined('MY_ARTICLES_PLUGIN_URL')) {
    define('MY_ARTICLES_PLUGIN_URL', 'http://example.com/wp-content/plugins/mon-affichage-articles/');
}

if (!defined('MY_ARTICLES_PLUGIN_DIR')) {
    define('MY_ARTICLES_PLUGIN_DIR', dirname(__DIR__) . '/mon-affichage-article/');
}

$_SERVER['REQUEST_URI'] = '/wp-json/my-articles/v1/render-preview';

require_once dirname(__DIR__) . '/mon-affichage-article/includes/class-my-articles-enqueue.php';

if (!My_Articles_Enqueue::is_block_editor_preview_context()) {
    fwrite(STDERR, "REST render-preview must be treated as editor preview.\n");
    exit(1);
}

if (My_Articles_Enqueue::should_enqueue_frontend_script()) {
    fwrite(STDERR, "Interactive front scripts must not enqueue during REST preview.\n");
    exit(1);
}

echo "phase2 rest preview context passed\n";
exit(0);
