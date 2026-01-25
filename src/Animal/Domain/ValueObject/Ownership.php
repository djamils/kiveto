<?php

declare(strict_types=1);

namespace App\Animal\Domain\ValueObject;

final readonly class Ownership
{
    public function __construct(
        public string $clientId,
        public OwnershipRole $role,
        public OwnershipStatus $status,
        public \DateTimeImmutable $startedAt,
        public ?\DateTimeImmutable $endedAt,
    ) {
    }

    public function isActive(): bool
    {
        return OwnershipStatus::ACTIVE === $this->status;
    }

    public function isPrimary(): bool
    {
        return OwnershipRole::PRIMARY === $this->role;
    }

    public function isSecondary(): bool
    {
        return OwnershipRole::SECONDARY === $this->role;
    }

    public function end(\DateTimeImmutable $endedAt): self
    {
        return new self(
            clientId: $this->clientId,
            role: $this->role,
            status: OwnershipStatus::ENDED,
            startedAt: $this->startedAt,
            endedAt: $endedAt,
        );
    }

    public function promoteToprimary(): self
    {
        return new self(
            clientId: $this->clientId,
            role: OwnershipRole::PRIMARY,
            status: $this->status,
            startedAt: $this->startedAt,
            endedAt: $this->endedAt,
        );
    }
}
