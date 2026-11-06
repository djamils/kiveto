<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\SecondFactor;

use App\System\IdentityAccess\Domain\ValueObject\UserId;

interface TrustedDeviceRepositoryInterface
{
    public function add(TrustedDevice $device): void;

    public function findByTokenHash(string $tokenHash): ?TrustedDevice;

    public function countUsable(UserId $userId, \DateTimeImmutable $now): int;

    /**
     * @return int how many were revoked
     */
    public function revokeAll(UserId $userId, \DateTimeImmutable $at): int;
}
