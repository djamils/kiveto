<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Application\Command;

use App\Context\Billing\Application\Command\PrepareInvoiceForAdmission\PrepareInvoiceForAdmission;
use App\Context\Billing\Application\Command\PrepareInvoiceForAdmission\PrepareInvoiceForAdmissionHandler;
use App\Context\Billing\Application\Port\BillableLineDto;
use App\Context\Billing\Application\Port\ConsultationBillingProviderInterface;
use App\Context\Billing\Application\Port\PayerDirectoryInterface;
use App\Context\Billing\Application\Port\PayerSnapshotDto;
use App\Context\Billing\Application\Port\ResolvedTaxDto;
use App\Context\Billing\Application\Port\TaxResolverInterface;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Several stubs here only feed the handler its inputs; the assertions are on
 * the invoice that comes out, not on how many times each port was asked.
 */
#[AllowMockObjectsWithoutExpectations]
final class PrepareInvoiceForAdmissionHandlerTest extends TestCase
{
    private const string CLINIC_ID    = '01960000-0000-7000-8000-000000000201';
    private const string ADMISSION_ID = '01960000-0000-7000-8000-000000000202';
    private const string CLIENT_ID    = '01960000-0000-7000-8000-000000000203';
    private const string INVOICE_ID   = '01960000-0000-7000-8000-000000000204';

    private InvoiceRepositoryInterface&MockObject $invoices;
    private ConsultationBillingProviderInterface&MockObject $consultationBilling;
    private TaxResolverInterface&MockObject $taxResolver;
    private PayerDirectoryInterface&MockObject $payerDirectory;
    private UuidGeneratorInterface&MockObject $uuidGenerator;
    private ClockInterface&MockObject $clock;
    private PrepareInvoiceForAdmissionHandler $handler;

    protected function setUp(): void
    {
        $this->invoices            = $this->createMock(InvoiceRepositoryInterface::class);
        $this->consultationBilling = $this->createMock(ConsultationBillingProviderInterface::class);
        $this->taxResolver         = $this->createMock(TaxResolverInterface::class);
        $this->payerDirectory      = $this->createMock(PayerDirectoryInterface::class);
        $this->uuidGenerator       = $this->createMock(UuidGeneratorInterface::class);
        $this->clock               = $this->createMock(ClockInterface::class);

        $this->handler = new PrepareInvoiceForAdmissionHandler(
            $this->invoices,
            $this->consultationBilling,
            $this->taxResolver,
            $this->payerDirectory,
            $this->uuidGenerator,
            $this->clock,
        );
    }

    public function testItSnapshotsTheVisitIntoADraft(): void
    {
        $this->invoices->expects(self::once())->method('findForAdmission')->willReturn(null);
        $this->clock->method('now')->willReturn($this->now());
        $this->uuidGenerator->method('generate')->willReturnOnConsecutiveCalls(
            self::INVOICE_ID,
            '01960000-0000-7000-8000-000000000301',
            '01960000-0000-7000-8000-000000000302',
        );

        $this->consultationBilling->expects(self::once())
            ->method('billableLinesForAdmission')
            ->with(self::ADMISSION_ID, self::CLINIC_ID)
            ->willReturn([
                $this->billableLine('Consultation standard', 'CONS_STD', '1', 5000),
                $this->billableLine('Radiographie', 'RADIO', '2', 7000),
            ])
        ;

        $this->payerDirectory->expects(self::once())
            ->method('forAdmission')
            ->willReturn(new PayerSnapshotDto(self::CLIENT_ID, 'Sophie Dupont', ['12 rue des Lilas']))
        ;

        // Net comes from quantity × unit price; the taxation port turns it into
        // net + tax, which is what the line freezes.
        $this->taxResolver->expects(self::exactly(2))
            ->method('resolve')
            ->willReturnCallback(static fn (int $net): ResolvedTaxDto => new ResolvedTaxDto(
                $net,
                (int) round($net * 0.2),
                $net + (int) round($net * 0.2),
                '20',
            ))
        ;

        $saved = null;
        $this->invoices->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (Invoice $invoice) use (&$saved): void {
                $saved = $invoice;
            })
        ;

