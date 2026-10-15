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
        public string $firstName = '',
        public string $lastName = '',
        public ?string $phone = null,
        // Set only while the member holds veterinary credentials.
        public ?string $registrationNumber = null,
        public bool $hasSignature = false,
    ) {
    }
}
