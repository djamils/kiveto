<?php

declare(strict_types=1);

namespace App\Animal\Domain\ValueObject;

use App\Animal\Domain\Exception\InvalidTransferStatus;

final readonly class Transfer
{
    public function __construct(
        public TransferStatus $transferStatus,
        public ?\DateTimeImmutable $soldAt,
        public ?\DateTimeImmutable $givenAt,
    ) {
    }

    public static function none(): self
    {
        return new self(
            transferStatus: TransferStatus::NONE,
            soldAt: null,
            givenAt: null,
        );
    }

    public function ensureConsistency(): void
    {
        match ($this->transferStatus) {
            TransferStatus::NONE  => $this->ensureNone(),
            TransferStatus::SOLD  => $this->ensureSold(),
            TransferStatus::GIVEN => $this->ensureGiven(),
        };
    }

    private function ensureNone(): void
    {
        if (null !== $this->soldAt || null !== $this->givenAt) {
            throw new InvalidTransferStatus('NONE status requires soldAt and givenAt to be null.');
        }
    }

    private function ensureSold(): void
    {
        if (null === $this->soldAt || null !== $this->givenAt) {
            throw new InvalidTransferStatus('SOLD status requires soldAt to be set and givenAt to be null.');
        }
    }

    private function ensureGiven(): void
    {
        if (null !== $this->soldAt || null === $this->givenAt) {
            throw new InvalidTransferStatus('GIVEN status requires givenAt to be set and soldAt to be null.');
        }
    }
}
