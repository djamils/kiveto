<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\IdentityAccess\Domain\SecondFactor\BackupCode;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactor;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactorRepositoryInterface;
use App\System\IdentityAccess\Domain\SecondFactor\TotpSecret;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Infrastructure\Security\TotpSecretCipher;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * One row per back-office account; the key is encrypted at rest.
 */
final readonly class DbalSecondFactorRepository implements SecondFactorRepositoryInterface
{
    private const string TABLE = 'identity_access__second_factors';

    public function __construct(
        private Connection $connection,
        private TotpSecretCipher $cipher,
    ) {
    }

    public function save(SecondFactor $factor): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (user_id, secret_ciphertext, enabled_at, last_used_step, backup_codes)'
            . ' VALUES (:user, :secret, :enabled, :step, :codes)'
            . ' AS new ON DUPLICATE KEY UPDATE secret_ciphertext = new.secret_ciphertext, enabled_at = new.enabled_at,'
            . ' last_used_step = new.last_used_step, backup_codes = new.backup_codes',
            [
                'user'    => Uuid::fromString($factor->userId()->toString())->toBinary(),
                'secret'  => $this->cipher->encrypt($factor->secret()->bytes()),
                'enabled' => self::utc($factor->enabledAt()),
                'step'    => $factor->lastUsedStep(),
                'codes'   => json_encode(array_map(
                    static fn (BackupCode $code): array => [
                        'hash'    => $code->hash(),
                        'used_at' => null !== $code->usedAt() ? self::utc($code->usedAt()) : null,
                    ],
                    $factor->backupCodes(),
                ), \JSON_THROW_ON_ERROR),
            ],
        );
    }

    public function findByUserId(UserId $userId): ?SecondFactor
    {
        return $this->find($userId, '');
    }

    public function findByUserIdForUpdate(UserId $userId): ?SecondFactor
    {
        return $this->find($userId, ' FOR UPDATE');
    }

    public function delete(UserId $userId): void
    {
        $this->connection->executeStatement('DELETE FROM ' . self::TABLE . ' WHERE user_id = :user', ['user' => Uuid::fromString($userId->toString())->toBinary()]);
    }

    private function find(UserId $userId, string $lock): ?SecondFactor
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE user_id = :user' . $lock,
            ['user' => Uuid::fromString($userId->toString())->toBinary()],
        );

        if (false === $row) {
            return null;
        }

        $codes = [];
        $raw   = json_decode(RowAccessor::string($row, 'backup_codes'), true, flags: \JSON_THROW_ON_ERROR);
        foreach (\is_array($raw) ? $raw : [] as $code) {
            if (\is_array($code) && \is_string($code['hash'] ?? null)) {
                $usedAt  = \is_string($code['used_at'] ?? null) ? new \DateTimeImmutable($code['used_at'], new \DateTimeZone('UTC')) : null;
                $codes[] = BackupCode::reconstitute($code['hash'], $usedAt);
            }
        }

        return SecondFactor::reconstitute(
            userId: $userId,
            secret: TotpSecret::fromBytes($this->cipher->decrypt(RowAccessor::string($row, 'secret_ciphertext'))),
            enabledAt: new \DateTimeImmutable(RowAccessor::string($row, 'enabled_at'), new \DateTimeZone('UTC')),
            lastUsedStep: RowAccessor::nullableInt($row, 'last_used_step'),
            backupCodes: $codes,
        );
    }

    private static function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
