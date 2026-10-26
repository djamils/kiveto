<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Billing\Application\Port\CashDeskReadRepositoryInterface;
use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListRow;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesCriteria;
use App\Context\Billing\Domain\CashClosing;
use App\Context\Billing\Domain\ChequeDeposit;
use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Exception\CreditNoteNotFoundException;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\CashClosingRepositoryInterface;
use App\Context\Billing\Domain\Repository\ChequeDepositRepositoryInterface;
use App\Context\Billing\Domain\Repository\CreditNoteRepositoryInterface;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\CashClosingId;
use App\Context\Billing\Domain\ValueObject\ChequeDepositId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use App\Context\Billing\Domain\ValueObject\LineAmounts;
use App\Context\Billing\Domain\ValueObject\OperationCategory;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Context\Billing\Domain\ValueObject\RateBucket;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\Shared\Money\Domain\ValueObject\UnitPrice;
use App\Tests\Support\Billing\SellerIdentities;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The register's storage against a real database: credit notes beside the
 * invoices, the balances they leave, the cheques in the drawer and the
 * closed days.
 */
final class BillingRegisterRepositoryTest extends KernelTestCase
{
    private const string CLINIC_ID    = '01960000-0000-7000-8000-00000000c201';
    private const string OTHER_CLINIC = '01960000-0000-7000-8000-00000000c202';
    private const string ADMISSION_ID = '01960000-0000-7000-8000-00000000c203';
    private const string CLIENT_ID    = '01960000-0000-7000-8000-00000000c204';
    private const string USER_ID      = '01960000-0000-7000-8000-00000000c205';

    private InvoiceRepositoryInterface $invoices;
    private CreditNoteRepositoryInterface $creditNotes;
    private InvoiceReadRepositoryInterface $read;
    private CashDeskReadRepositoryInterface $desk;
    private ChequeDepositRepositoryInterface $deposits;
    private CashClosingRepositoryInterface $closings;
    private InvoiceNumberGeneratorInterface $numbers;
    private UuidGeneratorInterface $uuids;

    protected function setUp(): void
    {
        parent::setUp();

        $c = self::getContainer();

        $this->invoices    = self::service($c->get(InvoiceRepositoryInterface::class), InvoiceRepositoryInterface::class);
        $this->creditNotes = self::service($c->get(CreditNoteRepositoryInterface::class), CreditNoteRepositoryInterface::class);
        $this->read        = self::service($c->get(InvoiceReadRepositoryInterface::class), InvoiceReadRepositoryInterface::class);
        $this->desk        = self::service($c->get(CashDeskReadRepositoryInterface::class), CashDeskReadRepositoryInterface::class);
        $this->deposits    = self::service($c->get(ChequeDepositRepositoryInterface::class), ChequeDepositRepositoryInterface::class);
        $this->closings    = self::service($c->get(CashClosingRepositoryInterface::class), CashClosingRepositoryInterface::class);
        $this->numbers     = self::service($c->get(InvoiceNumberGeneratorInterface::class), InvoiceNumberGeneratorInterface::class);
        $this->uuids       = self::service($c->get(UuidGeneratorInterface::class), UuidGeneratorInterface::class);
    }

    public function testCreditNotesRunInASeriesOfTheirOwn(): void
    {
        $clinic = $this->clinic();

        $first   = $this->numbers->nextCreditNote($clinic, 2026);
        $invoice = $this->numbers->next($clinic, 2026);
        $second  = $this->numbers->nextCreditNote($clinic, 2026);

        self::assertSame('AV-2026-000001', $first->toString());
        self::assertSame(1, $invoice->sequence());
        self::assertSame('AV-2026-000002', $second->toString());
    }

