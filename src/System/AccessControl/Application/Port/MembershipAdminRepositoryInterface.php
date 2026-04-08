<?php

declare(strict_types=1);

namespace App\System\AccessControl\Application\Port;

use App\System\AccessControl\Application\Query\ListAllMemberships\MembershipCollection;
use App\System\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\System\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\System\AccessControl\Domain\ValueObject\ClinicMembershipStatus;

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
