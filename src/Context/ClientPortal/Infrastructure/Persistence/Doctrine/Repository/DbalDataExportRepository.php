<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Export\DataExport;
use App\Context\ClientPortal\Domain\Export\DataExportId;
use App\Context\ClientPortal\Domain\Export\DataExportRepositoryInterface;
use App\Context\ClientPortal\Domain\Export\DataExportStatus;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/** One row per export request, never upserted; dates are written in UTC. */
final readonly class DbalDataExportRepository implements DataExportRepositoryInterface
{
    public const string TABLE = 'client_portal__data_exports';

    public function __construct(private Connection $connection)
    {
    }

    public function add(DataExport $export): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (id, user_id, status, requested_at, ready_at, expires_at, failed_at)'
            . ' VALUES (:id, :user, :status, :requested, :ready, :expires, :failed)',
            [
                'id'        => SqlValues::binary($export->id()->toString()),
                'user'      => SqlValues::binary($export->userId()),
                'status'    => $export->status()->value,
                'requested' => SqlValues::utc($export->requestedAt()),
                'ready'     => SqlValues::nullableUtc($export->readyAt()),
                'expires'   => SqlValues::nullableUtc($export->expiresAt()),
                'failed'    => SqlValues::nullableUtc($export->failedAt()),
            ],
        );
    }

    public function update(DataExport $export): void
    {
        $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET status = :status, ready_at = :ready, expires_at = :expires, failed_at = :failed WHERE id = :id',
            [
                'id'      => SqlValues::binary($export->id()->toString()),
                'status'  => $export->status()->value,
                'ready'   => SqlValues::nullableUtc($export->readyAt()),
                'expires' => SqlValues::nullableUtc($export->expiresAt()),
                'failed'  => SqlValues::nullableUtc($export->failedAt()),
            ],
        );
    }

    public function findById(string $exportId): ?DataExport
    {
        return $this->one($exportId, '');
    }

    public function findByIdForUpdate(string $exportId): ?DataExport
    {
        return $this->one($exportId, ' FOR UPDATE');
    }

    public function findByUser(string $userId): array
    {
        return $this->list('user_id = :user ORDER BY requested_at DESC, id DESC', ['user' => SqlValues::binary($userId)]);
    }

    public function findByUserForUpdate(string $userId): array
    {
        return $this->list('user_id = :user ORDER BY requested_at DESC, id DESC FOR UPDATE', ['user' => SqlValues::binary($userId)]);
    }

    public function findReadyExpiredForUpdate(\DateTimeImmutable $now, int $limit): array
    {
        return $this->list(
            'status = :ready AND expires_at <= :now ORDER BY expires_at ASC, id ASC LIMIT ' . $limit . ' FOR UPDATE SKIP LOCKED',
            ['ready' => DataExportStatus::READY->value, 'now' => SqlValues::utc($now)],
        );
    }

    public function findPendingRequestedBeforeForUpdate(\DateTimeImmutable $requestedBefore, int $limit): array
    {
        return $this->list(
            'status = :pending AND requested_at < :before ORDER BY requested_at ASC, id ASC LIMIT ' . $limit . ' FOR UPDATE SKIP LOCKED',
            ['pending' => DataExportStatus::PENDING->value, 'before' => SqlValues::utc($requestedBefore)],
        );
    }

    public function remove(DataExport $export): void
    {
        $this->connection->executeStatement('DELETE FROM ' . self::TABLE . ' WHERE id = :id', ['id' => SqlValues::binary($export->id()->toString())]);
    }

    private function one(string $exportId, string $lock): ?DataExport
    {
        if (!Uuid::isValid($exportId)) {
            return null;
        }

        $row = $this->connection->fetchAssociative('SELECT * FROM ' . self::TABLE . ' WHERE id = :id' . $lock, ['id' => SqlValues::binary($exportId)]);

        return false === $row ? null : self::hydrate($row);
    }

    /**
     * @param array<string, string> $parameters
     *
     * @return list<DataExport>
     */
    private function list(string $where, array $parameters): array
    {
        return array_map(self::hydrate(...), $this->connection->fetchAllAssociative('SELECT * FROM ' . self::TABLE . ' WHERE ' . $where, $parameters));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): DataExport
    {
        return DataExport::reconstitute(
            id: DataExportId::fromString(RowAccessor::uuid($row, 'id')),
            userId: RowAccessor::uuid($row, 'user_id'),
            status: DataExportStatus::from(RowAccessor::string($row, 'status')),
            requestedAt: SqlValues::date(RowAccessor::string($row, 'requested_at')),
            readyAt: SqlValues::nullableDate(RowAccessor::nullableString($row, 'ready_at')),
            expiresAt: SqlValues::nullableDate(RowAccessor::nullableString($row, 'expires_at')),
            failedAt: SqlValues::nullableDate(RowAccessor::nullableString($row, 'failed_at')),
        );
    }
}
