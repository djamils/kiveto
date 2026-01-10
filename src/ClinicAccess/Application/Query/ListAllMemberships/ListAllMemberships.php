<?php

declare(strict_types=1);

namespace App\ClinicAccess\Application\Query\ListAllMemberships;

use App\ClinicAccess\Domain\ValueObject\ClinicMemberRole;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipEngagement;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipStatus;

final readonly class ListAllMemberships
{
    public function __construct(
        public ?string $clinicId = null,
        public ?string $userId = null,
        public ?ClinicMembershipStatus $status = null,
        public ?ClinicMemberRole $role = null,
        public ?ClinicMembershipEngagement $engagement = null,
    ) {
    }
}
