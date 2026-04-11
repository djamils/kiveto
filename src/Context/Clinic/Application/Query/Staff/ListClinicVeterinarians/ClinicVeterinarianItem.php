<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians;

final readonly class ClinicVeterinarianItem
{
    public function __construct(
        public string $userId,
        public string $role,
        public string $engagement,
    ) {
    }
}
