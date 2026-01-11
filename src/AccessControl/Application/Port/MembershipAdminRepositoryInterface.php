<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Port;

use App\AccessControl\Application\Query\ListAllMemberships\MembershipCollection;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;

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
