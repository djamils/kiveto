<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Console;

/**
 * A clinic as the console lists it: identity, account and head counts
 * (members not disabled; veterinarians among them).
 */
final readonly class ConsoleClinicRow
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $city,
        public ?string $postalCode,
        public ?string $siret,
        public ?string $legalName,
        public string $status,
        public string $plan,
        public ?\DateTimeImmutable $pilotEndsOn,
        public ?\DateTimeImmutable $onboardedAt,
        public ?\DateTimeImmutable $suspendedAt,
        public \DateTimeImmutable $createdAt,
        public int $veterinarians,
        public int $members,
    ) {
    }

    public function isSuspended(): bool
    {
        return 'active' !== $this->status;
    }
}
