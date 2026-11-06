<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\IdentityAccess\Domain\SecondFactor\TrustedDevice;
use App\System\IdentityAccess\Domain\SecondFactor\TrustedDeviceRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalTrustedDeviceRepository implements TrustedDeviceRepositoryInterface
{
    private const string TABLE = 'identity_access__trusted_devices';

    public function __construct(private Connection $connection)
    {
    }

    public function add(TrustedDevice $device): void
    {
        $this->connection->insert(self::TABLE, [
            'id'         => Uuid::fromString($device->id())->toBinary(),
            'user_id'    => Uuid::fromString($device->userId()->toString())->toBinary(),
            'token_hash' => $device->tokenHash(),
            'label'      => $device->label(),
            'created_at' => self::utc($device->createdAt()),
            'expires_at' => self::utc($device->expiresAt()),
            'revoked_at' => null !== $device->revokedAt() ? self::utc($device->revokedAt()) : null,
        ]);
    }

    public function findByTokenHash(string $tokenHash): ?TrustedDevice
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM ' . self::TABLE . ' WHERE token_hash = :hash', ['hash' => $tokenHash]);

        if (false === $row) {
            return null;
        }

        $revokedAt = RowAccessor::nullableString($row, 'revoked_at');

        return TrustedDevice::reconstitute(
            id: RowAccessor::uuid($row, 'id'),
            userId: UserId::fromString(RowAccessor::uuid($row, 'user_id')),
            tokenHash: RowAccessor::string($row, 'token_hash'),
            label: RowAccessor::string($row, 'label'),
            createdAt: self::date(RowAccessor::string($row, 'created_at')),
            expiresAt: self::date(RowAccessor::string($row, 'expires_at')),
            revokedAt: null !== $revokedAt ? self::date($revokedAt) : null,
        );
    }

    public function countUsable(UserId $userId, \DateTimeImmutable $now): int
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE user_id = :user AND revoked_at IS NULL AND expires_at > :now',
            ['user' => Uuid::fromString($userId->toString())->toBinary(), 'now' => self::utc($now)],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    public function revokeAll(UserId $userId, \DateTimeImmutable $at): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET revoked_at = :at WHERE user_id = :user AND revoked_at IS NULL AND expires_at > :at',
            ['user' => Uuid::fromString($userId->toString())->toBinary(), 'at' => self::utc($at)],
        );
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
