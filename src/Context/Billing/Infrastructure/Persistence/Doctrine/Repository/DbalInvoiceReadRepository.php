<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Application\Query\FindInvoiceForAdmission\AdmissionInvoiceRow;
use App\Context\Billing\Application\Query\GetBillingStats\BillingStats;
use App\Context\Billing\Application\Query\GetCreditNoteDetails\CreditNoteDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\CreditNoteSummaryView;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceLineView;
use App\Context\Billing\Application\Query\GetInvoiceDetails\PaymentView;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListRow;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesCriteria;
use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Repository\CreditNoteRepositoryInterface;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentTerms;
use App\Context\Billing\Domain\ValueObject\ProcessingRule;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Read side of the Billing BC.
 *
 * Invoices and credit notes carry their totals on their row — calculated per
 * VAT rate by the aggregate and frozen at issue — so the lists read them as
 * they are, never recompute them, and can never disagree with the document
 * they link to. The register lists both in one query.
 */
final readonly class DbalInvoiceReadRepository implements InvoiceReadRepositoryInterface
{
    private const string DEFAULT_CURRENCY = 'EUR';

    /**
     * Columns the list can be sorted on, mapped to their SQL expression.
     */
    private const array SORTS = [
        SearchInvoicesCriteria::SORT_DATE   => 'd.sort_date',
        SearchInvoicesCriteria::SORT_NUMBER => 'd.number',
        SearchInvoicesCriteria::SORT_PAYER  => 'd.payer_name',
        SearchInvoicesCriteria::SORT_AMOUNT => 'd.total_net',
        SearchInvoicesCriteria::SORT_STATUS => 'd.status',
    ];

    private const array OUTSTANDING = [InvoiceStatus::ISSUED->value, InvoiceStatus::PARTIALLY_PAID->value];

    public function __construct(
        private Connection $connection,
        private InvoiceRepositoryInterface $invoices,
        private CreditNoteRepositoryInterface $creditNotes,
    ) {
    }

    public function details(ClinicId $clinicId, InvoiceId $invoiceId): InvoiceDetails
    {
        $invoice = $this->invoices->get($clinicId, $invoiceId);

        $lines = array_map(static fn (InvoiceLine $line): InvoiceLineView => self::lineView($line), $invoice->lines());

        $deposits = $this->depositDates($invoice->payments());
        $payments = array_map(
            static fn (Payment $payment): PaymentView => new PaymentView(
                id: $payment->id()->toString(),
                method: $payment->method()->value,
                methodLabel: $payment->method()->label(),
                amountMinorUnits: $payment->amount()->minorUnits(),
                receivedAt: $payment->receivedAt(),
                reference: $payment->reference(),
                recordedByUserId: $payment->recordedByUserId(),
                depositedAt: $deposits[$payment->id()->toString()] ?? null,
            ),
            $invoice->payments(),
        );

        $creditNotes = array_map(
            static fn (CreditNote $note): CreditNoteSummaryView => new CreditNoteSummaryView(
                id: $note->id()->toString(),
                number: $note->number()->toString(),
                totalGrossMinorUnits: $note->totalGross()->minorUnits(),
                reason: $note->reason(),
                refundMethod: $note->refundMethod()?->value,
                refundMethodLabel: $note->refundMethod()?->label(),
                refundedMinorUnits: $note->refunded()->minorUnits(),
                issuedAt: $note->issuedAt(),
                creditedLineIds: $note->creditedLineIds(),
            ),
            $this->creditNotes->forInvoice($clinicId, $invoiceId),
        );

        $discount = $invoice->discount();

        return new InvoiceDetails(
            id: $invoice->id()->toString(),
            clinicId: $invoice->clinicId()->toString(),
            number: $invoice->number()?->toString(),
            status: $invoice->status()->value,
            statusLabel: $invoice->status()->label(),
            admissionId: $invoice->admissionId()?->toString(),
            payerClientId: $invoice->payer()->clientId()?->toString(),
            payerName: $invoice->payer()->displayName(),
            payerAddressLines: $invoice->payer()->addressLines(),
            lines: $lines,
            payments: $payments,
            taxBreakdown: $invoice->taxBreakdown(),
            currency: $invoice->currency()->toString(),
            totalNetBeforeDiscountMinorUnits: $invoice->totalNetBeforeDiscount()->minorUnits(),
            discountMinorUnits: $invoice->discountAmount()->minorUnits(),
            discountKind: $discount?->kind()->value,
            discountValue: $discount?->value(),
            discountReason: $discount?->reason(),
            totalNetMinorUnits: $invoice->totalNet()->minorUnits(),
            totalTaxMinorUnits: $invoice->totalTax()->minorUnits(),
            totalGrossMinorUnits: $invoice->totalGross()->minorUnits(),
            amountPaidMinorUnits: $invoice->amountPaid()->minorUnits(),
            balanceDueMinorUnits: $invoice->balanceDue()->minorUnits(),
            notes: $invoice->notes(),
            createdAt: $invoice->createdAt(),
            issuedAt: $invoice->issuedAt(),
            paidAt: $invoice->paidAt(),
            cancelledAt: $invoice->cancelledAt(),
            cancellationReason: $invoice->cancellationReason(),
            creditedMinorUnits: $invoice->amountCredited()->minorUnits(),
            refundedMinorUnits: $invoice->amountRefunded()->minorUnits(),
            creditableMinorUnits: $invoice->creditableAmount()->minorUnits(),
            creditNotes: $creditNotes,
            counterSalePayer: $invoice->counterSalePayer()?->value,
            payerAttention: $invoice->payer()->attention(),
            processingRule: $invoice->processingRule()->value,
            payerSiren: $invoice->payer()->fiscalIdentity()?->siren()?->value(),
            payerVatNumber: $invoice->payer()->fiscalIdentity()?->vatNumber()?->value(),
            dueDate: $invoice->paymentTerms()?->dueDate,
            paymentTermsNote: $invoice->paymentTerms()?->note,
            professionalMentions: ProcessingRule::B2B === $invoice->processingRule() ? array_values(PaymentTerms::PROFESSIONAL_MENTIONS) : [],
            invoicingFramework: $invoice->framework()?->code(),
        );
    }

    public function creditNoteDetails(ClinicId $clinicId, CreditNoteId $creditNoteId): CreditNoteDetails
    {
        $note   = $this->creditNotes->get($clinicId, $creditNoteId);
        $origin = $this->connection->fetchAssociative(
            'SELECT BIN_TO_UUID(admission_id) AS admission_id, issued_at FROM billing__invoices WHERE id = :id',
            ['id' => Uuid::fromString($note->invoiceId()->toString())->toBinary()],
        );
        $issued = false !== $origin ? RowAccessor::nullableString($origin, 'issued_at') : null;

        return new CreditNoteDetails(
            id: $note->id()->toString(),
            clinicId: $note->clinicId()->toString(),
            number: $note->number()->toString(),
            invoiceId: $note->invoiceId()->toString(),
            invoiceNumber: $note->invoiceNumber(),
            invoiceIssuedAt: null !== $issued ? new \DateTimeImmutable($issued) : null,
            admissionId: false !== $origin ? RowAccessor::nullableString($origin, 'admission_id') : null,
            payerClientId: $note->payer()->clientId()?->toString(),
            payerName: $note->payer()->displayName(),
            payerAddressLines: $note->payer()->addressLines(),
            lines: array_map(static fn (InvoiceLine $line): InvoiceLineView => self::lineView($line), $note->lines()),
            taxBreakdown: $note->taxBreakdown(),
            currency: $note->currency()->toString(),
            totalNetMinorUnits: $note->totalNet()->minorUnits(),
            totalTaxMinorUnits: $note->totalTax()->minorUnits(),
            totalGrossMinorUnits: $note->totalGross()->minorUnits(),
            reason: $note->reason(),
            refundMethod: $note->refundMethod()?->value,
            refundMethodLabel: $note->refundMethod()?->label(),
            refundedMinorUnits: $note->refunded()->minorUnits(),
            issuedAt: $note->issuedAt(),
            issuedByUserId: $note->issuedByUserId(),
            payerAttention: $note->payer()->attention(),
            processingRule: $note->processingRule()->value,
            payerSiren: $note->payer()->fiscalIdentity()?->siren()?->value(),
            payerVatNumber: $note->payer()->fiscalIdentity()?->vatNumber()?->value(),
            discountMinorUnits: $note->discountAmount()->minorUnits(),
        );
    }

    public function search(ClinicId $clinicId, SearchInvoicesCriteria $criteria): array
    {
        if ($criteria->isImpossible()) {
            return ['items' => [], 'total' => 0];
        }

        $invoices = $this->invoiceFilter($clinicId, $criteria);
        $notes    = $this->creditNoteFilter($clinicId, $criteria);
        $params   = $invoices['params'] + $notes['params'];
        $types    = $invoices['types'] + $notes['types'];
        $parts    = [];
        $counts   = [];

        if ($criteria->lists(SearchInvoicesCriteria::KIND_INVOICE)) {
            $counts[] = 'SELECT COUNT(*) FROM billing__invoices i WHERE ' . $invoices['where'];
            $parts[]  = \sprintf(
                "SELECT 'INVOICE' AS kind, i.id AS id_bin, i.number, i.status, i.currency,
                        i.payer_client_id AS payer_bin, i.payer_name, i.admission_id AS admission_bin,
                        i.created_at, i.issued_at, i.discount_kind, i.discount_value,
                        i.total_tax_basis AS total_net, i.total_tax, %s AS total_paid,
                        i.credited_minor_units AS credited, i.refunded_minor_units AS refunded,
                        NULL AS origin_bin, NULL AS origin_number, i.total_gross AS stored_gross, NULL AS reason,
                        i.counter_sale_payer, COALESCE(i.issued_at, i.created_at) AS sort_date
                 FROM billing__invoices i WHERE %s",
                $this->paidExpression(),
                $invoices['where'],
            );
        }

        if ($criteria->lists(SearchInvoicesCriteria::KIND_CREDIT_NOTE)) {
            $counts[] = 'SELECT COUNT(*) FROM billing__credit_notes c LEFT JOIN billing__invoices oi ON oi.id = c.invoice_id WHERE ' . $notes['where'];
            $parts[]  = \sprintf(
                "SELECT 'CREDIT_NOTE' AS kind, c.id AS id_bin, c.number, 'CREDIT_NOTE' AS status, c.currency,
                        c.payer_client_id AS payer_bin, c.payer_name, oi.admission_id AS admission_bin,
                        c.issued_at AS created_at, c.issued_at, NULL AS discount_kind, NULL AS discount_value,
                        c.total_net_minor_units AS total_net, c.total_tax_minor_units AS total_tax,
                        c.refunded_minor_units AS total_paid, 0 AS credited, 0 AS refunded,
                        c.invoice_id AS origin_bin, c.invoice_number AS origin_number,
                        c.total_gross_minor_units AS stored_gross, c.reason, NULL AS counter_sale_payer, c.issued_at AS sort_date
                 FROM billing__credit_notes c LEFT JOIN billing__invoices oi ON oi.id = c.invoice_id
                 WHERE %s",
                $notes['where'],
            );
        }

        $total = 0;

        foreach ($counts as $count) {
            $raw = $this->connection->fetchOne($count, $params, $types);
            $total += is_numeric($raw) ? (int) $raw : 0;
        }

        if (0 === $total) {
            return ['items' => [], 'total' => 0];
        }

        $sort      = self::SORTS[$criteria->sortBy] ?? self::SORTS[SearchInvoicesCriteria::SORT_DATE];
        $direction = $criteria->sortDescending ? 'DESC' : 'ASC';

        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT d.kind, BIN_TO_UUID(d.id_bin) AS id, d.number, d.status, d.currency,
                        BIN_TO_UUID(d.payer_bin) AS payer_client_id, d.payer_name,
                        BIN_TO_UUID(d.admission_bin) AS admission_id, d.created_at, d.issued_at,
                        d.discount_kind, d.discount_value, d.total_net, d.total_tax, d.total_paid,
                        d.credited, d.refunded, BIN_TO_UUID(d.origin_bin) AS origin_id, d.origin_number, d.stored_gross, d.reason,
                        d.counter_sale_payer
                 FROM (%s) d
                 ORDER BY %s %s, d.created_at DESC
                 LIMIT %d OFFSET %d',
                implode(' UNION ALL ', $parts),
                $sort,
                $direction,
                // Both come from a whitelist in the controller, never from raw
                // input, and MySQL refuses a bound parameter here anyway.
                $criteria->perPage,
                $criteria->offset(),
            ),
            $params,
            $types,
        );

        return ['items' => array_map(fn (array $row): InvoiceListRow => $this->listRow($row), $rows), 'total' => $total];
    }

    public function forAdmission(ClinicId $clinicId, AdmissionId $admissionId): ?AdmissionInvoiceRow
    {
        $row = $this->connection->fetchAssociative(
            'SELECT BIN_TO_UUID(i.id) AS id, i.number, i.status, i.currency, i.total_gross
                 FROM billing__invoices i
                 WHERE i.clinic_id = :clinicId AND i.admission_id = :admissionId
                 ORDER BY i.created_at DESC
                 LIMIT 1',
            [
                'clinicId'    => Uuid::fromString($clinicId->toString())->toBinary(),
                'admissionId' => Uuid::fromString($admissionId->toString())->toBinary(),
            ],
        );

        if (false === $row) {
            return null;
        }

        return new AdmissionInvoiceRow(
            id: RowAccessor::string($row, 'id'),
            number: RowAccessor::nullableString($row, 'number'),
            status: RowAccessor::string($row, 'status'),
            totalGrossMinorUnits: RowAccessor::int($row, 'total_gross'),
            currency: RowAccessor::string($row, 'currency'),
        );
    }

    public function stats(
        ClinicId $clinicId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?\DateTimeImmutable $overdueIssuedBefore = null,
    ): BillingStats {
        $clinicBinary = Uuid::fromString($clinicId->toString())->toBinary();

        $collectedRaw = $this->connection->fetchOne(
            'SELECT COALESCE(SUM(p.amount_minor_units), 0)
             FROM billing__payments p
             WHERE p.clinic_id = :clinicId AND p.received_at >= :from AND p.received_at <= :to',
            [
                'clinicId' => $clinicBinary,
                'from'     => $from->format('Y-m-d H:i:s'),
                'to'       => $to->format('Y-m-d H:i:s'),
            ],
        );

        $issuedRaw = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM billing__invoices i
             WHERE i.clinic_id = :clinicId AND i.issued_at >= :from AND i.issued_at <= :to',
            [
                'clinicId' => $clinicBinary,
                'from'     => $from->format('Y-m-d H:i:s'),
                'to'       => $to->format('Y-m-d H:i:s'),
            ],
        );

        $due          = 0;
        $count        = 0;
        $overdue      = 0;
        $overdueCount = 0;
        $limit        = $overdueIssuedBefore?->format('Y-m-d H:i:s');

        foreach ($this->outstandingRows($clinicBinary, false) as $row) {
            $balance = $this->balanceOf($row);

            if ($balance <= 0) {
                continue;
            }

            $due += $balance;
            ++$count;

            if (null !== $limit && RowAccessor::string($row, 'issued_at') < $limit) {
                $overdue += $balance;
                ++$overdueCount;
            }
        }

        return new BillingStats(
            collectedMinorUnits: is_numeric($collectedRaw) ? (int) $collectedRaw : 0,
            outstandingMinorUnits: $due,
            issuedCount: is_numeric($issuedRaw) ? (int) $issuedRaw : 0,
            outstandingCount: $count,
            currency: self::DEFAULT_CURRENCY,
            overdueCount: $overdueCount,
            overdueMinorUnits: $overdue,
        );
    }

    public function outstandingByPayer(ClinicId $clinicId): array
    {
        $balances = [];

        foreach ($this->outstandingRows(Uuid::fromString($clinicId->toString())->toBinary(), true) as $row) {
            $due = $this->balanceOf($row);

            if ($due > 0) {
                $payer            = RowAccessor::uuid($row, 'payer_client_id');
                $balances[$payer] = ($balances[$payer] ?? 0) + $due;
            }
        }

        return $balances;
    }

    /**
     * Issued and partly paid invoices, with what it takes to work out their balance.
     *
     * @return list<array<string, mixed>>
     */
    private function outstandingRows(string $clinicBinary, bool $withPayerOnly): array
    {
        return $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT i.payer_client_id, i.issued_at, i.total_gross AS stored_gross,
                        i.credited_minor_units AS credited, i.refunded_minor_units AS refunded,
                        %s AS total_paid
                 FROM billing__invoices i
                 WHERE i.clinic_id = :clinicId AND i.status IN (:statuses)%s',
                $this->paidExpression(),
                $withPayerOnly ? ' AND i.payer_client_id IS NOT NULL' : '',
            ),
            ['clinicId' => $clinicBinary, 'statuses' => self::OUTSTANDING],
            ['statuses' => ArrayParameterType::STRING],
        );
    }

    /**
     * What an invoice row is still owed: as the aggregate counts it.
     *
     * @param array<string, mixed> $row
     */
    private function balanceOf(array $row): int
    {
        return RowAccessor::int($row, 'stored_gross')
            - RowAccessor::int($row, 'credited')
            - (int) round((float) RowAccessor::string($row, 'total_paid'))
            + RowAccessor::int($row, 'refunded');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function listRow(array $row): InvoiceListRow
    {
        $note     = SearchInvoicesCriteria::KIND_CREDIT_NOTE === RowAccessor::string($row, 'kind');
        $gross    = RowAccessor::int($row, 'stored_gross');
        $paid     = (int) round((float) RowAccessor::string($row, 'total_paid'));
        $credited = RowAccessor::int($row, 'credited');
        $refunded = RowAccessor::int($row, 'refunded');
        $issuedAt = RowAccessor::nullableString($row, 'issued_at');

        return new InvoiceListRow(
            id: RowAccessor::string($row, 'id'),
            number: RowAccessor::nullableString($row, 'number'),
            status: RowAccessor::string($row, 'status'),
            payerClientId: RowAccessor::nullableString($row, 'payer_client_id'),
            payerName: RowAccessor::string($row, 'payer_name'),
            currency: RowAccessor::string($row, 'currency'),
            totalGrossMinorUnits: $gross,
            amountPaidMinorUnits: $paid,
            balanceDueMinorUnits: $note ? 0 : $gross - $credited - $paid + $refunded,
            createdAt: new \DateTimeImmutable(RowAccessor::string($row, 'created_at')),
            issuedAt: null !== $issuedAt ? new \DateTimeImmutable($issuedAt) : null,
            admissionId: RowAccessor::nullableString($row, 'admission_id'),
            kind: $note ? SearchInvoicesCriteria::KIND_CREDIT_NOTE : SearchInvoicesCriteria::KIND_INVOICE,
            creditedMinorUnits: $credited,
            refundedMinorUnits: $refunded,
            originInvoiceId: RowAccessor::nullableString($row, 'origin_id'),
            originInvoiceNumber: RowAccessor::nullableString($row, 'origin_number'),
            reason: RowAccessor::nullableString($row, 'reason'),
            counterSalePayer: RowAccessor::nullableString($row, 'counter_sale_payer'),
        );
    }

    /**
     * When each cheque among these payments went to the bank.
     *
     * @param list<Payment> $payments
     *
     * @return array<string, \DateTimeImmutable> payment id => deposit date
     */
    private function depositDates(array $payments): array
    {
        $ids = [];

        foreach ($payments as $payment) {
            $ids[] = Uuid::fromString($payment->id()->toString())->toBinary();
        }

        if ([] === $ids) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(dp.payment_id) AS payment_id, d.deposited_at
             FROM billing__cheque_deposit_payments dp
             INNER JOIN billing__cheque_deposits d ON d.id = dp.deposit_id
             WHERE dp.payment_id IN (?)',
            [$ids],
            [ArrayParameterType::BINARY],
        );

        $dates = [];

        foreach ($rows as $row) {
            $dates[RowAccessor::string($row, 'payment_id')] = new \DateTimeImmutable(RowAccessor::string($row, 'deposited_at'));
        }

        return $dates;
    }

    private static function lineView(InvoiceLine $line): InvoiceLineView
    {
        return new InvoiceLineView(
            id: $line->id()->toString(),
            label: $line->label(),
            code: $line->code(),
            quantity: $line->quantity(),
            unitPriceMinorUnits: $line->enteredUnitPrice()->minorUnits(),
            unitPriceMode: $line->priceMode()->value,
            unitNet: $line->unitNet()->toDecimalString(),
            netMinorUnits: $line->netAmount()->minorUnits(),
            grossMinorUnits: $line->grossAmount()->minorUnits(),
            taxRatePercent: $line->taxRatePercent(),
            taxCategoryCode: $line->taxCategoryCode(),
            source: $line->source()->value,
            sourceReference: $line->sourceReference(),
        );
    }

    /**
     * @return array{where: string, params: array<string, mixed>, types: array<string, ArrayParameterType>}
     */
    private function invoiceFilter(ClinicId $clinicId, SearchInvoicesCriteria $criteria): array
    {
        $common = $this->commonFilter($clinicId, $criteria, 'i.payer_name', 'i.number', 'i.admission_id', 'COALESCE(i.issued_at, i.created_at)', 'i.payer_client_id');
        $where  = ['i.clinic_id = :clinicId', ...$common['where']];

        if ([] !== $criteria->statuses) {
            $where[]                      = 'i.status IN (:statuses)';
            $common['params']['statuses'] = $criteria->statuses;
            $common['types']['statuses']  = ArrayParameterType::STRING;
        }

        return ['where' => implode(' AND ', $where), 'params' => $common['params'], 'types' => $common['types']];
    }

    /**
     * @return array{where: string, params: array<string, mixed>, types: array<string, ArrayParameterType>}
     */
    private function creditNoteFilter(ClinicId $clinicId, SearchInvoicesCriteria $criteria): array
    {
        $common = $this->commonFilter($clinicId, $criteria, 'c.payer_name', 'c.number', 'oi.admission_id', 'c.issued_at', 'c.payer_client_id', 'c.invoice_number');

        return [
            'where'  => implode(' AND ', ['c.clinic_id = :clinicId', ...$common['where']]),
            'params' => $common['params'],
            'types'  => $common['types'],
        ];
    }

    /**
     * The conditions invoices and credit notes share, on their own columns.
     *
     * @return array{where: list<string>, params: array<string, mixed>, types: array<string, ArrayParameterType>}
     */
    private function commonFilter(
        ClinicId $clinicId,
        SearchInvoicesCriteria $criteria,
        string $payerName,
        string $number,
        string $admission,
        string $date,
        string $payer,
        ?string $originNumber = null,
    ): array {
        $where  = [];
        $params = ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()];
        $types  = [];

        if (null !== $criteria->term && '' !== $criteria->term) {
            $match = [$payerName . ' LIKE :term', $number . ' LIKE :term'];

            if (null !== $originNumber) {
                $match[] = $originNumber . ' LIKE :term';
            }

            if ([] !== $criteria->termAdmissionIds) {
                $match[]                  = $admission . ' IN (:termAdmissions)';
                $params['termAdmissions'] = self::binaries($criteria->termAdmissionIds);
                $types['termAdmissions']  = ArrayParameterType::BINARY;
            }

            $where[]        = '(' . implode(' OR ', $match) . ')';
            $params['term'] = '%' . $criteria->term . '%';
        }

        if (null !== $criteria->admissionIds) {
            $where[]              = $admission . ' IN (:admissions)';
            $params['admissions'] = self::binaries($criteria->admissionIds);
            $types['admissions']  = ArrayParameterType::BINARY;
        }

        if (null !== $criteria->issuedFrom) {
            $where[]        = $date . ' >= :from';
            $params['from'] = $criteria->issuedFrom->format('Y-m-d H:i:s');
        }

        if (null !== $criteria->issuedTo) {
            $where[]      = $date . ' <= :to';
            $params['to'] = $criteria->issuedTo->format('Y-m-d H:i:s');
        }

        if (null !== $criteria->payerClientId) {
            $where[]         = $payer . ' = :payer';
            $params['payer'] = Uuid::fromString($criteria->payerClientId)->toBinary();
        }

        return ['where' => $where, 'params' => $params, 'types' => $types];
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private static function binaries(array $ids): array
    {
        return array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $ids);
    }

    private function paidExpression(): string
    {
        return '(SELECT COALESCE(SUM(pm.amount_minor_units), 0)
                 FROM billing__payments pm WHERE pm.invoice_id = i.id)';
    }
}
