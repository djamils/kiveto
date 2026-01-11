<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Query\ListAllMemberships;

use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;

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
