<?php

declare(strict_types=1);

namespace App\Animal\Domain\ValueObject;

final readonly class Transfer
{
    public function __construct(
        public \App\Animal\Domain\Enum\TransferStatus $transferStatus,
        public ?\DateTimeImmutable $soldAt,
        public ?\DateTimeImmutable $givenAt,
    ) {
    }

    public static function none(): self
    {
        return new self(
            transferStatus: \App\Animal\Domain\Enum\TransferStatus::NONE,
            soldAt: null,
            givenAt: null,
        );
    }

    public function ensureConsistency(): void
    {
        match ($this->transferStatus) {
            \App\Animal\Domain\Enum\TransferStatus::NONE  => $this->ensureNone(),
            \App\Animal\Domain\Enum\TransferStatus::SOLD  => $this->ensureSold(),
            \App\Animal\Domain\Enum\TransferStatus::GIVEN => $this->ensureGiven(),
        };
    }

    private function ensureNone(): void
    {
        if (null !== $this->soldAt || null !== $this->givenAt) {
            throw new \App\Animal\Domain\Exception\InvalidTransferStatus('NONE status requires soldAt and givenAt to be null.');
        }
    }

    private function ensureSold(): void
    {
        if (null === $this->soldAt || null !== $this->givenAt) {
            throw new \App\Animal\Domain\Exception\InvalidTransferStatus('SOLD status requires soldAt to be set and givenAt to be null.');
        }
    }

    private function ensureGiven(): void
    {
        if (null !== $this->soldAt || null === $this->givenAt) {
            throw new \App\Animal\Domain\Exception\InvalidTransferStatus('GIVEN status requires givenAt to be set and soldAt to be null.');
        }
    }
}
