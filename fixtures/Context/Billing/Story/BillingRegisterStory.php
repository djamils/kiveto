<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Billing\Story;

use App\Context\Billing\Application\Command\AddManualInvoiceLine\AddManualInvoiceLine;
use App\Context\Billing\Application\Command\CloseCashDesk\CloseCashDesk;
use App\Context\Billing\Application\Command\DepositPendingCheques\DepositPendingCheques;
use App\Context\Billing\Application\Command\IssueCreditNote\IssueCreditNote;
use App\Context\Billing\Application\Command\IssueInvoice\IssueInvoice;
use App\Context\Billing\Application\Command\PrepareInvoiceForAdmission\PrepareInvoiceForAdmission;
use App\Context\Billing\Application\Command\RecordCounterSale\CounterSaleNewClient;
use App\Context\Billing\Application\Command\RecordCounterSale\RecordCounterSale;
use App\Context\Billing\Application\Command\RecordInvoicePayment\RecordInvoicePayment;
use App\Context\Billing\Application\Command\RemoveInvoiceLine\RemoveInvoiceLine;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ArticleEntity;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * The Paris register of invoices at a realistic size
 * (docs/frontend-theme/kiveto-theme/facturation-v-topbar_3.html): two pages
 * over « 30 jours », and every state the register, its sheet and its windows
 * draw:
 *
 * - invoices settled by card, cash, cheque (some already deposited at the
 *   bank, today's still in the drawer), transfer, and one split between cash
 *   and card;
 * - one paid in part, unpaid ones still « à régler » and overdue ones (past
 *   30 days, one of them paid in part);
 * - credit notes: part of a paid invoice refunded on the card, a line kept as
 *   a credit in the client's favour, and an unpaid invoice entirely cancelled
 *   (« annulée par avoir »);
 * - counter sales: to a client on file, to a newcomer whose record the desk
 *   created there and then, and to a client who refused to give any contact
 *   details;
 * - drafts: a consultation still in progress, and a visit waiting at the desk;
 * - the till: today's takings, a cheque remittance three days ago, and the
 *   two previous days closed (one with an explained difference).
 *
 * The invoices charge the real visits of the ordonnance register (closed
 * consultations, their medicines and ordonnances) plus the consultation fee
 * typed at the desk, through the command bus, then moved back to their visit's
 * day — so numbering follows the calendar and the sheet links to the
 * consultation and the ordonnance. Loaded last.
 */
final class BillingRegisterStory extends Story
{
    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string TZ    = 'Europe/Paris';

    /** How far back the register goes: older visits stay uninvoiced. */
    private const int HISTORY_DAYS = 80;

    /** Consultation fees typed at the desk, by the visit's first motif. */
    private const array FEES = [
        'Antiparasitaire' => ['Consultation de suivi', 3500],
        'Suivi'           => ['Consultation de suivi', 3500],
    ];

    private const array DEFAULT_FEE = ['Consultation standard', 5000];

    /** Above this many units, a medicine line is a count of tablets: it is billed as one box. */
    private const int MAX_BOXES = 3;

    /**
     * What becomes of each visit, oldest first (the list wraps).
     * paid:<mode>[:<reference>] · part:<mode>:<cents> · unpaid · split
     * · refund (paid, a line refunded on the card) · keep (paid, a line kept as a credit)
     * · cancel (unpaid, entirely credited).
     */
    private const array OUTCOMES = [
        'unpaid', 'part:CASH:3000', 'paid:CHECK:n° 1102938 · Crédit Agricole', 'cancel', 'paid:CARD',
        'paid:TRANSFER:Virement M. LAURENT', 'refund', 'paid:CASH', 'paid:CARD', 'paid:CHECK:n° 7781204 · LCL',
        'keep', 'paid:CARD', 'split', 'unpaid', 'paid:CARD', 'part:CARD:2000', 'paid:CASH',
        'paid:CARD', 'paid:CHECK:n° 4410287 · CIC', 'paid:CARD', 'unpaid', 'paid:CASH', 'paid:CARD',
    ];

    private \DateTimeImmutable $today;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function build(): void
    {
        $this->today = $this->clock->now()->setTimezone(new \DateTimeZone(self::TZ))->setTime(0, 0);
        $deposited   = false;
        $sales       = [
            [-21, '11:30', 'client', [['FOOD-RC', 1], ['SHAMPOING-DERM', 1]], 'CARD'],
            [-6, '17:45', 'refused', [['FRONTLINE-CB', 2]], 'CASH'],
            [0, '08:20', 'new', [['FRONTLINE-CB', 3]], 'CASH'],
        ];

        foreach ($this->closedVisits() as $i => $visit) {
            $at = new \DateTimeImmutable($visit['started'], new \DateTimeZone('UTC'))->setTimezone(new \DateTimeZone(self::TZ));

            // The counter sales and the remittance take their place in the calendar.
            while ([] !== $sales && $this->today->modify(\sprintf('%d days %s', $sales[0][0], $sales[0][1])) < $at) {
                $this->counterSale(...array_shift($sales));
            }

            if (!$deposited && $at >= $this->today->modify('-3 days')) {
                $this->deposit($this->today->modify('-3 days 17:30'));
                $deposited = true;
            }

            $this->invoice($visit, $at, self::OUTCOMES[$i % \count(self::OUTCOMES)]);
        }

        foreach ($sales as $sale) {
            $this->counterSale(...$sale);
        }

        $this->drafts();
        $this->closeDay(-2, 200, 'Erreur de rendu monnaie, signalée au client le lendemain');
        $this->closeDay(-1);
    }

    /**
     * @param array{admission: string, started: string, motif: ?string} $visit
     */
    private function invoice(array $visit, \DateTimeImmutable $at, string $outcome): void
    {
        $invoiceId = $this->commandBus->dispatch(new PrepareInvoiceForAdmission(self::PARIS, $visit['admission']));
        \assert(\is_string($invoiceId));

        $this->boxes($invoiceId);

        // The fee is typed at the desk unless the plan already charged a consultation.
        if (!$this->chargesAConsultation($invoiceId)) {
            [$fee, $price] = self::FEES[$visit['motif'] ?? ''] ?? self::DEFAULT_FEE;
            $this->commandBus->dispatch(new AddManualInvoiceLine(self::PARIS, $invoiceId, $fee, '1', $price, 'veterinary.act.consultation'));
        }
        $this->commandBus->dispatch(new IssueInvoice(self::PARIS, $invoiceId));

        $issued = $at->modify('+30 minutes');
        $this->backdateInvoice($invoiceId, $issued);

        $parts = explode(':', $outcome, 3);

        switch ($parts[0]) {
            case 'paid':
                $this->pay($invoiceId, $parts[1], null, $issued, $parts[2] ?? null);
                break;
            case 'part':
                $this->pay($invoiceId, $parts[1], (int) $parts[2], $issued);
                break;
            case 'split':
                $this->pay($invoiceId, 'CASH', 2000, $issued);
                $this->pay($invoiceId, 'CARD', null, $issued->modify('+1 minute'));
                break;
            case 'refund':
                $this->pay($invoiceId, 'CARD', null, $issued);
                $this->credit($invoiceId, 'Retour produit', 'CARD', $issued->modify('+2 days 10:15'), true);
                break;
            case 'keep':
                $this->pay($invoiceId, 'CARD', null, $issued);
                $this->credit($invoiceId, 'Geste commercial', null, $issued->modify('+1 day 09:40'), true);
                break;
            case 'cancel':
                $this->credit($invoiceId, 'Erreur de facturation', null, $issued->modify('+3 hours'), false);
                break;
        }

        $this->entityManager->clear();
    }

    private function chargesAConsultation(string $invoiceId): bool
    {
        return false !== $this->connection->fetchOne(
            "SELECT 1 FROM billing__invoice_lines WHERE invoice_id = :id AND tax_category_code = 'veterinary.act.consultation'",
            ['id' => $this->binary($invoiceId)],
        );
    }

    /**
     * The ordonnances count tablets; the catalogue prices boxes. A line of
     * « 42 » tablets is billed as the box handed over, the way the desk does.
     */
    private function boxes(string $invoiceId): void
    {
        $lines = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(id) AS id, label, unit_price_minor_units, tax_category_code
             FROM billing__invoice_lines WHERE invoice_id = :id AND quantity > :max',
            ['id' => $this->binary($invoiceId), 'max' => self::MAX_BOXES],
        );

        foreach ($lines as $line) {
            $this->commandBus->dispatch(new RemoveInvoiceLine(self::PARIS, $invoiceId, RowAccessor::string($line, 'id')));
            $this->commandBus->dispatch(new AddManualInvoiceLine(
                self::PARIS,
                $invoiceId,
                RowAccessor::string($line, 'label'),
                '1',
                RowAccessor::int($line, 'unit_price_minor_units'),
                RowAccessor::nullableString($line, 'tax_category_code'),
            ));
        }
    }

    /**
     * @param ?int $amount null: what is left
     */
    private function pay(string $invoiceId, string $method, ?int $amount, \DateTimeImmutable $at, ?string $reference = null): void
    {
        $this->commandBus->dispatch(new RecordInvoicePayment(
            clinicId: self::PARIS,
            invoiceId: $invoiceId,
            method: $method,
            amountMinorUnits: $amount ?? $this->balanceOf($invoiceId),
            reference: $reference,
        ));

        $this->connection->executeStatement(
            'UPDATE billing__payments SET received_at = :at WHERE invoice_id = :id AND received_at > :at',
            ['at' => $this->utc($at), 'id' => $this->binary($invoiceId)],
        );
        $this->connection->executeStatement(
            'UPDATE billing__invoices SET paid_at = IF(paid_at IS NULL, NULL, :at), updated_at = :at WHERE id = :id',
            ['at' => $this->utc($at), 'id' => $this->binary($invoiceId)],
        );
    }

    /**
     * @param bool $oneLine the cheapest line rather than the whole invoice
     */
    private function credit(string $invoiceId, string $reason, ?string $refund, \DateTimeImmutable $at, bool $oneLine): void
    {
        // The cheapest line first: what is returned or waived is the small article.
        $lines = $this->connection->fetchFirstColumn(
            'SELECT BIN_TO_UUID(id) FROM billing__invoice_lines WHERE invoice_id = :id ORDER BY gross_minor_units ASC, position ASC',
            ['id' => $this->binary($invoiceId)],
        );
        $lines = array_values(array_filter($lines, 'is_string'));

        $creditNoteId = $this->commandBus->dispatch(new IssueCreditNote(
            clinicId: self::PARIS,
            invoiceId: $invoiceId,
            lineIds: $oneLine ? [$lines[0]] : $lines,
            reason: $reason,
            refundMethod: $refund,
        ));
        \assert(\is_string($creditNoteId));

        $this->connection->executeStatement(
            'UPDATE billing__credit_notes SET issued_at = :at WHERE id = :id',
            ['at' => $this->utc($at), 'id' => $this->binary($creditNoteId)],
        );
        $this->connection->executeStatement(
            'UPDATE billing__invoices SET updated_at = :at, paid_at = IF(paid_at IS NULL, NULL, LEAST(paid_at, :at)) WHERE id = :id',
            ['at' => $this->utc($at), 'id' => $this->binary($invoiceId)],
        );
    }

    /**
     * @param 'client'|'new'|'refused'       $payer    a client on file, a newcomer, or a refusal of contact details
     * @param list<array{0: string, 1: int}> $articles [catalogue code, quantity]
     */
    private function counterSale(int $day, string $time, string $payer, array $articles, string $method): void
    {
        $at        = $this->today->modify(\sprintf('%d days %s', $day, $time));
        $invoiceId = $this->commandBus->dispatch(new RecordCounterSale(
            clinicId: self::PARIS,
            payerClientId: 'client' === $payer ? $this->aClient() : null,
            lines: array_map(fn (array $a): array => ['articleId' => $this->articleId($a[0]), 'quantity' => $a[1]], $articles),
            method: $method,
            newClient: 'new' === $payer ? new CounterSaleNewClient(CounterSaleNewClient::MADAM, 'Haddad', 'Nadia', '0618427731') : null,
            contactDetailsRefused: 'refused' === $payer,
        ));
        \assert(\is_string($invoiceId));

        $this->backdateInvoice($invoiceId, $at);
        $this->connection->executeStatement(
            'UPDATE billing__payments SET received_at = :at WHERE invoice_id = :id',
            ['at' => $this->utc($at), 'id' => $this->binary($invoiceId)],
        );
        $this->connection->executeStatement(
            'UPDATE billing__invoices SET paid_at = :at WHERE id = :id',
            ['at' => $this->utc($at), 'id' => $this->binary($invoiceId)],
        );
        $this->entityManager->clear();
    }

    private function deposit(\DateTimeImmutable $at): void
    {
        $depositId = $this->commandBus->dispatch(new DepositPendingCheques(self::PARIS));
        \assert(\is_string($depositId));

        $this->connection->executeStatement(
            'UPDATE billing__cheque_deposits SET deposited_at = :at WHERE id = :id',
            ['at' => $this->utc($at), 'id' => $this->binary($depositId)],
        );
        $this->entityManager->clear();
    }

    /**
     * Two visits not cashed yet: a consultation still in progress, and a
     * visit over and waiting at the desk.
     */
    private function drafts(): void
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(a.id) AS admission_id, c.status
             FROM admission__admissions a
             INNER JOIN consultation__consultations c ON c.admission_id = a.id
             WHERE a.clinic_id = :clinicId AND a.status = 'active'
               AND EXISTS (SELECT 1 FROM consultation__billing_lines bl WHERE bl.consultation_id = c.id)
               AND NOT EXISTS (SELECT 1 FROM billing__invoices i WHERE i.admission_id = a.id)
             ORDER BY c.status DESC, c.started_at_utc ASC",
            ['clinicId' => $this->binary(self::PARIS)],
        );

        $kept = [];

        foreach ($rows as $row) {
            $status = RowAccessor::string($row, 'status');

            // One of each: the other visits waiting at the desk are left to the demo's « Encaisser ».
            if (isset($kept[$status])) {
                continue;
            }

            $kept[$status] = true;
            $this->commandBus->dispatch(new PrepareInvoiceForAdmission(self::PARIS, RowAccessor::string($row, 'admission_id')));
        }

        $this->entityManager->clear();
    }

    /**
     * Closes a past business day on what its payments say the till held; a
     * shortfall, when given, is explained.
     */
    private function closeDay(int $day, int $shortfall = 0, ?string $comment = null): void
    {
        $start = $this->today->modify(\sprintf('%d days', $day));
        $end   = $start->modify('+1 day -1 second');
        $cash  = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount_minor_units), 0) FROM billing__payments
             WHERE clinic_id = :clinicId AND method = 'CASH' AND received_at BETWEEN :from AND :to",
            ['clinicId' => $this->binary(self::PARIS), 'from' => $this->utc($start), 'to' => $this->utc($end)],
        );
        $cash = is_numeric($cash) ? (int) $cash : 0;

        $this->commandBus->dispatch(new CloseCashDesk(
            clinicId: self::PARIS,
            businessDate: $start->format('Y-m-d'),
            dayStart: $start->setTimezone(new \DateTimeZone('UTC')),
            dayEnd: $end->setTimezone(new \DateTimeZone('UTC')),
            countedCashMinorUnits: $cash - $shortfall,
            countedChequesMinorUnits: null,
            comment: $comment,
        ));

        $this->connection->executeStatement(
            'UPDATE billing__cash_closings SET closed_at = :at WHERE clinic_id = :clinicId AND business_date = :day',
            ['at' => $this->utc($start->setTime(19, 10)), 'clinicId' => $this->binary(self::PARIS), 'day' => $start->format('Y-m-d')],
        );
    }

    /**
     * Closed visits of the past weeks no desk has cashed, oldest first.
     *
     * @return list<array{admission: string, started: string, motif: ?string}>
     */
    private function closedVisits(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(a.id) AS admission_id, c.started_at_utc,
                    (SELECT m.label FROM consultation__motifs m WHERE m.consultation_id = c.id ORDER BY m.position LIMIT 1) AS motif
             FROM admission__admissions a
             INNER JOIN consultation__consultations c ON c.admission_id = a.id AND c.status = 'CLOSED'
             WHERE a.clinic_id = :clinicId AND a.status = 'closed' AND c.started_at_utc >= :since
               AND NOT EXISTS (SELECT 1 FROM billing__invoices i WHERE i.admission_id = a.id)
             ORDER BY c.started_at_utc ASC",
            [
                'clinicId' => $this->binary(self::PARIS),
                'since'    => $this->utc($this->today->modify(\sprintf('-%d days', self::HISTORY_DAYS))),
            ],
        );

        return array_map(static fn (array $row): array => [
            'admission' => RowAccessor::string($row, 'admission_id'),
            'started'   => RowAccessor::string($row, 'started_at_utc'),
            'motif'     => RowAccessor::nullableString($row, 'motif'),
        ], $rows);
    }

    private function backdateInvoice(string $invoiceId, \DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'UPDATE billing__invoices SET created_at = :created, issued_at = :at, updated_at = :at, paid_at = IF(paid_at IS NULL, NULL, :at) WHERE id = :id',
            ['created' => $this->utc($at->modify('-4 minutes')), 'at' => $this->utc($at), 'id' => $this->binary($invoiceId)],
        );
    }

    /**
     * Read from the rows: loading an aggregate for a figure the fixtures only
     * need to hand back as a payment amount costs more than it is worth.
     */
    private function balanceOf(string $invoiceId): int
    {
        $row = $this->connection->fetchAssociative(
            'SELECT i.credited_minor_units, i.refunded_minor_units,
                    (SELECT COALESCE(SUM(l.gross_minor_units), 0) FROM billing__invoice_lines l WHERE l.invoice_id = i.id) AS gross,
                    (SELECT COALESCE(SUM(p.amount_minor_units), 0) FROM billing__payments p WHERE p.invoice_id = i.id) AS paid
             FROM billing__invoices i WHERE i.id = :id',
            ['id' => $this->binary($invoiceId)],
        );
        \assert(false !== $row);

        return RowAccessor::int($row, 'gross') - RowAccessor::int($row, 'credited_minor_units')
            - RowAccessor::int($row, 'paid') + RowAccessor::int($row, 'refunded_minor_units');
    }

    private function aClient(): string
    {
        $id = $this->connection->fetchOne(
            "SELECT BIN_TO_UUID(id) FROM client__clients WHERE clinic_id = :clinicId AND status = 'active' ORDER BY last_name ASC LIMIT 1",
            ['clinicId' => $this->binary(self::PARIS)],
        );
        \assert(\is_string($id));

        return $id;
    }

    private function articleId(string $code): string
    {
        $article = $this->entityManager->getRepository(ArticleEntity::class)->findOneBy([
            'code'     => $code,
            'tenantId' => Uuid::fromString(self::PARIS),
        ]);

        if (null === $article) {
            throw new \RuntimeException(\sprintf('Catalog article "%s" not found.', $code));
        }

        return $article->getId()->toRfc4122();
    }

    private function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function binary(string $uuid): string
    {
        return Uuid::fromString($uuid)->toBinary();
    }
}
