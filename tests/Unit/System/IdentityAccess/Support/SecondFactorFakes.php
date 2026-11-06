<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Support;

use App\System\IdentityAccess\Application\Port\OtpCodeHasherInterface;
use App\System\IdentityAccess\Application\Port\SecondFactorSecretsInterface;
use App\System\IdentityAccess\Application\Service\BackupCodes;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactor;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactorRepositoryInterface;
use App\System\IdentityAccess\Domain\SecondFactor\TotpSecret;
use App\System\IdentityAccess\Domain\ValueObject\UserId;

/**
 * Hand-written doubles of the second factor: an in-memory repository, the
 * RFC 6238 key, sequential backup codes and a readable digest.
 */
final class SecondFactorFakes implements SecondFactorRepositoryInterface, SecondFactorSecretsInterface, OtpCodeHasherInterface
{
    /** The SHA-1 key of RFC 6238, appendix B. */
    public const string RFC_KEY = '12345678901234567890';

    /** @var array<string, SecondFactor> */
    public array $factors = [];

    /** @var list<string> ids read with a lock, in order */
    public array $locked = [];

    public int $saves = 0;

    /** @var list<string> backup codes handed out before the sequential ones */
    public array $nextBackupCodes = [];

    private int $sequence = 0;

    public function save(SecondFactor $factor): void
    {
        $this->factors[$factor->userId()->toString()] = $factor;
        ++$this->saves;
    }

    public function findByUserId(UserId $userId): ?SecondFactor
    {
        return $this->factors[$userId->toString()] ?? null;
    }

    public function findByUserIdForUpdate(UserId $userId): ?SecondFactor
    {
        $this->locked[] = $userId->toString();

        return $this->findByUserId($userId);
    }

    public function delete(UserId $userId): void
    {
        unset($this->factors[$userId->toString()]);
    }

    public function newTotpSecret(): TotpSecret
    {
        return TotpSecret::fromBytes(self::RFC_KEY);
    }

    public function newBackupCode(): string
    {
        return array_shift($this->nextBackupCodes) ?? \sprintf('KVT%02d-ABCDE', ++$this->sequence);
    }

    public function hash(string $code): string
    {
        return 'h:' . $code;
    }

    public function backupCodes(): BackupCodes
    {
        return new BackupCodes($this, $this);
    }
}