    public function testACreditNoteComesBackAsItWentIn(): void
    {
        $invoice = $this->issued(1);
        $note    = $this->creditNote($invoice, 1200, 'Retour produit', PaymentMethod::CARD, 1200);

        $this->creditNotes->save($note);
        // Saving again does not write it twice.
        $this->creditNotes->save($note);

        $back = $this->creditNotes->get($this->clinic(), $note->id());

        self::assertSame('AV-2026-000009', $back->number()->toString());
        self::assertSame('FAC-2026-000001', $back->invoiceNumber());
        self::assertSame('Sophie Dupont', $back->payer()->displayName());
        self::assertSame(self::CLIENT_ID, $back->payer()->clientId()?->toString());
        self::assertSame(PaymentMethod::CARD, $back->refundMethod());
        self::assertSame(1200, $back->refunded()->minorUnits());
        self::assertSame(1200, $back->totalGross()->minorUnits());
        self::assertSame(InvoiceLineSource::CREDIT, $back->lines()[0]->source());
        self::assertSame(self::USER_ID, $back->issuedByUserId());
        self::assertCount(1, $this->creditNotes->forInvoice($this->clinic(), $invoice->id()));
        self::assertSame([], $this->creditNotes->forInvoice(ClinicId::fromString(self::OTHER_CLINIC), $invoice->id()));
    }

    public function testAnotherClinicsCreditNoteIsNotFound(): void
    {
        $note = $this->creditNote($this->issued(1), 1200, 'Erreur', null, 0);
        $this->creditNotes->save($note);

        $this->expectException(CreditNoteNotFoundException::class);

        $this->creditNotes->get(ClinicId::fromString(self::OTHER_CLINIC), $note->id());
    }

    public function testTheInvoiceRemembersWhatWasCreditedAndRefunded(): void
    {
        $invoice = $this->issued(1);
        $invoice->recordPayment($this->payment(7200, PaymentMethod::CARD), $this->now());
        $invoice->credit(Money::fromMinorUnits(1200, $invoice->currency()), true, $this->now());
        $this->invoices->save($invoice);

        $back = $this->invoices->get($this->clinic(), $invoice->id());

        self::assertSame(1200, $back->amountCredited()->minorUnits());
        self::assertSame(1200, $back->amountRefunded()->minorUnits());
        self::assertSame(0, $back->balanceDue()->minorUnits());
    }

    public function testTheDetailsListTheCreditNotesAndWhereTheChequesAre(): void
    {
        $invoice = $this->issued(1);
        $cheque  = $this->payment(3000, PaymentMethod::CHECK);
        $invoice->recordPayment($cheque, $this->now());
        $invoice->credit(Money::fromMinorUnits(1200, $invoice->currency()), false, $this->now());
        $this->invoices->save($invoice);

        $note = $this->creditNote($invoice, 1200, 'Geste commercial', null, 0);
        $this->creditNotes->save($note);

        $this->deposits->save(ChequeDeposit::record(
            ChequeDepositId::fromString($this->uuids->generate()),
            $this->clinic(),
            [$cheque->id()],
            Money::fromMinorUnits(3000, $invoice->currency()),
            new \DateTimeImmutable('2026-08-23 17:30:00'),
        ));

        $details = $this->read->details($this->clinic(), $invoice->id());

        self::assertSame(1200, $details->creditedMinorUnits);
        self::assertSame(0, $details->refundedMinorUnits);
        self::assertSame(6000, $details->creditableMinorUnits);
        self::assertCount(1, $details->creditNotes);
        self::assertSame('Geste commercial', $details->creditNotes[0]->reason);
        self::assertNull($details->creditNotes[0]->refundMethod);
        self::assertNotSame([], $details->creditNotes[0]->creditedLineIds);
        self::assertSame('2026-08-23', $details->payments[0]->depositedAt?->format('Y-m-d'));
        self::assertSame('src-1', $details->lines[0]->sourceReference);
        self::assertSame(3000, $details->balanceDueMinorUnits);

        $creditNote = $this->read->creditNoteDetails($this->clinic(), $note->id());

        self::assertSame($invoice->id()->toString(), $creditNote->invoiceId);
        self::assertSame(self::ADMISSION_ID, $creditNote->admissionId);
        self::assertSame('2026-08-22', $creditNote->invoiceIssuedAt?->format('Y-m-d'));
        self::assertSame([['ratePercent' => '20', 'netMinorUnits' => 1000, 'taxMinorUnits' => 200, 'grossMinorUnits' => 1200, 'allowanceMinorUnits' => 0]], $creditNote->taxBreakdown);
        self::assertSame(1200, $creditNote->totalGrossMinorUnits);
        self::assertSame('CREDIT', $creditNote->lines[0]->source);
    }

