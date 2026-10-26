<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Billing\Domain\Dispatch\DispatchKind;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatch;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchId;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * The dispatch log, in SQL; times are stored in UTC.
 */
final readonly class DbalInvoiceDispatchRepository implements InvoiceDispatchRepositoryInterface
{
    private const string TABLE  = 'billing__invoice_dispatches';
    private const string FORMAT = 'Y-m-d H:i:s';

    private const string COLUMNS = 'BIN_TO_UUID(id) AS id, BIN_TO_UUID(clinic_id) AS clinic_id, BIN_TO_UUID(invoice_id) AS invoice_id,'
        . ' kind, channels, notification_ids, BIN_TO_UUID(author_user_id) AS author_user_id, created_at';

    public function __construct(private Connection $connection)
    {
    }

    public function save(InvoiceDispatch $dispatch): void
    {
        $author = $dispatch->authorUserId();

        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (id, clinic_id, invoice_id, kind, channels, notification_ids, author_user_id, created_at)'
            . ' VALUES (:id, :clinic, :invoice, :kind, :channels, :notifications, :author, :at)'
            . ' ON DUPLICATE KEY UPDATE notification_ids = VALUES(notification_ids)',
            [
                'id'            => self::binary($dispatch->id()->toString()),
                'clinic'        => self::binary($dispatch->clinicId()->toString()),
                'invoice'       => self::binary($dispatch->invoiceId()->toString()),
                'kind'          => $dispatch->kind()->value,
                'channels'      => json_encode($dispatch->channels(), \JSON_THROW_ON_ERROR),
                'notifications' => json_encode($dispatch->notificationIds(), \JSON_THROW_ON_ERROR),
                'author'        => null !== $author ? self::binary($author) : null,
                'at'            => self::utc($dispatch->createdAt()),
            ],
        );
    }

    public function find(InvoiceDispatchId $id): ?InvoiceDispatch
    {
        $row = $this->connection->fetchAssociative(
            'SELECT ' . self::COLUMNS . ' FROM ' . self::TABLE . ' WHERE id = :id',
            ['id' => self::binary($id->toString())],
        );

        return false === $row ? null : self::dispatch($row);
    }

    public function listForInvoice(ClinicId $clinicId, InvoiceId $invoiceId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT ' . self::COLUMNS . ' FROM ' . self::TABLE
            . ' WHERE clinic_id = :clinic AND invoice_id = :invoice ORDER BY created_at ASC, id ASC',
            ['clinic' => self::binary($clinicId->toString()), 'invoice' => self::binary($invoiceId->toString())],
        );

        return array_map(self::dispatch(...), $rows);
    }

    public function countReminders(ClinicId $clinicId, InvoiceId $invoiceId): int
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE clinic_id = :clinic AND invoice_id = :invoice AND kind = :kind',
            ['clinic' => self::binary($clinicId->toString()), 'invoice' => self::binary($invoiceId->toString()), 'kind' => DispatchKind::REMINDER->value],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    public function lastReminderAt(ClinicId $clinicId, InvoiceId $invoiceId): ?\DateTimeImmutable
    {
        $at = $this->connection->fetchOne(
            'SELECT MAX(created_at) FROM ' . self::TABLE . ' WHERE clinic_id = :clinic AND invoice_id = :invoice AND kind = :kind',
            ['clinic' => self::binary($clinicId->toString()), 'invoice' => self::binary($invoiceId->toString()), 'kind' => DispatchKind::REMINDER->value],
        );

        return \is_string($at) ? new \DateTimeImmutable($at, new \DateTimeZone('UTC')) : null;
    }

    public function remindedSince(ClinicId $clinicId, \DateTimeImmutable $since): array
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT BIN_TO_UUID(invoice_id) FROM ' . self::TABLE . ' WHERE clinic_id = :clinic AND kind = :kind AND created_at >= :since',
            ['clinic' => self::binary($clinicId->toString()), 'kind' => DispatchKind::REMINDER->value, 'since' => self::utc($since)],
        );

        return array_values(array_filter($ids, \is_string(...)));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function dispatch(array $row): InvoiceDispatch
    {
        return InvoiceDispatch::reconstitute(
            id: InvoiceDispatchId::fromString(RowAccessor::string($row, 'id')),
            clinicId: ClinicId::fromString(RowAccessor::string($row, 'clinic_id')),
            invoiceId: InvoiceId::fromString(RowAccessor::string($row, 'invoice_id')),
            kind: DispatchKind::from(RowAccessor::string($row, 'kind')),
            channels: self::strings(RowAccessor::string($row, 'channels')),
            notificationIds: self::strings(RowAccessor::string($row, 'notification_ids')),
            authorUserId: RowAccessor::nullableString($row, 'author_user_id'),
            createdAt: new \DateTimeImmutable(RowAccessor::string($row, 'created_at'), new \DateTimeZone('UTC')),
        );
    }

    /**
     * @return list<string>
     */
    private static function strings(string $json): array
    {
        $list = json_decode($json, true, 4, \JSON_THROW_ON_ERROR);

        return \is_array($list) ? array_values(array_filter($list, \is_string(...))) : [];
    }

    private static function binary(string $uuid): string
    {
        return Uuid::fromString($uuid)->toBinary();
    }

    private static function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format(self::FORMAT);
    }
}
