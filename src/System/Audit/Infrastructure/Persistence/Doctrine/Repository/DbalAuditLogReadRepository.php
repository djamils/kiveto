<?php

declare(strict_types=1);

namespace App\System\Audit\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\Audit\Application\Port\AuditLogCriteria;
use App\System\Audit\Application\Port\AuditLogReadRepositoryInterface;
use App\System\Audit\Application\Query\AuditDetailView;
use App\System\Audit\Application\Query\AuditEntryView;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalAuditLogReadRepository implements AuditLogReadRepositoryInterface
{
    private const string TABLE = DbalAuditEntryRepository::TABLE;

    private const string ORDER = ' ORDER BY occurred_at DESC, id DESC';

    public function __construct(private Connection $connection)
    {
    }

    public function search(AuditLogCriteria $criteria, int $offset, int $limit): array
    {
        [$where, $params] = $this->where($criteria);

        return $this->views(
            'SELECT * FROM ' . self::TABLE . $where . self::ORDER . ' LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset),
            $params,
        );
    }

    public function count(AuditLogCriteria $criteria): int
    {
        [$where, $params] = $this->where($criteria);

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE . $where, $params);

        return is_numeric($count) ? (int) $count : 0;
    }

    public function forClinic(string $clinicId, int $limit): array
    {
        return $this->search(new AuditLogCriteria(clinicId: $clinicId), 0, $limit);
    }

    public function forUser(string $userId, int $limit): array
    {
        return $this->search(new AuditLogCriteria(userId: $userId), 0, $limit);
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function where(AuditLogCriteria $criteria): array
    {
        $conditions = [];
        $params     = [];

        if (null !== $criteria->category) {
            $conditions[]       = 'category = :category';
            $params['category'] = $criteria->category->value;
        }

        if (null !== $criteria->clinicId) {
            $conditions[]     = 'clinic_id = :clinic';
            $params['clinic'] = Uuid::fromString($criteria->clinicId)->toBinary();
        }

        if (null !== $criteria->actorKind) {
            $conditions[]   = 'actor_kind = :kind';
            $params['kind'] = $criteria->actorKind->value;
        }

        if (null !== $criteria->userId) {
            $conditions[]   = '(actor_id = :user OR target_user_id = :user)';
            $params['user'] = Uuid::fromString($criteria->userId)->toBinary();
        }

        if (null !== $criteria->since) {
            $conditions[]    = 'occurred_at >= :since';
            $params['since'] = $criteria->since->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }

        if (null !== $criteria->text) {
            $conditions[]   = '(summary LIKE :text OR action LIKE :text OR actor_name LIKE :text OR clinic_name LIKE :text)';
            $params['text'] = '%' . addcslashes($criteria->text, '%_\\') . '%';
        }

        return [[] === $conditions ? '' : ' WHERE ' . implode(' AND ', $conditions), $params];
    }

    /**
     * @param array<string, string> $params
     *
     * @return list<AuditEntryView>
     */
    private function views(string $sql, array $params): array
    {
        return array_map($this->view(...), $this->connection->fetchAllAssociative($sql, $params));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function view(array $row): AuditEntryView
    {
        return new AuditEntryView(
            id: RowAccessor::uuid($row, 'id'),
            occurredAt: new \DateTimeImmutable(RowAccessor::string($row, 'occurred_at'), new \DateTimeZone('UTC')),
            actorKind: RowAccessor::string($row, 'actor_kind'),
            actorId: RowAccessor::nullableUuid($row, 'actor_id'),
            actorName: RowAccessor::nullableString($row, 'actor_name'),
            action: RowAccessor::string($row, 'action'),
            category: RowAccessor::string($row, 'category'),
            summary: RowAccessor::string($row, 'summary'),
            clinicId: RowAccessor::nullableUuid($row, 'clinic_id'),
            clinicName: RowAccessor::nullableString($row, 'clinic_name'),
            targetUserId: RowAccessor::nullableUuid($row, 'target_user_id'),
            ipAddress: RowAccessor::nullableString($row, 'ip_address'),
            details: $this->details(RowAccessor::string($row, 'details')),
        );
    }

    /**
     * @return list<AuditDetailView>
     */
    private function details(string $json): array
    {
        $decoded = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        $details = [];

        foreach (\is_array($decoded) ? $decoded : [] as $detail) {
            if (\is_array($detail) && \is_string($detail['label'] ?? null) && \is_string($detail['value'] ?? null)) {
                $details[] = new AuditDetailView($detail['label'], $detail['value']);
            }
        }

        return $details;
    }
}
