<?php

declare(strict_types=1);

namespace App\ClinicAccess\Application\Command\EnableClinicMembership;

final readonly class EnableClinicMembership
{
    public function __construct(
        public string $membershipId,
    ) {
    }
}
