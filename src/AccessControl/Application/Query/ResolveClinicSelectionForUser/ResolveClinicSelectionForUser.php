<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Query\ResolveClinicSelectionForUser;

final readonly class ResolveClinicSelectionForUser
{
    public function __construct(
        public string $userId,
    ) {
    }
}