    public function testTheRegisterListsCreditNotesBesideTheInvoices(): void
    {
        $invoice = $this->issued(1);
        $invoice->credit(Money::fromMinorUnits(7200, $invoice->currency()), false, $this->now());
        $this->invoices->save($invoice);
        $this->creditNotes->save($this->creditNote($invoice, 7200, 'Erreur de facturation', null, 0));

        $other = $this->issued(2, null, 'Marc Lefebvre');
        $this->invoices->save($other);

        $both = $this->search(new SearchInvoicesCriteria(kinds: [SearchInvoicesCriteria::KIND_INVOICE, SearchInvoicesCriteria::KIND_CREDIT_NOTE], sortBy: SearchInvoicesCriteria::SORT_NUMBER, sortDescending: false));

        self::assertSame(3, $both['total']);
        self::assertSame(['AV-2026-000009', 'FAC-2026-000001', 'FAC-2026-000002'], array_map(static fn (InvoiceListRow $r): ?string => $r->number, $both['items']));

        $note = $both['items'][0];
        self::assertSame(SearchInvoicesCriteria::KIND_CREDIT_NOTE, $note->kind);
        self::assertSame('CREDIT_NOTE', $note->status);
        self::assertSame(7200, $note->totalGrossMinorUnits);
        self::assertSame(0, $note->balanceDueMinorUnits);
        self::assertSame($invoice->id()->toString(), $note->originInvoiceId);
        self::assertSame('FAC-2026-000001', $note->originInvoiceNumber);
        self::assertSame('Erreur de facturation', $note->reason);
        self::assertSame(self::ADMISSION_ID, $note->admissionId);

        $credited = $both['items'][1];
        self::assertSame(InvoiceStatus::CREDITED->value, $credited->status);
        self::assertSame(7200, $credited->creditedMinorUnits);
        self::assertSame(0, $credited->balanceDueMinorUnits);

        $notes = $this->search(new SearchInvoicesCriteria(kinds: [SearchInvoicesCriteria::KIND_CREDIT_NOTE]));
        self::assertSame(1, $notes['total']);

        // The origin's number, the payer and the visits a term points at find the credit note.
        self::assertSame(2, $this->search(new SearchInvoicesCriteria(term: 'FAC-2026-000001', kinds: [SearchInvoicesCriteria::KIND_INVOICE, SearchInvoicesCriteria::KIND_CREDIT_NOTE]))['total']);
        self::assertSame(1, $this->search(new SearchInvoicesCriteria(term: 'Lefebvre', kinds: [SearchInvoicesCriteria::KIND_INVOICE, SearchInvoicesCriteria::KIND_CREDIT_NOTE]))['total']);
        self::assertSame(2, $this->search(new SearchInvoicesCriteria(term: 'Rocky', kinds: [SearchInvoicesCriteria::KIND_INVOICE, SearchInvoicesCriteria::KIND_CREDIT_NOTE], termAdmissionIds: [self::ADMISSION_ID]))['total']);

        // A practitioner's visits keep their documents; a sale without a visit falls out.
        self::assertSame(2, $this->search(new SearchInvoicesCriteria(kinds: [SearchInvoicesCriteria::KIND_INVOICE, SearchInvoicesCriteria::KIND_CREDIT_NOTE], admissionIds: [self::ADMISSION_ID]))['total']);
        self::assertSame(0, $this->search(new SearchInvoicesCriteria(kinds: [SearchInvoicesCriteria::KIND_INVOICE, SearchInvoicesCriteria::KIND_CREDIT_NOTE], admissionIds: [$this->uuids->generate()]))['total']);

        // Period and payer apply to both.
        self::assertSame(0, $this->search(new SearchInvoicesCriteria(issuedFrom: new \DateTimeImmutable('2026-08-23'), kinds: [SearchInvoicesCriteria::KIND_CREDIT_NOTE]))['total']);
        self::assertSame(1, $this->search(new SearchInvoicesCriteria(issuedTo: new \DateTimeImmutable('2026-08-23'), payerClientId: self::CLIENT_ID, kinds: [SearchInvoicesCriteria::KIND_CREDIT_NOTE]))['total']);

        // Statuses only filter invoices.
        self::assertSame(2, $this->search(new SearchInvoicesCriteria(statuses: ['ISSUED'], kinds: [SearchInvoicesCriteria::KIND_INVOICE, SearchInvoicesCriteria::KIND_CREDIT_NOTE]))['total']);

        // Nothing to list for an impossible set of kinds.
        self::assertSame(['items' => [], 'total' => 0], $this->search(new SearchInvoicesCriteria(kinds: [])));
        self::assertSame(['items' => [], 'total' => 0], $this->search(new SearchInvoicesCriteria(term: 'nobody', kinds: [SearchInvoicesCriteria::KIND_CREDIT_NOTE])));
    }

