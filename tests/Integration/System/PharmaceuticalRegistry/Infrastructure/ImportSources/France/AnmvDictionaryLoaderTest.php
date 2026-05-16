<?php

declare(strict_types=1);

namespace Tests\Integration\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France;

use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\AnmvDictionaryLoader;
use PHPUnit\Framework\TestCase;

final class AnmvDictionaryLoaderTest extends TestCase
{
    private const FIXTURE_PATH = __DIR__
        . '/../../../../../../fixtures/System/PharmaceuticalRegistry/dictionary_sample.xml';

    public function testLoadsParsesValidDictionary(): void
    {
        $loader = new AnmvDictionaryLoader();
        $cache  = $loader->load(self::FIXTURE_PATH);

        self::assertSame('oclacitinib', $cache->getLabel('term-sa', 1));
        self::assertSame('Chien', $cache->getLabel('term-esp', 22));
        self::assertSame('Orale', $cache->getLabel('term-va', 19));
        self::assertSame('mg', $cache->getLabel('term-unite', 1));
    }

    public function testHasLabelReturnsTrueForKnownEntry(): void
    {
        $loader = new AnmvDictionaryLoader();
        $cache  = $loader->load(self::FIXTURE_PATH);

        self::assertTrue($cache->hasLabel('term-tit', 1));
        self::assertFalse($cache->hasLabel('term-tit', 9999));
        self::assertFalse($cache->hasLabel('nonexistent-section', 1));
    }
}
