<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\EnableClinicMembership;

use App\Shared\Application\Bus\CommandInterface;

final readonly class EnableClinicMembership implements CommandInterface
{
    public function __construct(
        public string $membershipId,
    ) {
    }
}
