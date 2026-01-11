<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Port;

use App\AccessControl\Application\Query\ListClinicsForUser\AccessibleClinic;
use App\IdentityAccess\Domain\ValueObject\UserId;

interface ClinicMembershipReadRepositoryInterface
{
    /**
     * Retourne la liste des cliniques accessibles pour un utilisateur (status ACTIVE + window de validité).
     *
     * @return list<AccessibleClinic>
     */
    public function findAccessibleClinicsForUser(UserId $userId): array;
}
