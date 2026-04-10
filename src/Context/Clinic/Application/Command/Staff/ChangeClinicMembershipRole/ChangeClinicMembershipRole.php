<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipRole;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Shared\Application\Bus\CommandInterface;

final readonly class ChangeClinicMembershipRole implements CommandInterface
{
    public function __construct(
        public string $membershipId,
        public ClinicMemberRole $role,
    ) {
    }
}
