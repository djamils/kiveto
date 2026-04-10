<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\DisableClinicMembership;

use App\Shared\Application\Bus\CommandInterface;

final readonly class DisableClinicMembership implements CommandInterface
{
    public function __construct(
        public string $membershipId,
    ) {
    }
}
