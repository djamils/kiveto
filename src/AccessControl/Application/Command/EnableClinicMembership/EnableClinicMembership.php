<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Command\EnableClinicMembership;

use App\Shared\Application\Bus\CommandInterface;

final readonly class EnableClinicMembership implements CommandInterface
{
    public function __construct(
        public string $membershipId,
    ) {
    }
}