    public function testBalancesCountWhatCreditNotesCancelledOut(): void
    {
        $credited = $this->issued(1);
        $credited->credit(Money::fromMinorUnits(2000, $credited->currency()), false, $this->now());
        $this->invoices->save($credited);

        $paidThenRefunded = $this->issued(2);
        $paidThenRefunded->recordPayment($this->payment(7200, PaymentMethod::CARD), $this->now());
        $paidThenRefunded->credit(Money::fromMinorUnits(1000, $paidThenRefunded->currency()), true, $this->now());
        $this->invoices->save($paidThenRefunded);

        $stats = $this->read->stats(
            $this->clinic(),
            new \DateTimeImmutable('2026-08-22 00:00:00'),
            new \DateTimeImmutable('2026-08-22 23:59:59'),
            new \DateTimeImmutable('2026-08-23 00:00:00'),
        );

        self::assertSame(5200, $stats->outstandingMinorUnits);
        self::assertSame(1, $stats->outstandingCount);
        self::assertSame(1, $stats->overdueCount);
        self::assertSame(5200, $stats->overdueMinorUnits);
        self::assertSame(0, $this->read->stats($this->clinic(), $this->now(), $this->now(), new \DateTimeImmutable('2026-08-01'))->overdueCount);
        self::assertSame([self::CLIENT_ID => 5200], $this->read->outstandingByPayer($this->clinic()));

        // A row left outstanding with nothing owed (written by hand) is not counted.
        $this->connection()->executeStatement(
            "UPDATE billing__invoices SET status = 'ISSUED' WHERE id = :id",
            ['id' => \Symfony\Component\Uid\Uuid::fromString($paidThenRefunded->id()->toString())->toBinary()],
        );
        self::assertSame(1, $this->read->stats($this->clinic(), $this->now(), $this->now())->outstandingCount);

        // An invoice without a payment has no cheque to place.
        self::assertSame([], $this->read->details($this->clinic(), $credited->id())->payments);

        $rows = $this->search(new SearchInvoicesCriteria(sortBy: SearchInvoicesCriteria::SORT_NUMBER, sortDescending: false))['items'];
        self::assertSame(5200, $rows[0]->balanceDueMinorUnits);
        self::assertSame(0, $rows[1]->balanceDueMinorUnits);
        self::assertSame(1000, $rows[1]->refundedMinorUnits);
    }

