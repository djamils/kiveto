<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain\ValueObject;

use App\Context\Billing\Domain\Exception\InvalidInvoiceLineException;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class InvoiceLineTest extends TestCase
{
    private const string LINE_ID = '01960000-0000-7000-8000-0000000000f4';

    public function testItFreezesEveryAmountOfTheLine(): void
    {
        $line = $this->line();

        self::assertSame(self::LINE_ID, $line->id()->toString());
        self::assertSame('Consultation standard', $line->label());
        self::assertSame('CONS_STD', $line->code());
        self::assertSame('2', $line->quantity());
        self::assertSame(5000, $line->unitNetPrice()->minorUnits());
        self::assertSame('veterinary.act.consultation', $line->taxCategoryCode());
        self::assertSame('20', $line->taxRatePercent());
        self::assertSame(10000, $line->netAmount()->minorUnits());
        self::assertSame(2000, $line->taxAmount()->minorUnits());
        self::assertSame(12000, $line->grossAmount()->minorUnits());
        self::assertSame(InvoiceLineSource::CONSULTATION, $line->source());
        self::assertSame('src-1', $line->sourceReference());
        self::assertSame('EUR', $line->currency()->toString());
    }

    public function testBlankOptionalFieldsBecomeNull(): void
    {
        $line = $this->line(code: '  ', taxCategoryCode: '  ', sourceReference: '  ');

        self::assertNull($line->code());
        self::assertNull($line->taxCategoryCode());
        self::assertNull($line->sourceReference());
    }

    public function testAnEmptyLabelIsRejected(): void
    {
        $this->expectException(InvalidInvoiceLineException::class);
        $this->expectExceptionMessage('An invoice line needs a label.');

        $this->line(label: '   ');
    }

    public function testAMalformedQuantityIsRejected(): void
    {
        $this->expectException(InvalidInvoiceLineException::class);
        $this->expectExceptionMessage('expected a decimal with at most 2 places');

        $this->line(quantity: '1.234');
    }

    public function testAZeroQuantityIsRejected(): void
    {
        $this->expectException(InvalidInvoiceLineException::class);
        $this->expectExceptionMessage('cannot have a zero quantity');

        $this->line(quantity: '0.00');
    }

    public function testMixedCurrenciesAreRejected(): void
    {
        $eur = CurrencyCode::fromString('EUR');
        $usd = CurrencyCode::fromString('USD');

        $this->expectException(InvalidInvoiceLineException::class);
        $this->expectExceptionMessage('must share one currency');

        InvoiceLine::create(
            id: InvoiceLineId::fromString(self::LINE_ID),
            label: 'Consultation',
            code: null,
            quantity: '1',
            unitNetPrice: Money::fromMinorUnits(5000, $eur),
            taxCategoryCode: null,
            taxRatePercent: '20',
            netAmount: Money::fromMinorUnits(5000, $eur),
            taxAmount: Money::fromMinorUnits(1000, $usd),
            grossAmount: Money::fromMinorUnits(6000, $eur),
            source: InvoiceLineSource::MANUAL,
        );
    }

    public function testAmountsThatDoNotAddUpAreRejected(): void
    {
        $this->expectException(InvalidInvoiceLineException::class);
        $this->expectExceptionMessage('net (10000) + tax (2000) != gross (99999)');

        $this->line(gross: 99999);
    }

    public function testAMalformedTaxRateIsRejected(): void
    {
        $this->expectException(InvalidInvoiceLineException::class);
        $this->expectExceptionMessage('Invalid tax rate "vingt"');

        $this->line(rate: 'vingt');
    }

    private function line(
        string $label = 'Consultation standard',
        ?string $code = 'CONS_STD',
        string $quantity = '2',
        ?string $taxCategoryCode = 'veterinary.act.consultation',
        string $rate = '20',
        int $gross = 12000,
        ?string $sourceReference = 'src-1',
    ): InvoiceLine {
        $eur = CurrencyCode::fromString('EUR');

        return InvoiceLine::create(
            id: InvoiceLineId::fromString(self::LINE_ID),
            label: $label,
            code: $code,
            quantity: $quantity,
            unitNetPrice: Money::fromMinorUnits(5000, $eur),
            taxCategoryCode: $taxCategoryCode,
            taxRatePercent: $rate,
            netAmount: Money::fromMinorUnits(10000, $eur),
            taxAmount: Money::fromMinorUnits(2000, $eur),
            grossAmount: Money::fromMinorUnits($gross, $eur),
            source: InvoiceLineSource::CONSULTATION,
            sourceReference: $sourceReference,
        );
    }
}
