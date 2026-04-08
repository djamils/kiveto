<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;

interface MembershipEligibilityCheckerInterface
{
    /**
     * @param list<string> $allowedRoles
     */
    public function isUserEligibleForClinicAt(
        UserId $userId,
        ClinicId $clinicId,
        \DateTimeImmutable $at,
        array $allowedRoles,
    ): bool;

    /**
     * @param list<string> $allowedRoles
     *
     * @return list<array{userId: string, displayName: string|null}>
     */
    public function listEligiblePractitionerUsersForClinic(
        ClinicId $clinicId,
        \DateTimeImmutable $at,
        array $allowedRoles,
    ): array;
}
