<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

final readonly class StaffProfileReadItem
{
    public function __construct(
        public string $profileId,
        public string $membershipId,
        public string $userId,
        public string $displayName,
        public ?string $professionalTitle,
        public string $agendaColor,
        public int $sortOrder,
        public bool $isVisibleInAgenda,
    ) {
    }
}
