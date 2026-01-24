<?php

declare(strict_types=1);

namespace App\Animal\Domain\ValueObject;

final readonly class Ownership
{
    public function __construct(
        public string $clientId,
        public \App\Animal\Domain\Enum\OwnershipRole $role,
        public \App\Animal\Domain\Enum\OwnershipStatus $status,
        public \DateTimeImmutable $startedAt,
        public ?\DateTimeImmutable $endedAt,
    ) {
    }

    public function isActive(): bool
    {
        return \App\Animal\Domain\Enum\OwnershipStatus::ACTIVE === $this->status;
    }

    public function isPrimary(): bool
    {
        return \App\Animal\Domain\Enum\OwnershipRole::PRIMARY === $this->role;
    }

    public function isSecondary(): bool
    {
        return \App\Animal\Domain\Enum\OwnershipRole::SECONDARY === $this->role;
    }

    public function end(\DateTimeImmutable $endedAt): self
    {
        return new self(
            clientId: $this->clientId,
            role: $this->role,
            status: \App\Animal\Domain\Enum\OwnershipStatus::ENDED,
            startedAt: $this->startedAt,
            endedAt: $endedAt,
        );
    }

    public function promoteToprimary(): self
    {
        return new self(
            clientId: $this->clientId,
            role: \App\Animal\Domain\Enum\OwnershipRole::PRIMARY,
            status: $this->status,
            startedAt: $this->startedAt,
            endedAt: $this->endedAt,
        );
    }
}
