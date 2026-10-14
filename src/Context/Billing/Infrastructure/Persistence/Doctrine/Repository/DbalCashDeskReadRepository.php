<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Billing\Application\Port\CashDeskReadRepositoryInterface;
use App\Context\Billing\Application\Query\GetCashDesk\CashClosingView;
use App\Context\Billing\Application\Query\GetCashDesk\ChequeView;
use App\Context\Billing\Application\Query\GetCashDesk\TakingsView;
use App\Context\Billing\Application\Query\GetChequeDeposit\ChequeDepositView;
use App\Context\Billing\Domain\ValueObject\ChequeDepositId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalCashDeskReadRepository implements CashDeskReadRepositoryInterface
{
    private const string CHEQUE_COLUMNS = 'BIN_TO_UUID(p.id) AS payment_id, BIN_TO_UUID(p.invoice_id) AS invoice_id,
        i.number, i.payer_name, p.reference, p.amount_minor_units, p.currency, p.received_at';

    public function __construct(private Connection $connection)
    {
    }

    public function pendingCheques(ClinicId $clinicId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT ' . self::CHEQUE_COLUMNS . '
             FROM billing__payments p
             INNER JOIN billing__invoices i ON i.id = p.invoice_id
             WHERE p.clinic_id = :clinicId AND p.method = :method
               AND NOT EXISTS (SELECT 1 FROM billing__cheque_deposit_payments dp WHERE dp.payment_id = p.id)
             ORDER BY p.received_at ASC, p.position ASC',
            ['clinicId' => $this->binary($clinicId->toString()), 'method' => PaymentMethod::CHECK->value],
        );

        return array_map(fn (array $row): ChequeView => $this->cheque($row), $rows);
    }

    public function takings(ClinicId $clinicId, \DateTimeImmutable $from, \DateTimeImmutable $to): TakingsView
    {
        $params = [
            'clinicId' => $this->binary($clinicId->toString()),
            'from'     => $from->format('Y-m-d H:i:s'),
            'to'       => $to->format('Y-m-d H:i:s'),
        ];

        $collected = $this->connection->fetchAllKeyValue(
            'SELECT p.method, SUM(p.amount_minor_units)
             FROM billing__payments p
             WHERE p.clinic_id = :clinicId AND p.received_at >= :from AND p.received_at <= :to
             GROUP BY p.method',
            $params,
        );

        $refunded = $this->connection->fetchAllKeyValue(
            'SELECT c.refund_method, SUM(c.refunded_minor_units)
             FROM billing__credit_notes c
             WHERE c.clinic_id = :clinicId AND c.refund_method IS NOT NULL
               AND c.issued_at >= :from AND c.issued_at <= :to
             GROUP BY c.refund_method',
            $params,
        );

        return new TakingsView($this->amounts($collected), $this->amounts($refunded));
    }

    public function chequeDeposit(ClinicId $clinicId, ChequeDepositId $depositId): ?ChequeDepositView
    {
        $deposit = $this->connection->fetchAssociative(
            'SELECT total_minor_units, currency, deposited_at FROM billing__cheque_deposits
             WHERE id = :id AND clinic_id = :clinicId',
            ['id' => $this->binary($depositId->toString()), 'clinicId' => $this->binary($clinicId->toString())],
        );

        if (false === $deposit) {
            return null;
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT ' . self::CHEQUE_COLUMNS . '
             FROM billing__cheque_deposit_payments dp
             INNER JOIN billing__payments p ON p.id = dp.payment_id
             INNER JOIN billing__invoices i ON i.id = p.invoice_id
             WHERE dp.deposit_id = :id
             ORDER BY p.received_at ASC, p.position ASC',
            ['id' => $this->binary($depositId->toString())],
        );

        return new ChequeDepositView(
            id: $depositId->toString(),
            depositedAt: new \DateTimeImmutable(RowAccessor::string($deposit, 'deposited_at')),
            totalMinorUnits: RowAccessor::int($deposit, 'total_minor_units'),
            currency: RowAccessor::string($deposit, 'currency'),
            cheques: array_map(fn (array $row): ChequeView => $this->cheque($row), $rows),
        );
    }

    public function closingFor(ClinicId $clinicId, string $businessDate): ?CashClosingView
    {
        $row = $this->connection->fetchAssociative(
            'SELECT number, business_date, closed_at FROM billing__cash_closings
             WHERE clinic_id = :clinicId AND business_date = :day',
            ['clinicId' => $this->binary($clinicId->toString()), 'day' => $businessDate],
        );

        if (false === $row) {
            return null;
        }

        return new CashClosingView(
            number: RowAccessor::int($row, 'number'),
            businessDate: RowAccessor::string($row, 'business_date'),
            closedAt: new \DateTimeImmutable(RowAccessor::string($row, 'closed_at')),
        );
    }

    public function closingCount(ClinicId $clinicId): int
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM billing__cash_closings WHERE clinic_id = :clinicId',
            ['clinicId' => $this->binary($clinicId->toString())],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function cheque(array $row): ChequeView
    {
        return new ChequeView(
            paymentId: RowAccessor::string($row, 'payment_id'),
            invoiceId: RowAccessor::string($row, 'invoice_id'),
            invoiceNumber: RowAccessor::nullableString($row, 'number'),
            payerName: RowAccessor::string($row, 'payer_name'),
            reference: RowAccessor::nullableString($row, 'reference'),
            amountMinorUnits: RowAccessor::int($row, 'amount_minor_units'),
            currency: RowAccessor::string($row, 'currency'),
            receivedAt: new \DateTimeImmutable(RowAccessor::string($row, 'received_at')),
        );
    }

    /**
     * @param array<mixed, mixed> $pairs
     *
     * @return array<string, int>
     */
    private function amounts(array $pairs): array
    {
        $amounts = [];

        foreach ($pairs as $method => $amount) {
            $amounts[(string) $method] = is_numeric($amount) ? (int) $amount : 0;
        }

        return $amounts;
    }

    private function binary(string $uuid): string
    {
        return Uuid::fromString($uuid)->toBinary();
    }
}
