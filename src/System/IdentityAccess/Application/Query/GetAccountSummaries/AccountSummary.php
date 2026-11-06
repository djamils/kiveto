<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetAccountSummaries;

/**
 * An account as the console shows it next to a person.
 */
final readonly class AccountSummary
{
    public function __construct(
        public string $id,
        public string $type,
        public ?string $email,
        public ?string $phone,
        public string $status,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $lastLoginAt,
    ) {
    }

    public function isActive(): bool
    {
        return 'ACTIVE' === $this->status;
    }

    public function isPending(): bool
    {
        return 'PENDING' === $this->status;
    }

    public function isDisabled(): bool
    {
        return 'DISABLED' === $this->status;
    }
}
