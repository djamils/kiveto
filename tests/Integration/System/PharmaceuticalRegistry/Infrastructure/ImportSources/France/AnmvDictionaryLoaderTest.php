<?php

declare(strict_types=1);

namespace Tests\Integration\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France;

use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\AnmvDictionaryLoader;
use PHPUnit\Framework\TestCase;

final class AnmvDictionaryLoaderTest extends TestCase
{
    private const FIXTURE_PATH = __DIR__ . '/../../../../../../fixtures/System/PharmaceuticalRegistry/dictionary_sample.xml';

    public function testLoadsParsesValidDictionary(): void
    {
        $loader = new AnmvDictionaryLoader();
        $cache  = $loader->load(self::FIXTURE_PATH);

        self::assertSame('oclacitinib', $cache->getActiveSubstanceLabel(1));
        self::assertSame('Chien', $cache->getTargetSpeciesLabel(7));
        self::assertSame('Orale', $cache->getAdministrationRouteLabel(14));
        self::assertSame('Jours', $cache->getWithdrawalUnitLabel(1));
    }
}
