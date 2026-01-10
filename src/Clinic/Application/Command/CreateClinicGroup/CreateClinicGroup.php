<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\CreateClinicGroup;

final readonly class CreateClinicGroup
{
    public function __construct(
        public string $name,
    ) {
    }
}
