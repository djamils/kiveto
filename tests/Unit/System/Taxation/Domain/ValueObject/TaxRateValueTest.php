<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Domain\ValueObject;

use App\System\Taxation\Domain\Exception\InvalidTaxRateValueException;
use App\System\Taxation\Domain\ValueObject\TaxRateValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxRateValueTest extends TestCase
{
    #[DataProvider('provideOfBasisPointsValidCases')]
    public function testOfBasisPointsValid(int $bp): void
    {
        $value = TaxRateValue::ofBasisPoints($bp);

        self::assertSame($bp, $value->basisPoints());
    }

    /** @return iterable<string, array{int}> */
    public static function provideOfBasisPointsValidCases(): iterable
    {
        yield 'zero' => [0];
        yield '2000' => [2000];
        yield '10000' => [10000];
    }

    public function testOfBasisPointsNegativeThrows(): void
    {
        $this->expectException(InvalidTaxRateValueException::class);

        TaxRateValue::ofBasisPoints(-1);
    }

    public function testOfBasisPointsTooLargeThrows(): void
    {
        $this->expectException(InvalidTaxRateValueException::class);

        TaxRateValue::ofBasisPoints(10001);
    }

    public function testOfPercentString20dot0(): void
    {
        self::assertSame(2000, TaxRateValue::ofPercentString('20.0')->basisPoints());
    }

    public function testOfPercentString5dot5(): void
    {
        self::assertSame(550, TaxRateValue::ofPercentString('5.5')->basisPoints());
    }

    public function testOfPercentString2dot1(): void
    {
        self::assertSame(210, TaxRateValue::ofPercentString('2.1')->basisPoints());
    }

    public function testOfPercentString0dot1(): void
    {
        self::assertSame(10, TaxRateValue::ofPercentString('0.1')->basisPoints());
    }

    public function testOfPercentString0(): void
    {
        self::assertSame(0, TaxRateValue::ofPercentString('0')->basisPoints());
    }

    public function testOfPercentString100(): void
    {
        self::assertSame(10000, TaxRateValue::ofPercentString('100')->basisPoints());
    }

    public function testOfPercentString3decimalsThrows(): void
    {
        $this->expectException(InvalidTaxRateValueException::class);

        TaxRateValue::ofPercentString('5.555');
    }

    public function testOfPercentStringNegativeThrows(): void
    {
        $this->expectException(InvalidTaxRateValueException::class);

        TaxRateValue::ofPercentString('-5');
    }

    public function testOfPercentStringEmptyThrows(): void
    {
        $this->expectException(InvalidTaxRateValueException::class);

        TaxRateValue::ofPercentString('');
    }

    public function testOfPercentString100dot01Throws(): void
    {
        $this->expectException(InvalidTaxRateValueException::class);

        TaxRateValue::ofPercentString('100.01');
    }

    #[DataProvider('provideToPercentStringCases')]
    public function testToPercentString(int $bp, string $expected): void
    {
        self::assertSame($expected, TaxRateValue::ofBasisPoints($bp)->toPercentString());
    }

    /** @return iterable<string, array{int, string}> */
    public static function provideToPercentStringCases(): iterable
    {
        yield '2000 → 20.00' => [2000, '20.00'];
        yield '550 → 5.50' => [550, '5.50'];
        yield '210 → 2.10' => [210, '2.10'];
        yield '0 → 0.00' => [0, '0.00'];
    }

    #[DataProvider('provideToBcmathFactorCases')]
    public function testToBcmathFactor(int $bp, string $expected): void
    {
        self::assertSame($expected, TaxRateValue::ofBasisPoints($bp)->toBcmathFactor());
    }

    /** @return iterable<string, array{int, string}> */
    public static function provideToBcmathFactorCases(): iterable
    {
        yield '2000 → 0.2000' => [2000, '0.2000'];
        yield '550 → 0.0550' => [550, '0.0550'];
    }

    public function testEqualsReturnsTrueForSameBasisPoints(): void
    {
        self::assertTrue(TaxRateValue::ofBasisPoints(2000)->equals(TaxRateValue::ofBasisPoints(2000)));
    }

    public function testEqualsReturnsFalseForDifferentBasisPoints(): void
    {
        self::assertFalse(TaxRateValue::ofBasisPoints(2000)->equals(TaxRateValue::ofBasisPoints(550)));
    }
}
