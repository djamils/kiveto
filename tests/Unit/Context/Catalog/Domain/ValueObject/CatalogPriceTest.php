<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Domain\ValueObject;

use App\Context\Catalog\Domain\Exception\InvalidPriceEntryModeException;
use App\Context\Catalog\Domain\ValueObject\CatalogPrice;
use App\Context\Catalog\Domain\ValueObject\PriceEntryMode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class CatalogPriceTest extends TestCase
{
    public function testAPriceKeepsTheAmountAndTheModeItWasEnteredIn(): void
    {
        $price = CatalogPrice::fromPrimitives(4500, 'EUR', 'incl');

        self::assertSame(4500, $price->minorUnits());
        self::assertSame(4500, $price->amount()->minorUnits());
        self::assertSame('EUR', $price->currency()->toString());
        self::assertSame(PriceEntryMode::INCLUDING_TAX, $price->mode());
        self::assertSame(PriceEntryMode::EXCLUDING_TAX, CatalogPrice::fromPrimitives(3750, 'EUR', 'excl')->mode());
    }

    public function testTheNamedFactoriesSayTheMode(): void
    {
        self::assertSame(PriceEntryMode::INCLUDING_TAX, CatalogPrice::includingTax($this->eur(4500))->mode());
        self::assertSame(PriceEntryMode::EXCLUDING_TAX, CatalogPrice::excludingTax($this->eur(4500))->mode());
        self::assertSame(PriceEntryMode::EXCLUDING_TAX, CatalogPrice::of($this->eur(4500), PriceEntryMode::EXCLUDING_TAX)->mode());
    }

    public function testAnotherAmountKeepsTheMode(): void
    {
        $adjusted = CatalogPrice::excludingTax($this->eur(4500))->withAmount($this->eur(4000));

        self::assertSame(4000, $adjusted->minorUnits());
        self::assertSame(PriceEntryMode::EXCLUDING_TAX, $adjusted->mode());
    }

    public function testTwoPricesAreEqualOnlyWithTheSameAmountAndMode(): void
    {
        $price = CatalogPrice::includingTax($this->eur(4500));

        self::assertTrue($price->equals(CatalogPrice::includingTax($this->eur(4500))));
        self::assertFalse($price->equals(CatalogPrice::excludingTax($this->eur(4500))));
        self::assertFalse($price->equals(CatalogPrice::includingTax($this->eur(4501))));
    }

    public function testAnUnknownModeIsRefused(): void
    {
        $this->expectException(InvalidPriceEntryModeException::class);
        $this->expectExceptionMessage('Invalid price entry mode "ttc": expected "incl" or "excl".');

        CatalogPrice::fromPrimitives(4500, 'EUR', 'ttc');
    }

    private function eur(int $minorUnits): Money
    {
        return Money::fromMinorUnits($minorUnits, CurrencyCode::fromString('EUR'));
    }
}
