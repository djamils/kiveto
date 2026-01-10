<?php

declare(strict_types=1);

namespace App\ClinicAccess\Application\Command\ChangeClinicMembershipRole;

use App\ClinicAccess\Domain\ValueObject\ClinicMemberRole;

final readonly class ChangeClinicMembershipRole
{
    public function __construct(
        public string $membershipId,
        public ClinicMemberRole $role,
    ) {
    }
}
