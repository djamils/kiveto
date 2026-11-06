<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\IdentityAccess\Application\Port\UserSessionRecord;
use App\System\IdentityAccess\Application\Port\UserSessionStoreInterface;
use App\System\IdentityAccess\Application\Query\ListUserSessions\UserSessionView;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalUserSessionStore implements UserSessionStoreInterface
{
    private const string TABLE = 'identity_access__user_sessions';

    public function __construct(private Connection $connection)
    {
    }

    public function find(string $sessionHash): ?UserSessionRecord
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_id, last_seen_at, revoked_at, ended_at FROM ' . self::TABLE . ' WHERE session_hash = :hash',
            ['hash' => $sessionHash],
        );

        if (false === $row) {
            return null;
        }

        return new UserSessionRecord(
            id: RowAccessor::uuid($row, 'id'),
            userId: RowAccessor::uuid($row, 'user_id'),
            lastSeenAt: self::date(RowAccessor::string($row, 'last_seen_at')),
            revoked: null !== RowAccessor::nullableString($row, 'revoked_at') || null !== RowAccessor::nullableString($row, 'ended_at'),
        );
    }

    public function open(string $id, string $userId, string $sessionHash, ?string $userAgent, ?string $ipAddress, \DateTimeImmutable $at): void
    {
        // A replayed first request of the same session finds its row there.
        $this->connection->executeStatement(
            'INSERT IGNORE INTO ' . self::TABLE . ' (id, user_id, session_hash, user_agent, ip_address, created_at, last_seen_at)'
            . ' VALUES (:id, :user, :hash, :agent, :ip, :at, :at)',
            [
                'id'    => Uuid::fromString($id)->toBinary(),
                'user'  => Uuid::fromString($userId)->toBinary(),
                'hash'  => $sessionHash,
                'agent' => $userAgent,
                'ip'    => $ipAddress,
                'at'    => self::utc($at),
            ],
        );
    }

    public function touch(string $sessionHash, ?string $ipAddress, \DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET last_seen_at = :at, ip_address = COALESCE(:ip, ip_address) WHERE session_hash = :hash',
            ['at' => self::utc($at), 'ip' => $ipAddress, 'hash' => $sessionHash],
        );
    }

    public function end(string $sessionHash, \DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET ended_at = :at WHERE session_hash = :hash AND ended_at IS NULL',
            ['at' => self::utc($at), 'hash' => $sessionHash],
        );
    }

    public function revoke(string $userId, string $id, \DateTimeImmutable $at): bool
    {
        return 1 === (int) $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET revoked_at = :at WHERE id = :id AND user_id = :user AND revoked_at IS NULL AND ended_at IS NULL',
            ['at' => self::utc($at), 'id' => Uuid::fromString($id)->toBinary(), 'user' => Uuid::fromString($userId)->toBinary()],
        );
    }

    public function revokeAll(string $userId, ?string $exceptSessionHash, \DateTimeImmutable $at): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET revoked_at = :at WHERE user_id = :user AND revoked_at IS NULL AND ended_at IS NULL'
            . (null !== $exceptSessionHash ? ' AND session_hash <> :keep' : ''),
            array_filter(
                ['at' => self::utc($at), 'user' => Uuid::fromString($userId)->toBinary(), 'keep' => $exceptSessionHash],
                static fn (?string $value): bool => null !== $value,
            ),
        );
    }

    public function listActive(string $userId, \DateTimeImmutable $since, ?string $currentSessionHash): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE user_id = :user AND revoked_at IS NULL AND ended_at IS NULL AND last_seen_at >= :since'
            . ' ORDER BY last_seen_at DESC, id DESC',
            ['user' => Uuid::fromString($userId)->toBinary(), 'since' => self::utc($since)],
        );

        return array_map(static fn (array $row): UserSessionView => new UserSessionView(
            id: RowAccessor::uuid($row, 'id'),
            userAgent: RowAccessor::nullableString($row, 'user_agent'),
            ipAddress: RowAccessor::nullableString($row, 'ip_address'),
            createdAt: self::date(RowAccessor::string($row, 'created_at')),
            lastSeenAt: self::date(RowAccessor::string($row, 'last_seen_at')),
            current: null !== $currentSessionHash && $currentSessionHash === RowAccessor::string($row, 'session_hash'),
        ), $rows);
    }

    private static function date(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }

    private static function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
