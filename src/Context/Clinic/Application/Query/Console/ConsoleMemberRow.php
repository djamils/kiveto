<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Console;

/**
 * A membership with the person's profile, as the console shows it.
 */
final readonly class ConsoleMemberRow
{
    public function __construct(
        public string $membershipId,
        public string $clinicId,
        public string $clinicName,
        public string $userId,
        public string $role,
        public bool $isAdministrator,
        public string $status,
        public \DateTimeImmutable $since,
        public string $firstName,
        public string $lastName,
        public string $displayName,
        public ?string $professionalTitle,
        public ?string $phone,
        public ?string $registrationNumber,
    ) {
    }

    /**
     * « Dr Claire Martin », « Inès Benali ».
     */
    public function printedName(): string
    {
        $name = trim($this->firstName . ' ' . $this->lastName);
        $name = '' !== $name ? $name : $this->displayName;

        return match ($this->professionalTitle) {
            'DR'    => 'Dr ' . $name,
            'PR'    => 'Pr ' . $name,
            default => $name,
        };
    }

    public function isDisabled(): bool
    {
        return 'ACTIVE' !== $this->status;
    }
}
