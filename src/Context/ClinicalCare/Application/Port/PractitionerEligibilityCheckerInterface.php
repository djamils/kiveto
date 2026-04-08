<?php

declare(strict_types=1);

namespace App\Context\ClinicalCare\Application\Port;

use App\Context\ClinicalCare\Domain\ValueObject\ClinicId;
use App\Context\ClinicalCare\Domain\ValueObject\UserId;

interface PractitionerEligibilityCheckerInterface
{
    /**
     * Check if user is eligible as practitioner for clinic at given time.
     *
     * @param string[] $allowedRoles Typically ['VETERINARY']
     */
    public function isEligibleForClinicAt(
        UserId $userId,
        ClinicId $clinicId,
        \DateTimeImmutable $at,
        array $allowedRoles,
    ): bool;
}
