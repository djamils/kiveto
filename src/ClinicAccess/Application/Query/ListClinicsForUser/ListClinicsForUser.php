<?php

declare(strict_types=1);

namespace App\ClinicAccess\Application\Query\ListClinicsForUser;

final readonly class ListClinicsForUser
{
    public function __construct(
        public string $userId,
    ) {
    }
}
