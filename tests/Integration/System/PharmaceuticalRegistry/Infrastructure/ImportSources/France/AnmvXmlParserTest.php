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
        self::assertSame('FR/H/0001/001/DC/001', $dtos[0]->authorityIdentifier);
        self::assertSame('Apoquel 3,6 mg comprimés', $dtos[0]->commercialName);
        self::assertSame(1, $dtos[0]->statusCode);
    }

    public function testFirstProductHasResolvedLabels(): void
    {
        $loader = new AnmvDictionaryLoader();
        $dico   = $loader->load(self::FIXTURE_DIR . 'dictionary_sample.xml');

        $parser = new AnmvXmlParser();
        $dtos   = iterator_to_array($parser->parse(self::FIXTURE_DIR . 'anmv_sample.xml', $dico));

        $first = $dtos[0];
        self::assertSame('Zoetis Belgium SA', $first->holderLaboratoryLabel);
        self::assertSame('Comprimé', $first->pharmaceuticalFormLabel);
        self::assertSame('QD11AH91', $first->atcVetCode);
        self::assertSame('600000123456', $first->permanentIdentifier);
    }

    public function testFirstProductComposition(): void
    {
        $loader = new AnmvDictionaryLoader();
        $dico   = $loader->load(self::FIXTURE_DIR . 'dictionary_sample.xml');

        $parser = new AnmvXmlParser();
        $dtos   = iterator_to_array($parser->parse(self::FIXTURE_DIR . 'anmv_sample.xml', $dico));

        $compos = $dtos[0]->compositions;
        self::assertCount(1, $compos);
        self::assertSame('oclacitinib', $compos[0]->activeSubstanceLabel);
        self::assertSame('3.6', $compos[0]->quantityValue);
        self::assertSame('mg', $compos[0]->quantityUnitLabel);
        self::assertFalse($compos[0]->isExcipient);
    }

    public function testFirstProductPresentation(): void
    {
        $loader = new AnmvDictionaryLoader();
        $dico   = $loader->load(self::FIXTURE_DIR . 'dictionary_sample.xml');

        $parser = new AnmvXmlParser();
        $dtos   = iterator_to_array($parser->parse(self::FIXTURE_DIR . 'anmv_sample.xml', $dico));

        $presentations = $dtos[0]->presentations;
        self::assertCount(1, $presentations);
        self::assertSame('Plaquette de 20 comprimés', $presentations[0]->description);
        self::assertSame('03700942019756', $presentations[0]->gtin);
        self::assertSame('aaaa1111-0000-0000-0000-000000000001', $presentations[0]->euPackIdentifier);
        self::assertSame(1, $presentations[0]->prescriptionCode);
    }

    public function testProductWithMultipleVoiesAdministration(): void
    {
        $loader = new AnmvDictionaryLoader();
        $dico   = $loader->load(self::FIXTURE_DIR . 'dictionary_sample.xml');

        $parser = new AnmvXmlParser();
        $dtos   = iterator_to_array($parser->parse(self::FIXTURE_DIR . 'anmv_sample.xml', $dico));

        // Second product (Cerenia) has 2 voies-admin
        $voies = $dtos[1]->voiesAdministration;
        self::assertCount(2, $voies);
        self::assertSame(24, $voies[0]->routeCode);
        self::assertSame(22, $voies[0]->speciesCode);
        self::assertNull($voies[0]->foodProductionCode);
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

    public function testProductWithoutAtcVetCodeHasNullAtcVetCode(): void
    {
        $loader = new AnmvDictionaryLoader();
        $dico   = $loader->load(self::FIXTURE_DIR . 'dictionary_sample.xml');

        $parser = new AnmvXmlParser();
        $dtos   = iterator_to_array($parser->parse(self::FIXTURE_DIR . 'anmv_sample.xml', $dico));

        // Product 8 (Cystaid Plus) has no atcvet-code element
        self::assertNull($dtos[7]->atcVetCode);
    }

    public function testLastProductIsAbandoned(): void
    {
        $loader = new AnmvDictionaryLoader();
        $dico   = $loader->load(self::FIXTURE_DIR . 'dictionary_sample.xml');

        $parser = new AnmvXmlParser();
        $dtos   = iterator_to_array($parser->parse(self::FIXTURE_DIR . 'anmv_sample.xml', $dico));

        // Product 10 (Rilexine) has term-stat-auto=5 (ABANDONED)
        self::assertSame(5, $dtos[9]->statusCode);
    }
}
