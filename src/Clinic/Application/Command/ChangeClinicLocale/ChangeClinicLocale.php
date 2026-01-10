<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\ChangeClinicLocale;

final readonly class ChangeClinicLocale
{
    public function __construct(
        public string $clinicId,
        public string $locale,
    ) {
    }
}
