<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians;

use App\Shared\Application\Bus\RequestCacheableQueryInterface;

final readonly class ListClinicVeterinarians implements RequestCacheableQueryInterface
{
    public function __construct(
        public string $clinicId,
    ) {
    }
}
