<?php

declare(strict_types=1);

namespace App\ClinicAccess\Application\Port;

use App\ClinicAccess\Application\Query\ListAllMemberships\MembershipCollection;
use App\ClinicAccess\Domain\ValueObject\ClinicMemberRole;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipEngagement;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipStatus;

interface MembershipAdminRepositoryInterface
{
    public function listAll(
        ?string $clinicId = null,
        ?string $userId = null,
        ?ClinicMembershipStatus $status = null,
        ?ClinicMemberRole $role = null,
        ?ClinicMembershipEngagement $engagement = null,
    ): MembershipCollection;
}
