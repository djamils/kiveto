<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain\TaxProfile;

use App\Context\Client\Domain\TaxProfile\LegalNameNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegalNameNormalizer::class)]
final class LegalNameNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideANameIsBroughtToAComparableFormCases(): iterable
    {
        yield 'accents and case' => ['Élevage des Calanques', 'elevage des calanques'];
        yield 'legal form first' => ['EARL ELEVAGE DES CALANQUES', 'elevage des calanques'];
        yield 'legal form last' => ['Centre équestre du Lac SAS', 'centre equestre du lac'];
        yield 'ampersand' => ['Le Refuge & Co', 'refuge et co'];
        yield 'article only' => ['refuge et co', 'refuge et co'];
        yield 'elided article' => ["L'Escale", 'escale'];
        yield 'typographic apostrophe' => ['L’Escale', 'escale'];
        yield 'article then legal form' => ['La Ferme SCEA', 'ferme'];
        yield 'legal form then article' => ['GAEC Les Prés', 'pres'];
        yield 'punctuation and spaces' => ['  Clinique-du   Port, (Marseille)  ', 'clinique du port marseille'];
        yield 'association' => ['Association Refuge des Collines', 'refuge des collines'];
        yield 'dotted legal form at the end' => ['Dupont S.A.R.L.', 'dupont'];
        yield 'dotted legal form at the start' => ['S.A.S. Dupont', 'dupont'];
        yield 'dotted legal form without the last dot' => ['Dupont S.A', 'dupont'];
        yield 'only one legal form' => ['SARL Dupont SA', 'dupont sa'];
        yield 'a lone legal form stays' => ['SAS', 'sas'];
        yield 'a lone article stays' => ['Les', 'les'];
        yield 'a legal form inside the name stays' => ['Ferme EARL du Mont', 'ferme earl du mont'];
    }

    #[DataProvider('provideANameIsBroughtToAComparableFormCases')]
    public function testANameIsBroughtToAComparableForm(string $name, string $expected): void
    {
        self::assertSame($expected, LegalNameNormalizer::normalize($name));
    }

    public function testTheReferencePairsMatch(): void
    {
        self::assertSame(LegalNameNormalizer::normalize('Élevage des Calanques'), LegalNameNormalizer::normalize('EARL ELEVAGE DES CALANQUES'));
        self::assertSame(LegalNameNormalizer::normalize('Le Refuge & Co'), LegalNameNormalizer::normalize('refuge et co'));
        self::assertNotSame(LegalNameNormalizer::normalize('Élevage des Calanques'), LegalNameNormalizer::normalize('Élevage du Mont'));
    }
}
