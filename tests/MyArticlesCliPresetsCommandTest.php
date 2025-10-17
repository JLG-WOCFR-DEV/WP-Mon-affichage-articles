<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!function_exists('trailingslashit')) {
    function trailingslashit($string)
    {
        $string = (string) $string;

        if ('' === $string) {
            return '/';
        }

        return rtrim($string, "\\/") . '/';
    }
}

if (!defined('WP_CLI')) {
    define('WP_CLI', true);
}

if (!class_exists('WP_CLI_Command')) {
    abstract class WP_CLI_Command
    {
    }
}

if (!class_exists('WP_CLI')) {
    class WP_CLI
    {
        /** @var array<int, string> */
        public static array $warnings = array();

        public static function reset(): void
        {
            self::$warnings = array();
        }

        public static function warning($message): void
        {
            self::$warnings[] = (string) $message;
        }

        public static function success($message): void
        {
            // No-op for tests.
        }

        public static function error($message): void
        {
            throw new RuntimeException((string) $message);
        }

        public static function log($message): void
        {
            // No-op for tests.
        }

        public static function line($message): void
        {
            // No-op for tests.
        }

        public static function add_command($name, $callable): void
        {
            // No-op for tests.
        }
    }
}

if (!class_exists('My_Articles_CLI_Presets_Command')) {
    require_once __DIR__ . '/../mon-affichage-article/includes/class-my-articles-cli.php';
}

if (!class_exists('My_Articles_Preset_Registry')) {
    require_once __DIR__ . '/../mon-affichage-article/includes/class-my-articles-preset-registry.php';
}

if (!class_exists('My_Articles_Shortcode')) {
    require_once __DIR__ . '/../mon-affichage-article/includes/class-my-articles-shortcode.php';
}

final class MyArticlesCliPresetsCommandTest extends TestCase
{
    /** @var string */
    private $tempRoot;

    /** @var string */
    private $presetsDir;

    /** @var ReflectionProperty */
    private $registryProperty;

    /** @var My_Articles_Preset_Registry|null */
    private $originalRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        WP_CLI::reset();

        $this->tempRoot = sys_get_temp_dir() . '/my-articles-cli-' . uniqid('', true);
        $this->presetsDir = $this->tempRoot . '/config/design-presets';

        if (!is_dir($this->presetsDir) && !mkdir($concurrentDirectory = $this->presetsDir, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Unable to create temporary presets directory.');
        }

        $this->registryProperty = new ReflectionProperty(My_Articles_Preset_Registry::class, 'instance');
        $this->registryProperty->setAccessible(true);
        /** @var My_Articles_Preset_Registry|null $original */
        $original = $this->registryProperty->getValue();
        $this->originalRegistry = $original instanceof My_Articles_Preset_Registry ? $original : null;

        $override = new class($this->presetsDir) extends My_Articles_Preset_Registry {
            /** @var string */
            private $overrideDir;

            public function __construct($overrideDir)
            {
                $this->overrideDir = (string) $overrideDir;
            }

            public function get_presets_dir()
            {
                return $this->overrideDir;
            }
        };

        $this->registryProperty->setValue($override);

        My_Articles_Shortcode::flush_design_presets_cache();
    }

    protected function tearDown(): void
    {
        $this->registryProperty->setValue($this->originalRegistry);
        My_Articles_Shortcode::flush_design_presets_cache();

        $this->removeDirectory($this->tempRoot);

        parent::tearDown();
    }

    public function test_import_rejects_unsafe_preset_identifiers(): void
    {
        $payload = array(
            'version' => '1.0.0',
            'presets' => array(
                array(
                    'id'     => '../../outside',
                    'label'  => 'Outside',
                    'values' => array(),
                ),
                array(
                    'id'     => '..',
                    'label'  => 'Dots',
                    'values' => array(),
                ),
                array(
                    'id'     => 'safe-id',
                    'label'  => 'Safe',
                    'values' => array(),
                ),
            ),
        );

        $sourceFile = $this->tempRoot . '/payload.json';
        file_put_contents($sourceFile, wp_json_encode($payload));

        $command = new My_Articles_CLI_Presets_Command();
        $command->import(array($sourceFile), array());

        $outsideDir = $this->tempRoot . '/outside';
        $this->assertFalse(is_dir($outsideDir), 'Preset import should not create directories outside of the presets base path.');

        $safeDir = $this->presetsDir . '/safe-id';
        $this->assertTrue(is_dir($safeDir), 'Safe presets should still be imported successfully.');
        $this->assertFileExists($safeDir . '/manifest.json');

        $skippedManifest = $this->presetsDir . '/manifest.json';
        $this->assertFileDoesNotExist($skippedManifest, 'Unsafe preset identifiers should be skipped entirely.');

        $warnings = WP_CLI::$warnings;
        $this->assertNotEmpty($warnings, 'CLI should emit a warning for unsafe preset identifiers.');
        $this->assertStringContainsString('..', implode(' ', $warnings));
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if (false === $items) {
            return;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
