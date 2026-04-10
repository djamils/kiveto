<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

use App\Context\Clinic\Application\Query\ListClinicsForUser\AccessibleClinic;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;

interface ClinicMembershipReadRepositoryInterface
{
    /**
     * Returns the list of clinics accessible to a user (status ACTIVE + validity window).
     *
     * @return list<AccessibleClinic>
     */
    public function findAccessibleClinicsForUser(UserId $userId): array;
}
