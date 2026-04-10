<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\MembershipCollection;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;

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
