<?php

declare(strict_types=1);

namespace App\System\AccessControl\Application\Command\ChangeClinicMembershipRole;

use App\Shared\Application\Bus\CommandInterface;
use App\System\AccessControl\Domain\ValueObject\ClinicMemberRole;

final readonly class ChangeClinicMembershipRole implements CommandInterface
{
    public function __construct(
        public string $membershipId,
        public ClinicMemberRole $role,
    ) {
    }
}
