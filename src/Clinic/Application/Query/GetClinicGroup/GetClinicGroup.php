<?php

declare(strict_types=1);

namespace App\Clinic\Application\Query\GetClinicGroup;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetClinicGroup implements QueryInterface
{
    public function __construct(
        public string $clinicGroupId,
    ) {
    }
}
