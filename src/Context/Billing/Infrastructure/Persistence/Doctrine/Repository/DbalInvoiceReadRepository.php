<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Application\Query\FindInvoiceForAdmission\AdmissionInvoiceRow;
use App\Context\Billing\Application\Query\GetBillingStats\BillingStats;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceLineView;
use App\Context\Billing\Application\Query\GetInvoiceDetails\PaymentView;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListRow;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesCriteria;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\Discount;
use App\Context\Billing\Domain\ValueObject\DiscountKind;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Read side of the Billing BC.
 *
 * Totals are recomputed in SQL rather than stored on the invoice row: they are
 * a pure function of the lines, and a denormalised copy is one more thing that
 * can drift.
 */
final readonly class DbalInvoiceReadRepository implements InvoiceReadRepositoryInterface
{
    private const string DEFAULT_CURRENCY = 'EUR';

    /**
     * Columns the list can be sorted on, mapped to their SQL expression.
     */
    private const array SORTS = [
        SearchInvoicesCriteria::SORT_DATE   => 'COALESCE(i.issued_at, i.created_at)',
        SearchInvoicesCriteria::SORT_NUMBER => 'i.number',
        SearchInvoicesCriteria::SORT_PAYER  => 'i.payer_name',
        SearchInvoicesCriteria::SORT_AMOUNT => 'total_net',
        SearchInvoicesCriteria::SORT_STATUS => 'i.status',
    ];

    public function __construct(
        private Connection $connection,
        private InvoiceRepositoryInterface $invoices,
    ) {
    }

    public function details(ClinicId $clinicId, InvoiceId $invoiceId): InvoiceDetails
    {
        $invoice = $this->invoices->get($clinicId, $invoiceId);

        $lines = array_map(
            static fn ($line): InvoiceLineView => new InvoiceLineView(
                id: $line->id()->toString(),
                label: $line->label(),
                code: $line->code(),
                quantity: $line->quantity(),
                unitPriceMinorUnits: $line->unitNetPrice()->minorUnits(),
                netMinorUnits: $line->netAmount()->minorUnits(),
                taxMinorUnits: $line->taxAmount()->minorUnits(),
                grossMinorUnits: $line->grossAmount()->minorUnits(),
                taxRatePercent: $line->taxRatePercent(),
                taxCategoryCode: $line->taxCategoryCode(),
                source: $line->source()->value,
            ),
            $invoice->lines(),
        );

        $payments = array_map(
            static fn ($payment): PaymentView => new PaymentView(
                id: $payment->id()->toString(),
                method: $payment->method()->value,
                methodLabel: $payment->method()->label(),
                amountMinorUnits: $payment->amount()->minorUnits(),
                receivedAt: $payment->receivedAt(),
                reference: $payment->reference(),
            ),
            $invoice->payments(),
        );

        $breakdown = [];

        foreach ($invoice->taxBreakdown() as $row) {
            $breakdown[] = [
                'ratePercent'   => $row['ratePercent'],
                'netMinorUnits' => $row['netMinorUnits'],
                'taxMinorUnits' => $row['taxMinorUnits'],
            ];
        }

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
            taxBreakdown: $breakdown,
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
        );
    }

    public function search(ClinicId $clinicId, SearchInvoicesCriteria $criteria): array
    {
        if ($criteria->isImpossible()) {
            return ['items' => [], 'total' => 0];
        }

        $filter = $this->buildFilter($clinicId, $criteria);

        $totalRaw = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM billing__invoices i WHERE ' . $filter['where'],
            $filter['params'],
            $filter['types'],
        );

        $total = is_numeric($totalRaw) ? (int) $totalRaw : 0;

        if (0 === $total) {
            return ['items' => [], 'total' => 0];
        }

        $sort      = self::SORTS[$criteria->sortBy] ?? self::SORTS[SearchInvoicesCriteria::SORT_DATE];
        $direction = $criteria->sortDescending ? 'DESC' : 'ASC';

        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT BIN_TO_UUID(i.id) AS id, i.number, i.status, i.currency,
                        BIN_TO_UUID(i.payer_client_id) AS payer_client_id, i.payer_name,
                        BIN_TO_UUID(i.admission_id) AS admission_id,
                        i.created_at, i.issued_at,
                        i.discount_kind, i.discount_value,
                        %s AS total_net, %s AS total_tax,
                        %s AS total_paid
                 FROM billing__invoices i
                 WHERE %s
                 ORDER BY %s %s, i.created_at DESC
                 LIMIT %d OFFSET %d',
                $this->netExpression(),
                $this->taxExpression(),
                $this->paidExpression(),
                $filter['where'],
                $sort,
                $direction,
                // Both come from a whitelist in the controller, never from raw
                // input, and MySQL refuses a bound parameter here anyway.
                $criteria->perPage,
                $criteria->offset(),
            ),
            $filter['params'],
            $filter['types'],
        );

        $items = [];

        foreach ($rows as $row) {
            $gross = $this->grossAfterDiscount($row);
            $paid  = (int) round((float) RowAccessor::string($row, 'total_paid'));

            $items[] = new InvoiceListRow(
                id: RowAccessor::string($row, 'id'),
                number: RowAccessor::nullableString($row, 'number'),
                status: RowAccessor::string($row, 'status'),
                payerClientId: RowAccessor::nullableString($row, 'payer_client_id'),
                payerName: RowAccessor::string($row, 'payer_name'),
                currency: RowAccessor::string($row, 'currency'),
                totalGrossMinorUnits: $gross,
                amountPaidMinorUnits: $paid,
                balanceDueMinorUnits: $gross - $paid,
                createdAt: new \DateTimeImmutable(RowAccessor::string($row, 'created_at')),
                issuedAt: null !== RowAccessor::nullableString($row, 'issued_at')
                    ? new \DateTimeImmutable(RowAccessor::string($row, 'issued_at'))
                    : null,
                admissionId: RowAccessor::nullableString($row, 'admission_id'),
            );
        }

        return ['items' => $items, 'total' => $total];
    }

    public function forAdmission(ClinicId $clinicId, AdmissionId $admissionId): ?AdmissionInvoiceRow
    {
        $row = $this->connection->fetchAssociative(
            \sprintf(
                'SELECT BIN_TO_UUID(i.id) AS id, i.number, i.status, i.currency,
                        i.discount_kind, i.discount_value,
                        %s AS total_net, %s AS total_tax
                 FROM billing__invoices i
                 WHERE i.clinic_id = :clinicId AND i.admission_id = :admissionId
                 ORDER BY i.created_at DESC
                 LIMIT 1',
                $this->netExpression(),
                $this->taxExpression(),
            ),
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
            totalGrossMinorUnits: $this->grossAfterDiscount($row),
            currency: RowAccessor::string($row, 'currency'),
        );
    }

    public function stats(ClinicId $clinicId, \DateTimeImmutable $from, \DateTimeImmutable $to): BillingStats
    {
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

        $outstanding = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT i.discount_kind, i.discount_value,
                        %s AS total_net, %s AS total_tax, %s AS total_paid
                 FROM billing__invoices i
                 WHERE i.clinic_id = :clinicId AND i.status IN (:statuses)',
                $this->netExpression(),
                $this->taxExpression(),
                $this->paidExpression(),
            ),
            [
                'clinicId' => $clinicBinary,
                'statuses' => [InvoiceStatus::ISSUED->value, InvoiceStatus::PARTIALLY_PAID->value],
            ],
            ['statuses' => ArrayParameterType::STRING],
        );

        $due   = 0;
        $count = 0;

        foreach ($outstanding as $row) {
            $gross = $this->grossAfterDiscount($row);
            $paid  = (int) round((float) RowAccessor::string($row, 'total_paid'));

            if ($gross > $paid) {
                $due += $gross - $paid;
                ++$count;
            }
        }

        return new BillingStats(
            collectedMinorUnits: is_numeric($collectedRaw) ? (int) $collectedRaw : 0,
            outstandingMinorUnits: $due,
            issuedCount: is_numeric($issuedRaw) ? (int) $issuedRaw : 0,
            outstandingCount: $count,
            currency: self::DEFAULT_CURRENCY,
        );
    }

    public function outstandingByPayer(ClinicId $clinicId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT i.payer_client_id, i.discount_kind, i.discount_value,
                        %s AS total_net, %s AS total_tax, %s AS total_paid
                 FROM billing__invoices i
                 WHERE i.clinic_id = :clinicId AND i.status IN (:statuses) AND i.payer_client_id IS NOT NULL',
                $this->netExpression(),
                $this->taxExpression(),
                $this->paidExpression(),
            ),
            [
                'clinicId' => Uuid::fromString($clinicId->toString())->toBinary(),
                'statuses' => [InvoiceStatus::ISSUED->value, InvoiceStatus::PARTIALLY_PAID->value],
            ],
            ['statuses' => ArrayParameterType::STRING],
        );

        $balances = [];

        foreach ($rows as $row) {
            $due = $this->grossAfterDiscount($row) - (int) round((float) RowAccessor::string($row, 'total_paid'));

            if ($due > 0) {
                $payer            = RowAccessor::uuid($row, 'payer_client_id');
                $balances[$payer] = ($balances[$payer] ?? 0) + $due;
            }
        }

        return $balances;
    }

    /**
     * @return array{
     *     where: string,
     *     params: array<string, mixed>,
     *     types: array<string, ArrayParameterType>
     * }
     */
    private function buildFilter(ClinicId $clinicId, SearchInvoicesCriteria $criteria): array
    {
        $where  = ['i.clinic_id = :clinicId'];
        $params = ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()];
        $types  = [];

        if (null !== $criteria->term && '' !== $criteria->term) {
            $where[]        = '(i.payer_name LIKE :term OR i.number LIKE :term)';
            $params['term'] = '%' . $criteria->term . '%';
        }

        if ([] !== $criteria->statuses) {
            $where[]            = 'i.status IN (:statuses)';
            $params['statuses'] = $criteria->statuses;
            $types['statuses']  = ArrayParameterType::STRING;
        }

        if (null !== $criteria->issuedFrom) {
            $where[]        = 'COALESCE(i.issued_at, i.created_at) >= :from';
            $params['from'] = $criteria->issuedFrom->format('Y-m-d H:i:s');
        }

        if (null !== $criteria->issuedTo) {
            $where[]      = 'COALESCE(i.issued_at, i.created_at) <= :to';
            $params['to'] = $criteria->issuedTo->format('Y-m-d H:i:s');
        }

        if (null !== $criteria->payerClientId) {
            $where[]         = 'i.payer_client_id = :payer';
            $params['payer'] = Uuid::fromString($criteria->payerClientId)->toBinary();
        }

        return ['where' => implode(' AND ', $where), 'params' => $params, 'types' => $types];
    }

    private function netExpression(): string
    {
        return '(SELECT COALESCE(SUM(l.net_minor_units), 0)
                 FROM billing__invoice_lines l WHERE l.invoice_id = i.id)';
    }

    private function taxExpression(): string
    {
        return '(SELECT COALESCE(SUM(l.tax_minor_units), 0)
                 FROM billing__invoice_lines l WHERE l.invoice_id = i.id)';
    }

    private function paidExpression(): string
    {
        return '(SELECT COALESCE(SUM(pm.amount_minor_units), 0)
                 FROM billing__payments pm WHERE pm.invoice_id = i.id)';
    }

    /**
     * The lines are stored before any discount, so the same arithmetic the
     * aggregate performs is replayed here — through the very same value object,
     * so the list can never disagree with the invoice it links to.
     *
     * @param array<string, mixed> $row
     */
    private function grossAfterDiscount(array $row): int
    {
        $net = (int) round((float) RowAccessor::string($row, 'total_net'));
        $tax = (int) round((float) RowAccessor::string($row, 'total_tax'));

        $kind  = RowAccessor::nullableString($row, 'discount_kind');
        $value = RowAccessor::nullableString($row, 'discount_value');

        if (null === $kind || null === $value || 0 === $net) {
            return $net + $tax;
        }

        $off = Discount::reconstitute(DiscountKind::from($kind), $value, null)->amountOff($net);

        return ($net - $off) + (int) round($tax * ($net - $off) / $net);
    }
}