        $id = ($this->handler)(new PrepareInvoiceForAdmission(self::CLINIC_ID, self::ADMISSION_ID));

        self::assertSame(self::INVOICE_ID, $id);
        self::assertInstanceOf(Invoice::class, $saved);
        self::assertSame(InvoiceStatus::DRAFT, $saved->status());
        self::assertCount(2, $saved->lines());
        self::assertSame('Sophie Dupont', $saved->payer()->displayName());
        self::assertSame(['12 rue des Lilas'], $saved->payer()->addressLines());
        // 5000 + 14000 net, plus 20 % of each
        self::assertSame(19000, $saved->totalNet()->minorUnits());
        self::assertSame(3800, $saved->totalTax()->minorUnits());
        self::assertSame('src-CONS_STD', $saved->lines()[0]->sourceReference());
    }

    public function testAnInvoiceAlreadyIssuedIsReturnedUntouched(): void
    {
        $existing = $this->issuedInvoice();

        $this->invoices->expects(self::once())->method('findForAdmission')->willReturn($existing);
        $this->invoices->expects(self::never())->method('save');
        $this->consultationBilling->expects(self::never())->method('billableLinesForAdmission');

        $id = ($this->handler)(new PrepareInvoiceForAdmission(self::CLINIC_ID, self::ADMISSION_ID));

        self::assertSame(self::INVOICE_ID, $id);
    }

    public function testAnExistingDraftIsRebuiltUnderItsOwnId(): void
    {
        $existing = Invoice::draft(
            id: InvoiceId::fromString(self::INVOICE_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            admissionId: AdmissionId::fromString(self::ADMISSION_ID),
            payer: Payer::anonymous(),
            currency: CurrencyCode::fromString('EUR'),
            now: $this->now(),
        );

        $this->invoices->expects(self::once())->method('findForAdmission')->willReturn($existing);
        $this->clock->method('now')->willReturn($this->now());
        $this->uuidGenerator->method('generate')->willReturn('01960000-0000-7000-8000-000000000301');
        $this->consultationBilling->method('billableLinesForAdmission')->willReturn([
            $this->billableLine('Consultation', 'CONS', '1', 4000),
        ]);
        $this->payerDirectory->method('forAdmission')->willReturn(null);
        $this->taxResolver->method('resolve')->willReturn(new ResolvedTaxDto(4000, 800, 4800, '20'));

        $saved = null;
        $this->invoices->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (Invoice $invoice) use (&$saved): void {
                $saved = $invoice;
            })
        ;

        $id = ($this->handler)(new PrepareInvoiceForAdmission(self::CLINIC_ID, self::ADMISSION_ID));

        self::assertSame(self::INVOICE_ID, $id);
        self::assertInstanceOf(Invoice::class, $saved);
        // No owner on file: the invoice still has to name someone.
        self::assertSame('Client de passage', $saved->payer()->displayName());
        self::assertCount(1, $saved->lines());
    }

    public function testAVisitWithNothingToChargeStillGetsADraftInEuros(): void
    {
        $this->invoices->method('findForAdmission')->willReturn(null);
        $this->clock->method('now')->willReturn($this->now());
        $this->uuidGenerator->method('generate')->willReturn(self::INVOICE_ID);
        $this->consultationBilling->method('billableLinesForAdmission')->willReturn([]);
        $this->payerDirectory->method('forAdmission')->willReturn(null);
        $this->taxResolver->expects(self::never())->method('resolve');

        $saved = null;
        $this->invoices->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (Invoice $invoice) use (&$saved): void {
                $saved = $invoice;
            })
        ;

        ($this->handler)(new PrepareInvoiceForAdmission(self::CLINIC_ID, self::ADMISSION_ID));

        self::assertInstanceOf(Invoice::class, $saved);
        self::assertSame([], $saved->lines());
        self::assertSame('EUR', $saved->currency()->toString());
    }

    public function testTheCurrencyComesFromTheFirstLineThatNamesOne(): void
    {
        $this->invoices->method('findForAdmission')->willReturn(null);
        $this->clock->method('now')->willReturn($this->now());
        $this->uuidGenerator->method('generate')->willReturn(self::INVOICE_ID);
        $this->payerDirectory->method('forAdmission')->willReturn(null);
        $this->taxResolver->method('resolve')->willReturn(new ResolvedTaxDto(1000, 0, 1000, '0'));
        $this->consultationBilling->method('billableLinesForAdmission')->willReturn([
            new BillableLineDto('c', 'l', 'Sans devise', null, '1', 1000, '', null),
            new BillableLineDto('c', 'l2', 'En francs', null, '1', 1000, 'CHF', null),
        ]);

        $saved = null;
        $this->invoices->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (Invoice $invoice) use (&$saved): void {
                $saved = $invoice;
            })
        ;

        ($this->handler)(new PrepareInvoiceForAdmission(self::CLINIC_ID, self::ADMISSION_ID));

        self::assertInstanceOf(Invoice::class, $saved);
        self::assertSame('CHF', $saved->currency()->toString());
    }

    public function testADraftRereadsTheStructuresCurrentNameUntilIssued(): void
    {
        $existing = Invoice::draft(
            id: InvoiceId::fromString(self::INVOICE_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            admissionId: AdmissionId::fromString(self::ADMISSION_ID),
            payer: Payer::create(null, 'Elevage des Calanqes', [], 'Mme Claire Rostand'),
            currency: CurrencyCode::fromString('EUR'),
            now: $this->now(),
        );

        $this->invoices->method('findForAdmission')->willReturn($existing);
        $this->clock->method('now')->willReturn($this->now());
        $this->uuidGenerator->method('generate')->willReturn('01960000-0000-7000-8000-000000000302');
        $this->consultationBilling->method('billableLinesForAdmission')->willReturn([]);
        $this->payerDirectory->method('forAdmission')
            ->willReturn(new PayerSnapshotDto(self::CLIENT_ID, 'Élevage des Calanques', [], 'Mme Claire Rostand'))
        ;

        $saved = null;
        $this->invoices->method('save')->willReturnCallback(static function (Invoice $invoice) use (&$saved): void {
            $saved = $invoice;
        });

        ($this->handler)(new PrepareInvoiceForAdmission(self::CLINIC_ID, self::ADMISSION_ID));

        self::assertInstanceOf(Invoice::class, $saved);
        self::assertSame('Élevage des Calanques', $saved->payer()->displayName());
        self::assertSame('Mme Claire Rostand', $saved->payer()->attention());
    }

    private function billableLine(string $label, string $code, string $quantity, int $unitPrice): BillableLineDto
    {
        return new BillableLineDto(
            consultationId: '01960000-0000-7000-8000-000000000401',
            sourceLineId: 'src-' . $code,
            label: $label,
            code: $code,
            quantity: $quantity,
            unitPriceMinorUnits: $unitPrice,
            currency: 'EUR',
            taxCategoryCode: 'veterinary.act.consultation',
        );
    }

    private function issuedInvoice(): Invoice
    {
        return Invoice::reconstitute(
            id: InvoiceId::fromString(self::INVOICE_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            number: InvoiceNumber::of(2026, 1),
            status: InvoiceStatus::ISSUED,
            admissionId: AdmissionId::fromString(self::ADMISSION_ID),
            payer: Payer::anonymous(),
            lines: [],
            payments: [],
            discount: null,
            currency: CurrencyCode::fromString('EUR'),
            notes: null,
            createdAt: $this->now(),
            updatedAt: $this->now(),
            issuedAt: $this->now(),
            paidAt: null,
            cancelledAt: null,
            cancellationReason: null,
        );
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-22 10:00:00');
    }
}