    public function testTheTillKnowsItsChequesTakingsAndClosedDays(): void
    {
        $invoice = $this->issued(1);
        $first   = $this->payment(2000, PaymentMethod::CHECK, 'n° 1234567 · LCL');
        $second  = $this->payment(1500, PaymentMethod::CHECK);
        $invoice->recordPayment($first, $this->now());
        $invoice->recordPayment($second, $this->now());
        $invoice->recordPayment($this->payment(3700, PaymentMethod::CASH), $this->now());
        $invoice->credit(Money::fromMinorUnits(1200, $invoice->currency()), true, $this->now());
        $this->invoices->save($invoice);
        $this->creditNotes->save($this->creditNote($invoice, 1200, 'Retour produit', PaymentMethod::CASH, 1200));

        $pending = $this->desk->pendingCheques($this->clinic());
        self::assertCount(2, $pending);
        self::assertSame('n° 1234567 · LCL', $pending[0]->reference);
        self::assertSame('FAC-2026-000001', $pending[0]->invoiceNumber);
        self::assertSame('Sophie Dupont', $pending[0]->payerName);
        self::assertSame([], $this->desk->pendingCheques(ClinicId::fromString(self::OTHER_CLINIC)));

        $depositId = ChequeDepositId::fromString($this->uuids->generate());
        $this->deposits->save(ChequeDeposit::record($depositId, $this->clinic(), [$first->id()], Money::fromMinorUnits(2000, $invoice->currency()), $this->now(), self::USER_ID));

        self::assertCount(1, $this->desk->pendingCheques($this->clinic()));

        $deposit = $this->desk->chequeDeposit($this->clinic(), $depositId);
        self::assertNotNull($deposit);
        self::assertSame(2000, $deposit->totalMinorUnits);
        self::assertSame('EUR', $deposit->currency);
        self::assertCount(1, $deposit->cheques);
        self::assertSame($first->id()->toString(), $deposit->cheques[0]->paymentId);
        self::assertNull($this->desk->chequeDeposit(ClinicId::fromString(self::OTHER_CLINIC), $depositId));

        $takings = $this->desk->takings($this->clinic(), new \DateTimeImmutable('2026-08-22 00:00'), new \DateTimeImmutable('2026-08-22 23:59'));
        self::assertSame(['CASH' => 3700, 'CHECK' => 3500], $this->sorted($takings->collectedByMethod));
        self::assertSame(['CASH' => 1200], $takings->refundedByMethod);
        self::assertSame([], $this->desk->takings($this->clinic(), new \DateTimeImmutable('2026-08-23 00:00'), new \DateTimeImmutable('2026-08-23 23:59'))->collectedByMethod);

        self::assertSame(1, $this->closings->nextNumber($this->clinic()));
        self::assertNull($this->closings->findForDate($this->clinic(), '2026-08-22'));
        self::assertNull($this->desk->closingFor($this->clinic(), '2026-08-22'));
        self::assertSame(0, $this->desk->closingCount($this->clinic()));

        $this->closings->save(CashClosing::close(
            CashClosingId::fromString($this->uuids->generate()),
            $this->clinic(),
            1,
            '2026-08-22',
            ['CASH' => 2500, 'CHECK' => 3500],
            2400,
            3500,
            'Pièce de monnaie égarée',
            CurrencyCode::fromString('EUR'),
            $this->now(),
            self::USER_ID,
        ));

        $closed = $this->closings->findForDate($this->clinic(), '2026-08-22');
        self::assertNotNull($closed);
        self::assertSame(1, $closed->number());
        self::assertSame(-100, $closed->gapMinorUnits());
        self::assertSame('Pièce de monnaie égarée', $closed->comment());
        self::assertSame(self::USER_ID, $closed->closedByUserId());
        self::assertSame(2, $this->closings->nextNumber($this->clinic()));
        self::assertSame(1, $this->desk->closingFor($this->clinic(), '2026-08-22')?->number);
        self::assertSame(1, $this->desk->closingCount($this->clinic()));
    }

    public function testAStructuresContactTravelsFromTheInvoiceToItsCreditNote(): void
    {
        $invoice = Invoice::draft(
            id: InvoiceId::fromString($this->uuids->generate()),
            clinicId: $this->clinic(),
            admissionId: null,
            payer: Payer::create(PayerId::fromString(self::CLIENT_ID), 'Élevage des Calanques', ['13400 Aubagne'], 'Mme Claire Rostand'),
            currency: CurrencyCode::fromString('EUR'),
            now: $this->now(),
        );
        $invoice->addLine($this->line(5000, 1000), $this->now());
        $invoice->addLine($this->line(1000, 200), $this->now());
        $invoice->issue(InvoiceNumber::of(2026, 31), SellerIdentities::complete(), $this->now());
        $this->invoices->save($invoice);

        $note = $this->creditNote($invoice, 1200, 'Retour produit', null, 0);
        $this->creditNotes->save($note);

        self::assertSame('Mme Claire Rostand', $this->invoices->get($this->clinic(), $invoice->id())->payer()->attention());
        self::assertSame('Mme Claire Rostand', $this->creditNotes->get($this->clinic(), $note->id())->payer()->attention());
        self::assertSame('Mme Claire Rostand', $this->read->details($this->clinic(), $invoice->id())->payerAttention);
        self::assertSame('Mme Claire Rostand', $this->read->creditNoteDetails($this->clinic(), $note->id())->payerAttention);
        self::assertNull($this->read->details($this->clinic(), $this->issued(32)->id())->payerAttention);
    }

