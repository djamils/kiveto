<?php

declare(strict_types=1);

namespace App\ClinicAccess\Application\Query\GetUserMembershipInClinic;

final readonly class GetUserMembershipInClinic
{
    public function __construct(
        public string $userId,
        public string $clinicId,
    ) {
    }
}
