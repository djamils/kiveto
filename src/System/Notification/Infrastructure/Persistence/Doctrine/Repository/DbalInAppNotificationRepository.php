<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\Notification\Domain\Inbox\InAppKind;
use App\System\Notification\Domain\Inbox\InAppNotification;
use App\System\Notification\Domain\Inbox\InAppNotificationId;
use App\System\Notification\Domain\Inbox\InAppNotificationRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Dates are written in UTC. An entry is added with INSERT IGNORE on the
 * (idempotency key, user) unique index: a replayed post writes nothing.
 */
final readonly class DbalInAppNotificationRepository implements InAppNotificationRepositoryInterface
{
    private const string TABLE = 'notification__in_app_notifications';

    public function __construct(private Connection $connection)
    {
    }

    public function add(InAppNotification $notification): bool
    {
        return 1 === (int) $this->connection->executeStatement(
            'INSERT IGNORE INTO ' . self::TABLE . ' (id, clinic_id, user_id, kind, title, detail, route, route_params, actor_initials, idempotency_key, created_at, read_at)'
            . ' VALUES (:id, :clinic, :user, :kind, :title, :detail, :route, :params, :initials, :key, :created, NULL)',
            [
                'id'       => Uuid::fromString($notification->id()->toString())->toBinary(),
                'clinic'   => Uuid::fromString($notification->clinicId())->toBinary(),
                'user'     => Uuid::fromString($notification->userId())->toBinary(),
                'kind'     => $notification->kind()->value,
                'title'    => $notification->title(),
                'detail'   => $notification->detail(),
                'route'    => $notification->route(),
                'params'   => json_encode((object) $notification->routeParams(), \JSON_THROW_ON_ERROR),
                'initials' => $notification->actorInitials(),
                'key'      => $notification->idempotencyKey(),
                'created'  => self::utc($notification->createdAt()),
            ],
        );
    }

    public function markRead(InAppNotificationId $id, string $userId, \DateTimeImmutable $at): bool
    {
        return 1 === (int) $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET read_at = :at WHERE id = :id AND user_id = :user AND read_at IS NULL',
            ['at' => self::utc($at), 'id' => Uuid::fromString($id->toString())->toBinary(), 'user' => Uuid::fromString($userId)->toBinary()],
        );
    }

    public function markAllRead(string $userId, string $clinicId, \DateTimeImmutable $at): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET read_at = :at WHERE user_id = :user AND clinic_id = :clinic AND read_at IS NULL',
            ['at' => self::utc($at), 'user' => Uuid::fromString($userId)->toBinary(), 'clinic' => Uuid::fromString($clinicId)->toBinary()],
        );
    }

    public function withdrawUnread(string $idempotencyKey): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE idempotency_key = :key AND read_at IS NULL',
            ['key' => $idempotencyKey],
        );
    }

    public function listFor(string $userId, string $clinicId, \DateTimeImmutable $since, ?int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE user_id = :user AND clinic_id = :clinic AND created_at >= :since'
            . ' ORDER BY created_at DESC, id DESC' . (null !== $limit ? ' LIMIT ' . max(1, $limit) : ''),
            ['user' => Uuid::fromString($userId)->toBinary(), 'clinic' => Uuid::fromString($clinicId)->toBinary(), 'since' => self::utc($since)],
        );

        return array_map(self::notification(...), $rows);
    }

    public function countUnread(string $userId, string $clinicId, \DateTimeImmutable $since): int
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE user_id = :user AND clinic_id = :clinic AND created_at >= :since AND read_at IS NULL',
            ['user' => Uuid::fromString($userId)->toBinary(), 'clinic' => Uuid::fromString($clinicId)->toBinary(), 'since' => self::utc($since)],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    public function purgeCreatedBefore(\DateTimeImmutable $before): int
    {
        return (int) $this->connection->executeStatement('DELETE FROM ' . self::TABLE . ' WHERE created_at < :before', ['before' => self::utc($before)]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function notification(array $row): InAppNotification
    {
        $params = json_decode(RowAccessor::string($row, 'route_params'), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($params));
        $readAt = RowAccessor::nullableString($row, 'read_at');

        return InAppNotification::reconstitute(
            id: InAppNotificationId::fromString(RowAccessor::uuid($row, 'id')),
            clinicId: RowAccessor::uuid($row, 'clinic_id'),
            userId: RowAccessor::uuid($row, 'user_id'),
            kind: InAppKind::from(RowAccessor::string($row, 'kind')),
            title: RowAccessor::string($row, 'title'),
            detail: RowAccessor::string($row, 'detail'),
            route: RowAccessor::string($row, 'route'),
            routeParams: array_map(static fn (mixed $v): string => \is_scalar($v) ? (string) $v : '', array_filter($params, \is_string(...), \ARRAY_FILTER_USE_KEY)),
            actorInitials: RowAccessor::nullableString($row, 'actor_initials'),
            idempotencyKey: RowAccessor::string($row, 'idempotency_key'),
            createdAt: new \DateTimeImmutable(RowAccessor::string($row, 'created_at'), new \DateTimeZone('UTC')),
            readAt: null !== $readAt ? new \DateTimeImmutable($readAt, new \DateTimeZone('UTC')) : null,
        );
    }

    private static function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