    private function connection(): \Doctrine\DBAL\Connection
    {
        return self::service(self::getContainer()->get(\Doctrine\DBAL\Connection::class), \Doctrine\DBAL\Connection::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function service(mixed $service, string $class): object
    {
        \assert($service instanceof $class);

        return $service;
    }

    /**
     * @return array{items: list<InvoiceListRow>, total: int}
     */
    private function search(SearchInvoicesCriteria $criteria): array
    {
        return $this->read->search($this->clinic(), $criteria);
    }

    /**
     * @param array<string, int> $amounts
     *
     * @return array<string, int>
     */
    private function sorted(array $amounts): array
    {
        ksort($amounts);

        return $amounts;
    }

    private function issued(int $sequence, ?string $admissionId = self::ADMISSION_ID, string $payerName = 'Sophie Dupont'): Invoice
    {
        $invoice = Invoice::draft(
            id: InvoiceId::fromString($this->uuids->generate()),
            clinicId: $this->clinic(),
            admissionId: null !== $admissionId ? AdmissionId::fromString($admissionId) : null,
            payer: Payer::create(PayerId::fromString(self::CLIENT_ID), $payerName, ['12 rue des Lilas']),
            currency: CurrencyCode::fromString('EUR'),
            now: $this->now(),
        );

        $invoice->addLine($this->line(5000, 1000), $this->now());
        $invoice->addLine($this->line(1000, 200), $this->now());
        $invoice->issue(InvoiceNumber::of(2026, $sequence), SellerIdentities::complete(), $this->now());
        $this->invoices->save($invoice);

        return $invoice;
    }

    private function creditNote(Invoice $invoice, int $gross, string $reason, ?PaymentMethod $method, int $refunded): CreditNote
    {
        $net = (int) round($gross / 1.2);

        return CreditNote::issue(
            id: CreditNoteId::fromString($this->uuids->generate()),
            clinicId: $this->clinic(),
            number: InvoiceNumber::of(2026, 9, 'AV'),
            invoiceId: $invoice->id(),
            invoiceNumber: (string) $invoice->number()?->toString(),
            payer: $invoice->payer(),
            lines: [InvoiceLine::reconstitute(
                id: InvoiceLineId::fromString($this->uuids->generate()),
                label: 'Frontline Combo Chien pipettes',
                code: null,
                quantity: '1',
                enteredUnitPrice: Money::fromMinorUnits($gross, $invoice->currency()),
                priceMode: PriceMode::INCLUDING_TAX,
                taxCategoryCode: 'veterinary.drug.companion',
                taxRatePercent: '20',
                operationCategory: OperationCategory::GOODS,
                source: InvoiceLineSource::CREDIT,
                sourceReference: $invoice->lines()[1]->id()->toString(),
                amounts: LineAmounts::of(
                    Money::fromMinorUnits($net, $invoice->currency()),
                    Money::fromMinorUnits($gross, $invoice->currency()),
                    UnitPrice::fromMicroUnits($net * 10_000, $invoice->currency()),
                ),
            )],
            breakdown: [RateBucket::of('20', $net, 0, $gross - $net, $invoice->currency())],
            reason: $reason,
            refundMethod: $method,
            refunded: Money::fromMinorUnits($refunded, $invoice->currency()),
            currency: $invoice->currency(),
            now: $this->now(),
            issuedByUserId: self::USER_ID,
        );
    }

    private function line(int $net, int $tax): InvoiceLine
    {
        $eur = CurrencyCode::fromString('EUR');

        return InvoiceLine::create(
            id: InvoiceLineId::fromString($this->uuids->generate()),
            label: 'Consultation standard',
            code: 'CONS_STD',
            quantity: '1',
            enteredUnitPrice: Money::fromMinorUnits($net + $tax, $eur),
            priceMode: PriceMode::INCLUDING_TAX,
            taxCategoryCode: 'veterinary.act.consultation',
            taxRatePercent: '20',
            operationCategory: OperationCategory::SERVICES,
            source: InvoiceLineSource::CONSULTATION,
            sourceReference: 'src-1',
        );
    }

    private function payment(int $amount, PaymentMethod $method, ?string $reference = null): Payment
    {
        return Payment::create(
            id: PaymentId::fromString($this->uuids->generate()),
            method: $method,
            amount: Money::fromMinorUnits($amount, CurrencyCode::fromString('EUR')),
            receivedAt: $this->now(),
            reference: $reference,
        );
    }

    private function clinic(): ClinicId
    {
        return ClinicId::fromString(self::CLINIC_ID);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-22 10:00:00');
    }
}
