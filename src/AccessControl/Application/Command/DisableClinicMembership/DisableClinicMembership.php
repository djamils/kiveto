<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Command\DisableClinicMembership;

final readonly class DisableClinicMembership
{
    public function __construct(
        public string $membershipId,
    ) {
    }
}
