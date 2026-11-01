<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Export\DataExport;
use App\Context\ClientPortal\Domain\Export\DataExportId;
use App\Context\ClientPortal\Domain\Export\DataExportRepositoryInterface;
use App\Context\ClientPortal\Domain\Export\DataExportStatus;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

/** One row per export request; dates are written in UTC. */
final readonly class DbalDataExportRepository implements DataExportRepositoryInterface
{
    public const string TABLE = 'client_portal__data_exports';

    public function __construct(private Connection $connection)
    {
    }

    public function save(DataExport $export): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (id, user_id, status, requested_at, ready_at, expires_at, failed_at)'
            . ' VALUES (:id, :user, :status, :requested, :ready, :expires, :failed)'
            . ' AS new ON DUPLICATE KEY UPDATE status = new.status, ready_at = new.ready_at, expires_at = new.expires_at, failed_at = new.failed_at',
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

    public function findById(string $exportId): ?DataExport
    {
        if (!Uuid::isValid($exportId)) {
            return null;
        }

        $row = $this->connection->fetchAssociative('SELECT * FROM ' . self::TABLE . ' WHERE id = :id', ['id' => SqlValues::binary($exportId)]);

        return false === $row ? null : self::hydrate($row);
    }

    public function findByUser(string $userId): array
    {
        return array_map(self::hydrate(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE user_id = :user ORDER BY requested_at DESC, id DESC',
            ['user' => SqlValues::binary($userId)],
        ));
    }

    public function findReadyExpiredAt(\DateTimeImmutable $now, int $limit): array
    {
        return array_map(self::hydrate(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE status = :ready AND expires_at <= :now ORDER BY expires_at ASC, id ASC LIMIT :limit',
            ['ready' => DataExportStatus::READY->value, 'now' => SqlValues::utc($now), 'limit' => $limit],
            ['limit' => ParameterType::INTEGER],
        ));
    }

    public function remove(DataExport $export): void
    {
        $this->connection->executeStatement('DELETE FROM ' . self::TABLE . ' WHERE id = :id', ['id' => SqlValues::binary($export->id()->toString())]);
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
