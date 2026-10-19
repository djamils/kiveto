<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain\ValueObject;

use App\Context\Billing\Domain\Exception\InvalidInvoiceLineException;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\LineAmounts;
use App\Context\Billing\Domain\ValueObject\OperationCategory;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\Shared\Money\Domain\ValueObject\UnitPrice;
use PHPUnit\Framework\TestCase;

final class InvoiceLineTest extends TestCase
{
    private const string LINE_ID = '01960000-0000-7000-8000-0000000000f4';

    public function testItKeepsWhatWasEnteredAndStartsWithoutAmounts(): void
    {
        $line = $this->line();

        self::assertSame(self::LINE_ID, $line->id()->toString());
        self::assertSame('Consultation standard', $line->label());
        self::assertSame('CONS_STD', $line->code());
        self::assertSame('2', $line->quantity());
        self::assertSame(6000, $line->enteredUnitPrice()->minorUnits());
        self::assertSame(PriceMode::INCLUDING_TAX, $line->priceMode());
        self::assertSame('veterinary.act.consultation', $line->taxCategoryCode());
        self::assertSame('20', $line->taxRatePercent());
        self::assertSame(OperationCategory::SERVICES, $line->operationCategory());
        self::assertSame(InvoiceLineSource::CONSULTATION, $line->source());
        self::assertSame('src-1', $line->sourceReference());
        self::assertSame('EUR', $line->currency()->toString());
        self::assertTrue($line->netAmount()->isZero());
        self::assertTrue($line->grossAmount()->isZero());
        self::assertSame(0, $line->unitNet()->microUnits());
    }

    public function testItCarriesTheAmountsItsDocumentCalculated(): void
    {
        $line = $this->line()->withAmounts($this->amounts(10000, 12000));

        self::assertSame(10000, $line->netAmount()->minorUnits());
        self::assertSame(12000, $line->grossAmount()->minorUnits());
        self::assertSame(50_000_000, $line->unitNet()->microUnits());
        self::assertTrue($line->amounts()->equals($this->amounts(10000, 12000)));
        self::assertSame(6000, $line->enteredUnitPrice()->minorUnits());
        self::assertSame(self::LINE_ID, $line->id()->toString());
    }

    public function testItGivesTheCalculatorWhatWasEntered(): void
    {
        $input = $this->line()->calculationLine();

        self::assertSame(6000, $input->enteredUnitPrice->minorUnits());
        self::assertSame(PriceMode::INCLUDING_TAX, $input->priceMode);
        self::assertSame('2', $input->quantity);
        self::assertSame('20', $input->ratePercent);
    }

    public function testItIsReconstitutedWithItsStoredAmounts(): void
    {
        $eur  = CurrencyCode::fromString('EUR');
        $line = InvoiceLine::reconstitute(
            id: InvoiceLineId::fromString(self::LINE_ID),
            label: 'Vaccin',
            code: null,
            quantity: '1',
            enteredUnitPrice: Money::fromMinorUnits(5000, $eur),
            priceMode: PriceMode::EXCLUDING_TAX,
            taxCategoryCode: null,
            taxRatePercent: '0',
            operationCategory: null,
            source: InvoiceLineSource::MANUAL,
            sourceReference: null,
            amounts: $this->amounts(5000, 5000),
        );

        self::assertNull($line->operationCategory());
        self::assertSame(5000, $line->netAmount()->minorUnits());
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

    public function testAmountsInAnotherCurrencyAreRejected(): void
    {
        $usd = CurrencyCode::fromString('USD');

        $this->expectException(InvalidInvoiceLineException::class);
        $this->expectExceptionMessage('must share one currency');

        $this->line()->withAmounts(LineAmounts::of(
            Money::fromMinorUnits(1, $usd),
            Money::fromMinorUnits(1, $usd),
            UnitPrice::fromMicroUnits(1, $usd),
        ));
    }

    public function testAMalformedTaxRateIsRejected(): void
    {
        $this->expectException(InvalidInvoiceLineException::class);
        $this->expectExceptionMessage('Invalid tax rate "vingt"');

        $this->line(rate: 'vingt');
    }

    private function amounts(int $net, int $gross): LineAmounts
    {
        $eur = CurrencyCode::fromString('EUR');

        return LineAmounts::of(
            Money::fromMinorUnits($net, $eur),
            Money::fromMinorUnits($gross, $eur),
            UnitPrice::fromMicroUnits(50_000_000, $eur),
        );
    }

    private function line(
        string $label = 'Consultation standard',
        ?string $code = 'CONS_STD',
        string $quantity = '2',
        ?string $taxCategoryCode = 'veterinary.act.consultation',
        string $rate = '20',
        ?string $sourceReference = 'src-1',
    ): InvoiceLine {
        return InvoiceLine::create(
            id: InvoiceLineId::fromString(self::LINE_ID),
            label: $label,
            code: $code,
            quantity: $quantity,
            enteredUnitPrice: Money::fromMinorUnits(6000, CurrencyCode::fromString('EUR')),
            priceMode: PriceMode::INCLUDING_TAX,
            taxCategoryCode: $taxCategoryCode,
            taxRatePercent: $rate,
            operationCategory: OperationCategory::SERVICES,
            source: InvoiceLineSource::CONSULTATION,
            sourceReference: $sourceReference,
        );
    }
}
