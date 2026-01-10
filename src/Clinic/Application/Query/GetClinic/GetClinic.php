<?php

declare(strict_types=1);

namespace App\Clinic\Application\Query\GetClinic;

final readonly class GetClinic
{
    public function __construct(
        public string $clinicId,
    ) {
    }
}
