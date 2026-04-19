<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\AccessibleClinic;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;

interface ClinicMembershipReadRepositoryInterface
{
    /**
     * Returns the list of clinics accessible to a user (status ACTIVE + validity window).
     *
     * @return list<AccessibleClinic>
     */
    public function findAccessibleClinicsForUser(UserId $userId): array;

    /**
     * Returns the list of ACTIVE veterinarians for a clinic, ordered deterministically
     * so UI labels and colors remain stable across reloads.
     * Each item carries membershipId for profile enrichment via batch lookup.
     *
     * @return list<ClinicVeterinarianItem>
     */
    public function findVeterinariansForClinic(ClinicId $clinicId): array;
}
