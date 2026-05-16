<?php

declare(strict_types=1);

namespace Tests\Integration\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France;

use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\AnmvDictionaryLoader;
use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\AnmvXmlParser;
use PHPUnit\Framework\TestCase;

final class AnmvXmlParserTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/../../../../../../fixtures/System/PharmaceuticalRegistry/';

    public function testParsesAllTenProducts(): void
    {
        $loader = new AnmvDictionaryLoader();
        $dico   = $loader->load(self::FIXTURE_DIR . 'dictionary_sample.xml');

        $parser = new AnmvXmlParser();
        $dtos   = iterator_to_array($parser->parse(self::FIXTURE_DIR . 'anmv_sample.xml', $dico));

        self::assertCount(10, $dtos);
        self::assertSame('FR/H/0001', $dtos[0]->authorityIdentifier);
        self::assertSame('Apoquel 3,6 mg comprimés', $dtos[0]->commercialName);
        self::assertSame(2, $dtos[0]->statusCode);
    }

    public function testEachDtoHasContentHash(): void
    {
        $loader = new AnmvDictionaryLoader();
        $dico   = $loader->load(self::FIXTURE_DIR . 'dictionary_sample.xml');

        $parser = new AnmvXmlParser();

        foreach ($parser->parse(self::FIXTURE_DIR . 'anmv_sample.xml', $dico) as $dto) {
            self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $dto->contentHash->toString());
        }
    }
}
