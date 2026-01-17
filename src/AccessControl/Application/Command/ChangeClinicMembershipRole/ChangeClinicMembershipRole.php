<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Command\ChangeClinicMembershipRole;

use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\Shared\Application\Bus\CommandInterface;

final readonly class ChangeClinicMembershipRole implements CommandInterface
{
    public function __construct(
        public string $membershipId,
        public ClinicMemberRole $role,
    ) {
    }
}
