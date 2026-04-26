<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Search\Normalization;

use App\Shared\Search\Normalization\SearchTermNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SearchTermNormalizerTest extends TestCase
{
    private SearchTermNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new SearchTermNormalizer();
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function provideNormalizeTextCases(): iterable
    {
        return [
            ['rémi', 'remi'],
            ['RÉMI', 'remi'],
            ['Rémi', 'remi'],
            ['RéMi', 'remi'],
            ['é è ê ë', 'e e e e'],
            ['à â', 'a a'],
            ['ô', 'o'],
            ['ù ü', 'u u'],
            ['ç', 'c'],
            ['García', 'garcia'],
            ['José', 'jose'],
            ['Ñoño', 'nono'],
            ['Müller', 'muller'],
            ['Öffner', 'offner'],
            ['Straße', 'strasse'],
            ['Jean-Pierre', 'jean pierre'],
            ["O'Brien", 'o brien'],
            ['Saint-Étienne', 'saint etienne'],
            ['  Marie    Curie  ', 'marie curie'],
        ];
    }

    #[DataProvider('provideNormalizeTextCases')]
    public function testNormalizeText(string $input, string $expected): void
    {
        self::assertSame($expected, $this->normalizer->normalizeText($input));
    }

    /**
     * @return list<array{0: string, 1: bool}>
     */
    public static function provideIsChipNumberCases(): iterable
    {
        return [
            ['250269802120045', true],
            ['25026980212004', false],
            ['2502698021200456', false],
            ['25026980212004X', false],
            ['000000000000000', true],
        ];
    }

    #[DataProvider('provideIsChipNumberCases')]
    public function testIsChipNumber(string $input, bool $expected): void
    {
        self::assertSame($expected, $this->normalizer->isChipNumber($input));
    }

    /**
     * @return list<array{0: string, 1: bool}>
     */
    public static function provideIsPhoneCases(): iterable
    {
        return [
            ['+33612345678', true],
            ['06 12 34 56 78', true],
            ['0612345678', true],
            ['123', false],
        ];
    }

    #[DataProvider('provideIsPhoneCases')]
    public function testIsPhone(string $input, bool $expected): void
    {
        self::assertSame($expected, $this->normalizer->isPhone($input));
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function provideNormalizePhoneCases(): iterable
    {
        return [
            ['0612345678', '+33612345678'],
            ['06.12.34.56.78', '+33612345678'],
            ['+33612345678', '+33612345678'],
        ];
    }

    #[DataProvider('provideNormalizePhoneCases')]
    public function testNormalizePhone(string $input, string $expected): void
    {
        self::assertSame($expected, $this->normalizer->normalizePhone($input));
    }

    /**
     * @return list<array{0: string, 1: bool}>
     */
    public static function provideIsExecutableCases(): iterable
    {
        return [
            ['ab', true],
            ['a', false],
            ['  a  ', false],
        ];
    }

    #[DataProvider('provideIsExecutableCases')]
    public function testIsExecutable(string $input, bool $expected): void
    {
        self::assertSame($expected, $this->normalizer->isExecutable($input));
    }
}
