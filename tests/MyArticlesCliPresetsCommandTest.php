<?php
declare(strict_types=1);

if (!defined('WP_CLI')) {
    define('WP_CLI', true);
}

if (!class_exists('WP_CLI_Command')) {
    class WP_CLI_Command
    {
    }
}

if (!class_exists('WP_CLI')) {
    class WP_CLI
    {
        /** @var array<int, string> */
        public static array $warnings = array();

        /** @var array<int, string> */
        public static array $errors = array();

        /** @var array<int, string> */
        public static array $successes = array();

        /** @var array<int, string> */
        public static array $logs = array();

        /** @var array<int, array{0: string, 1: mixed}> */
        public static array $added_commands = array();

        public static function add_command($name, $callable): void
        {
            self::$added_commands[] = array((string) $name, $callable);
        }

        public static function warning($message): void
        {
            self::$warnings[] = (string) $message;
        }

        public static function error($message): void
        {
            self::$errors[] = (string) $message;
            throw new RuntimeException((string) $message);
        }

        public static function success($message): void
        {
            self::$successes[] = (string) $message;
        }

        public static function log($message): void
        {
            self::$logs[] = (string) $message;
        }

        public static function line($message): void
        {
            self::$logs[] = (string) $message;
        }
    }
}

if (!class_exists('My_Articles_Preset_Registry')) {
    require_once dirname(__DIR__) . '/mon-affichage-article/includes/class-my-articles-preset-registry.php';
}

if (!class_exists('My_Articles_Shortcode')) {
    require_once dirname(__DIR__) . '/mon-affichage-article/includes/class-my-articles-shortcode.php';
}

require_once dirname(__DIR__) . '/mon-affichage-article/includes/class-my-articles-cli.php';

final class MyArticlesCliPresetsCommandTest extends WP_UnitTestCase
{
    private string $tempRoot;

    private string $baseDir;

    /** @var mixed */
    private $previousRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        WP_CLI::$warnings = array();
        WP_CLI::$errors = array();
        WP_CLI::$successes = array();
        WP_CLI::$logs = array();

        $this->tempRoot = sys_get_temp_dir() . '/my-articles-cli-' . uniqid('', true);
        $this->baseDir  = $this->tempRoot . '/config/design-presets';

        mkdir($this->baseDir, 0777, true);

        $this->previousRegistry = $this->replacePresetRegistry($this->baseDir);
    }

    protected function tearDown(): void
    {
        $this->restorePresetRegistry($this->previousRegistry);
        $this->removeDirectory($this->tempRoot);

        parent::tearDown();
    }

    public function test_import_rejects_unsafe_preset_ids(): void
    {
        $source = $this->tempRoot . '/import.json';
        $payload = array(
            'presets' => array(
                array(
                    'id'     => '../../outside',
                    'label'  => 'Unsafe preset',
                    'values' => array(),
                ),
                array(
                    'id'     => '$$$',
                    'label'  => 'Invalid preset',
                    'values' => array(),
                ),
            ),
        );

        file_put_contents($source, json_encode($payload));

        $command = new My_Articles_CLI_Presets_Command();
        $command->import(array($source), array());

        $insideDir = $this->baseDir . '/outside';
        $this->assertDirectoryExists($insideDir);
        $this->assertFileExists($insideDir . '/manifest.json');

        $outsideDir = $this->tempRoot . '/outside';
        $this->assertDirectoryDoesNotExist($outsideDir);

        $createdDirectories = $this->listDirectories($this->baseDir);
        $this->assertSame(array('outside'), $createdDirectories);

        $this->assertNotEmpty(WP_CLI::$successes);
        $this->assertCount(1, WP_CLI::$warnings);
        $this->assertStringContainsString('$$$', WP_CLI::$warnings[0]);
    }

    /**
     * @param string $baseDir
     * @return mixed Previous registry instance.
     */
    private function replacePresetRegistry(string $baseDir)
    {
        $registry = new class($baseDir) extends My_Articles_Preset_Registry {
            private string $baseDir;

            public function __construct(string $baseDir)
            {
                $this->baseDir = $baseDir;
            }

            public function get_presets_dir()
            {
                return $this->baseDir;
            }
        };

        $reflection = new ReflectionProperty(My_Articles_Preset_Registry::class, 'instance');
        $reflection->setAccessible(true);
        $previous = $reflection->getValue();
        $reflection->setValue($registry);

        return $previous;
    }

    /**
     * @param mixed $previous
     */
    private function restorePresetRegistry($previous): void
    {
        $reflection = new ReflectionProperty(My_Articles_Preset_Registry::class, 'instance');
        $reflection->setAccessible(true);
        $reflection->setValue($previous);
    }

    /**
     * @param string $path
     * @return array<int, string>
     */
    private function listDirectories(string $path): array
    {
        if (!is_dir($path)) {
            return array();
        }

        $entries = array();
        $handle  = opendir($path);

        if (false === $handle) {
            return $entries;
        }

        try {
            while (false !== ($item = readdir($handle))) {
                if ('.' === $item || '..' === $item) {
                    continue;
                }

                $candidate = $path . DIRECTORY_SEPARATOR . $item;
                if (is_dir($candidate)) {
                    $entries[] = $item;
                }
            }
        } finally {
            closedir($handle);
        }

        sort($entries);

        return $entries;
    }

    private function removeDirectory(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path)) {
            @unlink($path);
            return;
        }

        $items = scandir($path);
        if (false === $items) {
            return;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $target = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($target)) {
                $this->removeDirectory($target);
            } else {
                @unlink($target);
            }
        }

        @rmdir($path);
    }
}
