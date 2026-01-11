<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Command\ChangeClinicMembershipRole;

use App\AccessControl\Domain\ValueObject\ClinicMemberRole;

final readonly class ChangeClinicMembershipRole
{
    public function __construct(
        public string $membershipId,
        public ClinicMemberRole $role,
    ) {
    }
}
