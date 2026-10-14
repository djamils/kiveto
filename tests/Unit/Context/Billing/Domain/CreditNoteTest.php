<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain;

use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Event\CreditNoteIssued;
use App\Context\Billing\Domain\Exception\CurrencyMismatchException;
use App\Context\Billing\Domain\Exception\InvalidCreditNoteException;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class CreditNoteTest extends TestCase
{
    private const string ID        = '01960000-0000-7000-8000-00000000c101';
    private const string CLINIC_ID = '01960000-0000-7000-8000-00000000c102';
    private const string INVOICE   = '01960000-0000-7000-8000-00000000c103';
    private const string LINE      = '01960000-0000-7000-8000-00000000c104';
    private const string CREDITED  = '01960000-0000-7000-8000-00000000c105';
    private const string USER      = '01960000-0000-7000-8000-00000000c106';
    private const string CLIENT    = '01960000-0000-7000-8000-00000000c107';

    public function testACreditNoteCarriesItsLinesAndWhatItGaveBack(): void
    {
        $note = $this->issue(refunded: 1200, method: PaymentMethod::CARD);

        self::assertSame(self::ID, $note->id()->toString());
        self::assertSame(self::CLINIC_ID, $note->clinicId()->toString());
        self::assertSame('AV-2026-000003', $note->number()->toString());
        self::assertSame(self::INVOICE, $note->invoiceId()->toString());
        self::assertSame('FAC-2026-000040', $note->invoiceNumber());
        self::assertSame('Sophie Dupont', $note->payer()->displayName());
        self::assertCount(1, $note->lines());
        self::assertSame('Retour produit', $note->reason());
        self::assertSame(PaymentMethod::CARD, $note->refundMethod());
        self::assertSame(1200, $note->refunded()->minorUnits());
        self::assertSame('EUR', $note->currency()->toString());
        self::assertSame('2026-10-14', $note->issuedAt()->format('Y-m-d'));
        self::assertSame(self::USER, $note->issuedByUserId());
        self::assertSame(1000, $note->totalNet()->minorUnits());
        self::assertSame(200, $note->totalTax()->minorUnits());
        self::assertSame(1200, $note->totalGross()->minorUnits());
        self::assertSame([self::CREDITED], $note->creditedLineIds());

        $event = $note->recordedDomainEvents()[0];
        self::assertInstanceOf(CreditNoteIssued::class, $event);
        self::assertSame('AV-2026-000003', $event->number);
        self::assertSame(1200, $event->refundedMinorUnits);
    }

    public function testNothingGivenBackNeedsNoMeans(): void
    {
        $note = $this->issue(refunded: 0, method: PaymentMethod::CASH);

        self::assertNull($note->refundMethod());
    }

    public function testAReasonIsRequired(): void
    {
        $this->expectException(InvalidCreditNoteException::class);

        $this->issue(reason: '   ');
    }

    public function testALineIsRequired(): void
    {
        $this->expectException(InvalidCreditNoteException::class);

        $this->issue(lines: []);
    }

    public function testLinesAreInTheCreditNotesCurrency(): void
    {
        $this->expectException(CurrencyMismatchException::class);

        $this->issue(lines: [$this->line('USD')]);
    }

    public function testTheRefundIsInTheCreditNotesCurrency(): void
    {
        $this->expectException(CurrencyMismatchException::class);

        $this->issue(refundCurrency: 'USD');
    }

    public function testItCannotGiveBackMoreThanItCredits(): void
    {
        $this->expectException(InvalidCreditNoteException::class);

        $this->issue(refunded: 1201, method: PaymentMethod::CARD);
    }

    public function testARefundNeedsAMeans(): void
    {
        $this->expectException(InvalidCreditNoteException::class);

        $this->issue(refunded: 100, method: null);
    }

    public function testItComesBackAsStored(): void
    {
        $note = CreditNote::reconstitute(
            CreditNoteId::fromString(self::ID),
            ClinicId::fromString(self::CLINIC_ID),
            InvoiceNumber::fromString('AV-2026-000001'),
            InvoiceId::fromString(self::INVOICE),
            'FAC-2026-000040',
            Payer::anonymous(),
            [$this->line('EUR', null)],
            'Geste commercial',
            null,
            Money::fromMinorUnits(0, CurrencyCode::fromString('EUR')),
            CurrencyCode::fromString('EUR'),
            new \DateTimeImmutable('2026-10-01'),
            null,
        );

        self::assertSame([], $note->recordedDomainEvents());
        self::assertSame([], $note->creditedLineIds());
        self::assertNull($note->issuedByUserId());
    }

    /**
     * @param list<InvoiceLine>|null $lines
     */
    private function issue(
        int $refunded = 0,
        ?PaymentMethod $method = null,
        string $reason = 'Retour produit',
        ?array $lines = null,
        string $refundCurrency = 'EUR',
    ): CreditNote {
        return CreditNote::issue(
            id: CreditNoteId::fromString(self::ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            number: InvoiceNumber::of(2026, 3, 'AV'),
            invoiceId: InvoiceId::fromString(self::INVOICE),
            invoiceNumber: 'FAC-2026-000040',
            payer: Payer::create(PayerId::fromString(self::CLIENT), 'Sophie Dupont'),
            lines: $lines ?? [$this->line()],
            reason: $reason,
            refundMethod: $method,
            refunded: Money::fromMinorUnits($refunded, CurrencyCode::fromString($refundCurrency)),
            currency: CurrencyCode::fromString('EUR'),
            now: new \DateTimeImmutable('2026-10-14 11:00:00'),
            issuedByUserId: self::USER,
        );
    }

    private function line(string $currency = 'EUR', ?string $credited = self::CREDITED): InvoiceLine
    {
        $money = static fn (int $amount): Money => Money::fromMinorUnits($amount, CurrencyCode::fromString($currency));

        return InvoiceLine::create(
            id: InvoiceLineId::fromString(self::LINE),
            label: 'Frontline Combo Chien pipettes',
            code: 'FRONTLINE-CB',
            quantity: '1',
            unitNetPrice: $money(1000),
            taxCategoryCode: 'veterinary.drug.companion',
            taxRatePercent: '20',
            netAmount: $money(1000),
            taxAmount: $money(200),
            grossAmount: $money(1200),
            source: InvoiceLineSource::CREDIT,
            sourceReference: $credited,
        );
    }
}
