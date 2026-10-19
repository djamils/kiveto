<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain\Service;

use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Service\CreditNoteCalculator;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\Discount;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\OperationCategory;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Context\Billing\Domain\ValueObject\RateBucket;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class CreditNoteCalculatorTest extends TestCase
{
    private int $sequence = 0;

    public function testAFullCreditMirrorsTheInvoicePerRate(): void
    {
        $invoice = $this->invoice(Discount::percentage('10'));
        $credit  = $this->credit($invoice, $invoice->lines());

        self::assertSame($this->rows($invoice->amounts()->buckets()), $this->rows($credit['breakdown']));
        self::assertCount(4, $credit['lines']);

        $line = $credit['lines'][0];
        self::assertSame(InvoiceLineSource::CREDIT, $line->source());
        self::assertSame($invoice->lines()[0]->id()->toString(), $line->sourceReference());
        self::assertTrue($line->amounts()->equals($invoice->lines()[0]->amounts()));
        self::assertSame(PriceMode::INCLUDING_TAX, $line->priceMode());
        self::assertSame(OperationCategory::SERVICES, $line->operationCategory());
    }

    public function testTwoPartialCreditNotesAddUpExactlyToADiscountedInvoice(): void
    {
        $invoice = $this->invoice(Discount::percentage('10'));
        $lines   = $invoice->lines();

        // First note: one line of each rate.
        $first     = $this->credit($invoice, [$lines[0], $lines[2]]);
        $firstNote = $this->note($invoice, $first);

        // Second note: the rest — it takes exactly what is left.
        $second = $this->credit($invoice, [$lines[1], $lines[3]], [$firstNote]);

        foreach ($invoice->amounts()->buckets() as $i => $bucket) {
            $a = $first['breakdown'][$i];
            $b = $second['breakdown'][$i];

            self::assertSame($bucket->ratePercent(), $a->ratePercent());
            self::assertSame($bucket->lineTotal()->minorUnits(), $a->lineTotal()->minorUnits() + $b->lineTotal()->minorUnits());
            self::assertSame($bucket->allowance()->minorUnits(), $a->allowance()->minorUnits() + $b->allowance()->minorUnits());
            self::assertSame($bucket->base()->minorUnits(), $a->base()->minorUnits() + $b->base()->minorUnits());
            self::assertSame($bucket->vat()->minorUnits(), $a->vat()->minorUnits() + $b->vat()->minorUnits());
        }
    }

    public function testAPartialNoteTakesItsShareOfTheDiscountAndOfWhatWasPaid(): void
    {
        $invoice = $this->invoice(Discount::percentage('10'));
        $credit  = $this->credit($invoice, [$invoice->lines()[0]]);

        // 45.00 + 30.00 € at 20 % → TTC 75.00, discounted 67.50: the 45.00 €
        // line weighs 60 % of it → 40.50 € given back. Its net 37.50 € takes
        // 60 % of the 6.25 € allowance (3.75 €) → base 33.75 €, VAT 6.75 €.
        $bucket = $credit['breakdown'][0];
        self::assertCount(1, $credit['breakdown']);
        self::assertSame(3750, $bucket->lineTotal()->minorUnits());
        self::assertSame(375, $bucket->allowance()->minorUnits());
        self::assertSame(3375, $bucket->base()->minorUnits());
        self::assertSame(4050, $bucket->gross()->minorUnits());
    }

    /**
     * @param list<RateBucket> $buckets
     *
     * @return list<array{string, int, int, int}>
     */
    private function rows(array $buckets): array
    {
        return array_map(
            static fn (RateBucket $b): array => [$b->ratePercent(), $b->lineTotal()->minorUnits(), $b->allowance()->minorUnits(), $b->vat()->minorUnits()],
            $buckets,
        );
    }

    /**
     * @param list<InvoiceLine> $selected
     * @param list<CreditNote>  $previous
     *
     * @return array{lines: list<InvoiceLine>, breakdown: list<RateBucket>}
     */
    private function credit(Invoice $invoice, array $selected, array $previous = []): array
    {
        return new CreditNoteCalculator()->credit($invoice, $selected, $previous, fn (): InvoiceLineId => $this->nextId());
    }

    /**
     * @param array{lines: list<InvoiceLine>, breakdown: list<RateBucket>} $credit
     */
    private function note(Invoice $invoice, array $credit): CreditNote
    {
        return CreditNote::issue(
            id: CreditNoteId::fromString('01960000-0000-7000-8000-00000000e0f0'),
            clinicId: $invoice->clinicId(),
            number: InvoiceNumber::of(2026, 1, 'AV'),
            invoiceId: $invoice->id(),
            invoiceNumber: (string) $invoice->number()?->toString(),
            payer: $invoice->payer(),
            lines: $credit['lines'],
            breakdown: $credit['breakdown'],
            reason: 'Retour',
            refundMethod: null,
            refunded: Money::zero($invoice->currency()),
            currency: $invoice->currency(),
            now: new \DateTimeImmutable('2026-10-15'),
        );
    }

    /**
     * Two acts at 20 % and two medicines at 5.5 %, entered VAT included.
     */
    private function invoice(Discount $discount): Invoice
    {
        $now     = new \DateTimeImmutable('2026-10-14');
        $invoice = Invoice::draft(
            InvoiceId::fromString('01960000-0000-7000-8000-00000000e001'),
            ClinicId::fromString('01960000-0000-7000-8000-00000000e002'),
            null,
            Payer::anonymous(),
            CurrencyCode::fromString('EUR'),
            $now,
        );

        foreach ([[4500, '20', OperationCategory::SERVICES], [3000, '20', OperationCategory::SERVICES], [1290, '5.5', OperationCategory::GOODS], [735, '5.5', OperationCategory::GOODS]] as [$price, $rate, $nature]) {
            $invoice->addLine(InvoiceLine::create(
                id: $this->nextId(),
                label: 'Ligne',
                code: null,
                quantity: '1',
                enteredUnitPrice: Money::fromMinorUnits($price, CurrencyCode::fromString('EUR')),
                priceMode: PriceMode::INCLUDING_TAX,
                taxCategoryCode: null,
                taxRatePercent: $rate,
                operationCategory: $nature,
                source: InvoiceLineSource::MANUAL,
            ), $now);
        }

        $invoice->applyDiscount($discount, $now);
        $invoice->issue(InvoiceNumber::of(2026, 7), $now);

        return $invoice;
    }

    private function nextId(): InvoiceLineId
    {
        return InvoiceLineId::fromString(\sprintf('01960000-0000-7000-8000-%012d', ++$this->sequence));
    }
}
