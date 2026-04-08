<?php

declare(strict_types=1);

namespace App\System\AccessControl\Application\Command\DisableClinicMembership;

use App\Shared\Application\Bus\CommandInterface;

final readonly class DisableClinicMembership implements CommandInterface
{
    public function __construct(
        public string $membershipId,
    ) {
    }
}
