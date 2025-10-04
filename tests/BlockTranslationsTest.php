<?php

declare(strict_types=1);

namespace MonAffichageArticles\Tests;

use PHPUnit\Framework\TestCase;

final class BlockTranslationsTest extends TestCase
{
    public function testFrenchJsonTranslationsContainColorLabel(): void
    {
        $pluginDir = realpath(__DIR__ . '/../mon-affichage-article');
        $this->assertIsString($pluginDir);

        $translationsPath = $pluginDir . '/languages/mon-articles-fr_FR-d38246567e142318f24686bcaa0ee4b1.json';
        $this->assertFileExists($translationsPath);

        $contents = file_get_contents($translationsPath);
        $this->assertNotFalse($contents, 'Unable to read translations JSON file.');

        $decoded = json_decode($contents, true);
        $this->assertIsArray($decoded, 'Invalid JSON structure for translations file.');

        $this->assertArrayHasKey('locale_data', $decoded);
        $this->assertArrayHasKey('mon-articles', $decoded['locale_data']);
        $this->assertArrayHasKey('Couleurs', $decoded['locale_data']['mon-articles']);
        $this->assertSame(
            'Couleurs',
            $decoded['locale_data']['mon-articles']['Couleurs'][0] ?? null,
            'The expected translation for "Couleurs" is missing.'
        );
    }
}
