<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListClinicVeterinarians implements QueryInterface
{
    public function __construct(
        public string $clinicId,
    ) {
    }
}
