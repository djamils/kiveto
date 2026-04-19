<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians;

final readonly class ClinicVeterinarianItem
{
    public function __construct(
        public string $userId,
        public string $role,
        public string $engagement,
        public string $membershipId,
        public ?string $displayName = null,
        public ?string $professionalTitle = null,
        public ?string $agendaColor = null,
        public ?int $sortOrder = null,
        public ?bool $isVisibleInAgenda = null,
    ) {
    }
}
