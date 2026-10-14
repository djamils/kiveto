<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesCriteria;
use App\Context\Billing\Domain\Exception\InvoiceNotFoundException;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\Discount;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The write and read sides against a real database: the aggregate has to come
 * back exactly as it went in, and the list has to agree with it on every total.
 */
final class DoctrineInvoiceRepositoryTest extends KernelTestCase
{
    private const string CLINIC_ID    = '01960000-0000-7000-8000-00000000c001';
    private const string OTHER_CLINIC = '01960000-0000-7000-8000-00000000c002';
    private const string ADMISSION_ID = '01960000-0000-7000-8000-00000000c003';
    private const string CLIENT_ID    = '01960000-0000-7000-8000-00000000c004';

    private InvoiceRepositoryInterface $invoices;
    private InvoiceReadRepositoryInterface $read;
    private UuidGeneratorInterface $uuids;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();

        $invoices = $container->get(InvoiceRepositoryInterface::class);
        \assert($invoices instanceof InvoiceRepositoryInterface);
        $this->invoices = $invoices;

        $read = $container->get(InvoiceReadRepositoryInterface::class);
        \assert($read instanceof InvoiceReadRepositoryInterface);
        $this->read = $read;

        $uuids = $container->get(UuidGeneratorInterface::class);
        \assert($uuids instanceof UuidGeneratorInterface);
        $this->uuids = $uuids;
    }

    public function testAnInvoiceComesBackAsItWentIn(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $invoice->addLine($this->line(2000, 400, 2400), $this->now());
        $invoice->applyDiscount(Discount::percentage('10', 'Fidélité'), $this->now());
        $invoice->setNotes('Merci', $this->now());
        $invoice->issue(InvoiceNumber::of(2026, 77), $this->now());
        $invoice->recordPayment($this->payment(1000), $this->now());

        $this->invoices->save($invoice);

        $reloaded = $this->invoices->get($this->clinic(), $invoice->id());

        self::assertSame('FAC-2026-000077', $reloaded->number()?->toString());
        self::assertSame(InvoiceStatus::PARTIALLY_PAID, $reloaded->status());
        self::assertCount(2, $reloaded->lines());
        self::assertCount(1, $reloaded->payments());
        self::assertSame('Merci', $reloaded->notes());
        self::assertSame(700, $reloaded->discountAmount()->minorUnits());
        self::assertSame(6300, $reloaded->totalNet()->minorUnits());
        self::assertSame(1260, $reloaded->totalTax()->minorUnits());
        self::assertSame(7560, $reloaded->totalGross()->minorUnits());
        self::assertSame(6560, $reloaded->balanceDue()->minorUnits());
        self::assertSame('Sophie Dupont', $reloaded->payer()->displayName());
        self::assertSame(['12 rue des Lilas', '75011 Paris'], $reloaded->payer()->addressLines());
    }

    public function testSavingTwiceUpdatesRatherThanDuplicates(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $this->invoices->save($invoice);

        $invoice->issue(InvoiceNumber::of(2026, 78), $this->now());
        $this->invoices->save($invoice);

        $reloaded = $this->invoices->get($this->clinic(), $invoice->id());

        self::assertSame(InvoiceStatus::ISSUED, $reloaded->status());
        self::assertCount(1, $reloaded->lines());
    }

    public function testARemovedLineIsDeletedFromTheRow(): void
    {
        $invoice = $this->draft();
        $kept    = $this->line(5000, 1000, 6000);
        $dropped = $this->line(2000, 400, 2400);
        $invoice->addLine($kept, $this->now());
        $invoice->addLine($dropped, $this->now());
        $this->invoices->save($invoice);

        $invoice->removeLine($dropped->id(), $this->now());
        $this->invoices->save($invoice);

        $reloaded = $this->invoices->get($this->clinic(), $invoice->id());

        self::assertCount(1, $reloaded->lines());
        self::assertTrue($reloaded->lines()[0]->id()->equals($kept->id()));
    }

    public function testASecondSaveOnlyWritesTheNewPayment(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $invoice->issue(InvoiceNumber::of(2026, 79), $this->now());
        $invoice->recordPayment($this->payment(1000), $this->now());
        $this->invoices->save($invoice);

        $invoice->recordPayment($this->payment(2000), $this->now());
        $this->invoices->save($invoice);

        $reloaded = $this->invoices->get($this->clinic(), $invoice->id());

        // The first payment is already on file: writing it again would double
        // what the client actually handed over.
        self::assertCount(2, $reloaded->payments());
        self::assertSame(3000, $reloaded->amountPaid()->minorUnits());
    }

    public function testAnInvoiceOfAnotherClinicIsInvisible(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $this->invoices->save($invoice);

        self::assertNull($this->invoices->findById(ClinicId::fromString(self::OTHER_CLINIC), $invoice->id()));
    }

    public function testAnUnknownInvoiceIsRejected(): void
    {
        $this->expectException(InvoiceNotFoundException::class);
        $this->expectExceptionMessage('Invoice not found');

        $this->invoices->get($this->clinic(), InvoiceId::fromString($this->uuids->generate()));
    }

    public function testTheVisitFindsItsInvoice(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $this->invoices->save($invoice);

        $found = $this->invoices->findForAdmission($this->clinic(), AdmissionId::fromString(self::ADMISSION_ID));

        self::assertNotNull($found);
        self::assertTrue($found->id()->equals($invoice->id()));
    }

    public function testACancelledInvoiceNoLongerHoldsTheVisit(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $invoice->issue(InvoiceNumber::of(2026, 80), $this->now());
        $invoice->cancel('Erreur', $this->now());
        $this->invoices->save($invoice);

        self::assertNull(
            $this->invoices->findForAdmission($this->clinic(), AdmissionId::fromString(self::ADMISSION_ID)),
        );
    }

    public function testAVisitWithoutAnInvoiceFindsNothing(): void
    {
        self::assertNull(
            $this->invoices->findForAdmission($this->clinic(), AdmissionId::fromString($this->uuids->generate())),
        );
    }

    public function testTheReadSideFindsTheInvoiceOfAVisitWithItsTotal(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $invoice->issue(InvoiceNumber::of(2026, 41), $this->now());
        $this->invoices->save($invoice);

        $row = $this->read->forAdmission($this->clinic(), AdmissionId::fromString(self::ADMISSION_ID));

        self::assertNotNull($row);
        self::assertSame($invoice->id()->toString(), $row->id);
        self::assertSame('FAC-2026-000041', $row->number);
        self::assertSame('ISSUED', $row->status);
        self::assertSame(6000, $row->totalGrossMinorUnits);
        self::assertSame('EUR', $row->currency);
        self::assertTrue($row->isPrintable());
    }

    public function testTheReadSideReportsADraftAsNotPrintable(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $this->invoices->save($invoice);

        $row = $this->read->forAdmission($this->clinic(), AdmissionId::fromString(self::ADMISSION_ID));

        self::assertNotNull($row);
        self::assertNull($row->number);
        self::assertFalse($row->isPrintable());
    }

    public function testTheReadSideKeepsTheInvoiceOfAnotherClinicOut(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $this->invoices->save($invoice);

        self::assertNull($this->read->forAdmission(
            ClinicId::fromString(self::OTHER_CLINIC),
            AdmissionId::fromString(self::ADMISSION_ID),
        ));
    }

    public function testAVisitThatWasNeverInvoicedHasNoRow(): void
    {
        self::assertNull($this->read->forAdmission(
            $this->clinic(),
            AdmissionId::fromString($this->uuids->generate()),
        ));
    }

    public function testTheNumberSequenceHandsOutConsecutiveNumbers(): void
    {
        $generator = self::getContainer()->get(InvoiceNumberGeneratorInterface::class);
        \assert($generator instanceof InvoiceNumberGeneratorInterface);

        $first    = $generator->next($this->clinic(), 2026);
        $second   = $generator->next($this->clinic(), 2026);
        $other    = $generator->next(ClinicId::fromString(self::OTHER_CLINIC), 2026);
        $nextYear = $generator->next($this->clinic(), 2027);

        self::assertSame($first->sequence() + 1, $second->sequence());
        // Each clinic and each year counts on its own.
        self::assertSame(1, $other->sequence());
        self::assertSame(1, $nextYear->sequence());
        self::assertSame(2027, $nextYear->year());
    }

    public function testTheListAgreesWithTheInvoiceOnItsTotals(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $invoice->applyDiscount(Discount::percentage('10'), $this->now());
        $invoice->issue(InvoiceNumber::of(2026, 81), $this->now());
        $invoice->recordPayment($this->payment(1000), $this->now());
        $this->invoices->save($invoice);

        $result = $this->read->search($this->clinic(), new SearchInvoicesCriteria());

        self::assertSame(1, $result['total']);
        $row = $result['items'][0];
        self::assertSame($invoice->totalGross()->minorUnits(), $row->totalGrossMinorUnits);
        self::assertSame(1000, $row->amountPaidMinorUnits);
        self::assertSame($invoice->balanceDue()->minorUnits(), $row->balanceDueMinorUnits);
        self::assertSame('FAC-2026-000081', $row->number);
        self::assertSame('Sophie Dupont', $row->payerName);
        self::assertSame(self::CLIENT_ID, $row->payerClientId);
        self::assertSame(self::ADMISSION_ID, $row->admissionId);
        self::assertNotNull($row->issuedAt);
    }

    public function testTheListFiltersOnStatusTermAndPeriod(): void
    {
        $paid = $this->draft();
        $paid->addLine($this->line(5000, 1000, 6000), $this->now());
        $paid->issue(InvoiceNumber::of(2026, 90), $this->now());
        $paid->recordPayment($this->payment(6000), $this->now());
        $this->invoices->save($paid);

        $unpaid = $this->draft(admissionId: null, payerName: 'Marc Lefebvre');
        $unpaid->addLine($this->line(2000, 400, 2400), $this->now());
        $unpaid->issue(InvoiceNumber::of(2026, 91), $this->now());
        $this->invoices->save($unpaid);

        $byStatus = $this->read->search($this->clinic(), new SearchInvoicesCriteria(statuses: ['PAID']));
        self::assertSame(1, $byStatus['total']);
        self::assertSame('FAC-2026-000090', $byStatus['items'][0]->number);

        $byName = $this->read->search($this->clinic(), new SearchInvoicesCriteria(term: 'Lefebvre'));
        self::assertSame(1, $byName['total']);
        self::assertSame('FAC-2026-000091', $byName['items'][0]->number);

        $byNumber = $this->read->search($this->clinic(), new SearchInvoicesCriteria(term: '000090'));
        self::assertSame(1, $byNumber['total']);

        $byPayer = $this->read->search(
            $this->clinic(),
            new SearchInvoicesCriteria(payerClientId: self::CLIENT_ID),
        );
        self::assertSame(2, $byPayer['total']);

        $future = $this->read->search(
            $this->clinic(),
            new SearchInvoicesCriteria(issuedFrom: new \DateTimeImmutable('+1 year')),
        );
        self::assertSame(0, $future['total']);

        $upTo = $this->read->search(
            $this->clinic(),
            new SearchInvoicesCriteria(issuedTo: $this->now()->modify('+1 day')),
        );
        self::assertSame(2, $upTo['total']);
    }

    public function testAnImpossibleDateRangeSkipsTheQuery(): void
    {
        $result = $this->read->search($this->clinic(), new SearchInvoicesCriteria(
            issuedFrom: new \DateTimeImmutable('2026-12-31'),
            issuedTo: new \DateTimeImmutable('2026-01-01'),
        ));

        self::assertSame(['items' => [], 'total' => 0], $result);
    }

    public function testTheListSortsAndPaginates(): void
    {
        foreach ([1, 2, 3] as $index) {
            $invoice = $this->draft(admissionId: null, payerName: 'Client ' . $index);
            $invoice->addLine($this->line(1000 * $index, 200 * $index, 1200 * $index), $this->now());
            $invoice->issue(InvoiceNumber::of(2026, 100 + $index), $this->now());
            $this->invoices->save($invoice);
        }

        $byAmount = $this->read->search(
            $this->clinic(),
            new SearchInvoicesCriteria(sortBy: SearchInvoicesCriteria::SORT_AMOUNT, sortDescending: true),
        );
        self::assertSame(3600, $byAmount['items'][0]->totalGrossMinorUnits);

        $byPayer = $this->read->search($this->clinic(), new SearchInvoicesCriteria(
            sortBy: SearchInvoicesCriteria::SORT_PAYER,
            sortDescending: false,
        ));
        self::assertSame('Client 1', $byPayer['items'][0]->payerName);

        $page2 = $this->read->search($this->clinic(), new SearchInvoicesCriteria(perPage: 2, page: 2));
        self::assertCount(1, $page2['items']);
        self::assertSame(3, $page2['total']);

        // An unknown sort falls back on the date rather than failing.
        $fallback = $this->read->search($this->clinic(), new SearchInvoicesCriteria(sortBy: 'colour'));
        self::assertSame(3, $fallback['total']);
    }

    public function testTheDetailsMirrorTheAggregate(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $invoice->applyDiscount(Discount::percentage('10', 'Fidélité'), $this->now());
        $invoice->issue(InvoiceNumber::of(2026, 110), $this->now());
        $invoice->recordPayment($this->payment(1000), $this->now());
        $this->invoices->save($invoice);

        $details = $this->read->details($this->clinic(), $invoice->id());

        self::assertSame('FAC-2026-000110', $details->number);
        self::assertSame('Partielle', $details->statusLabel);
        self::assertCount(1, $details->lines);
        self::assertCount(1, $details->payments);
        self::assertSame('Carte bancaire', $details->payments[0]->methodLabel);
        self::assertCount(1, $details->taxBreakdown);
        self::assertSame('20', $details->taxBreakdown[0]['ratePercent']);
        self::assertSame('PERCENTAGE', $details->discountKind);
        self::assertSame('Fidélité', $details->discountReason);
        self::assertSame(500, $details->discountMinorUnits);
        self::assertSame(5400, $details->totalGrossMinorUnits);
        self::assertSame(4400, $details->balanceDueMinorUnits);
        self::assertSame('CONS_STD', $details->lines[0]->code);
        self::assertSame('CONSULTATION', $details->lines[0]->source);
        self::assertSame(self::ADMISSION_ID, $details->admissionId);
    }

    public function testTheStatsCountWhatWasTakenAndWhatIsOwed(): void
    {
        $paid = $this->draft();
        $paid->addLine($this->line(5000, 1000, 6000), $this->now());
        $paid->issue(InvoiceNumber::of(2026, 120), $this->now());
        $paid->recordPayment($this->payment(6000), $this->now());
        $this->invoices->save($paid);

        $partly = $this->draft(admissionId: null);
        $partly->addLine($this->line(2000, 400, 2400), $this->now());
        $partly->issue(InvoiceNumber::of(2026, 121), $this->now());
        $partly->recordPayment($this->payment(400), $this->now());
        $this->invoices->save($partly);

        $draft = $this->draft(admissionId: null);
        $draft->addLine($this->line(9000, 1800, 10800), $this->now());
        $this->invoices->save($draft);

        $stats = $this->read->stats(
            $this->clinic(),
            $this->now()->modify('-1 day'),
            $this->now()->modify('+1 day'),
        );

        self::assertSame(6400, $stats->collectedMinorUnits);
        self::assertSame(2, $stats->issuedCount);
        // Only the partly paid one still owes something; the draft is not issued.
        self::assertSame(2000, $stats->outstandingMinorUnits);
        self::assertSame(1, $stats->outstandingCount);
        self::assertSame('EUR', $stats->currency);
    }

    public function testStatsOnADiscountedInvoiceUseTheDiscountedTotal(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $invoice->applyDiscount(Discount::percentage('50'), $this->now());
        $invoice->issue(InvoiceNumber::of(2026, 130), $this->now());
        $this->invoices->save($invoice);

        $stats = $this->read->stats(
            $this->clinic(),
            $this->now()->modify('-1 day'),
            $this->now()->modify('+1 day'),
        );

        self::assertSame($invoice->totalGross()->minorUnits(), $stats->outstandingMinorUnits);
    }

    public function testEachPayerOwesWhatTheirOpenInvoicesStillCarry(): void
    {
        $partly = $this->draft(admissionId: null);
        $partly->addLine($this->line(2000, 400, 2400), $this->now());
        $partly->issue(InvoiceNumber::of(2026, 140), $this->now());
        $partly->recordPayment($this->payment(400), $this->now());
        $this->invoices->save($partly);

        $issued = $this->draft(admissionId: null);
        $issued->addLine($this->line(1000, 200, 1200), $this->now());
        $issued->issue(InvoiceNumber::of(2026, 141), $this->now());
        $this->invoices->save($issued);

        // Settled and draft invoices owe nothing.
        $paid = $this->draft(admissionId: null);
        $paid->addLine($this->line(5000, 1000, 6000), $this->now());
        $paid->issue(InvoiceNumber::of(2026, 142), $this->now());
        $paid->recordPayment($this->payment(6000), $this->now());
        $this->invoices->save($paid);

        $draft = $this->draft(admissionId: null);
        $draft->addLine($this->line(9000, 1800, 10800), $this->now());
        $this->invoices->save($draft);

        self::assertSame([self::CLIENT_ID => 3200], $this->read->outstandingByPayer($this->clinic()));
        self::assertSame([], $this->read->outstandingByPayer(ClinicId::fromString(self::OTHER_CLINIC)));
    }

    private function draft(
        ?string $admissionId = self::ADMISSION_ID,
        string $payerName = 'Sophie Dupont',
    ): Invoice {
        return Invoice::draft(
            id: InvoiceId::fromString($this->uuids->generate()),
            clinicId: $this->clinic(),
            admissionId: null !== $admissionId ? AdmissionId::fromString($admissionId) : null,
            payer: Payer::create(
                PayerId::fromString(self::CLIENT_ID),
                $payerName,
                ['12 rue des Lilas', '75011 Paris'],
            ),
            currency: CurrencyCode::fromString('EUR'),
            now: $this->now(),
        );
    }

    private function line(int $net, int $tax, int $gross): InvoiceLine
    {
        $eur = CurrencyCode::fromString('EUR');

        return InvoiceLine::create(
            id: InvoiceLineId::fromString($this->uuids->generate()),
            label: 'Consultation standard',
            code: 'CONS_STD',
            quantity: '1',
            unitNetPrice: Money::fromMinorUnits($net, $eur),
            taxCategoryCode: 'veterinary.act.consultation',
            taxRatePercent: '20',
            netAmount: Money::fromMinorUnits($net, $eur),
            taxAmount: Money::fromMinorUnits($tax, $eur),
            grossAmount: Money::fromMinorUnits($gross, $eur),
            source: InvoiceLineSource::CONSULTATION,
            sourceReference: 'src-1',
        );
    }

    private function payment(int $amount): Payment
    {
        return Payment::create(
            id: PaymentId::fromString($this->uuids->generate()),
            method: PaymentMethod::CARD,
            amount: Money::fromMinorUnits($amount, CurrencyCode::fromString('EUR')),
            receivedAt: $this->now(),
            reference: 'TX-1',
        );
    }

    private function clinic(): ClinicId
    {
        return ClinicId::fromString(self::CLINIC_ID);
    }

    /**
     * The clock of these tests. Windows are asked for around it rather than
     * around the real time: a fixed instant and a moving window drift apart
     * within a day, and the suite starts failing on its own.
     */
    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-22 10:00:00');
    }
}
