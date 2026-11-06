<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\SecondFactor;

use App\System\IdentityAccess\Domain\ValueObject\UserId;

interface SecondFactorRepositoryInterface
{
    public function save(SecondFactor $factor): void;

    public function findByUserId(UserId $userId): ?SecondFactor;

    /**
     * Locked until the transaction ends: two codes typed at once cannot both
     * spend the same step.
     */
    public function findByUserIdForUpdate(UserId $userId): ?SecondFactor;

    public function delete(UserId $userId): void;
}
