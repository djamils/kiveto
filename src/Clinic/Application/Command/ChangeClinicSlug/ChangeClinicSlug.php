<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\ChangeClinicSlug;

final readonly class ChangeClinicSlug
{
    public function __construct(
        public string $clinicId,
        public string $slug,
    ) {
    }
}
