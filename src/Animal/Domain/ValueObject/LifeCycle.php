<?php

declare(strict_types=1);

namespace App\Animal\Domain\ValueObject;

use App\Animal\Domain\Exception\InvalidLifeStatus;

final readonly class LifeCycle
{
    public function __construct(
        public LifeStatus $lifeStatus,
        public ?\DateTimeImmutable $deceasedAt,
        public ?\DateTimeImmutable $missingSince,
    ) {
    }

    public static function alive(): self
    {
        return new self(
            lifeStatus: LifeStatus::ALIVE,
            deceasedAt: null,
            missingSince: null,
        );
    }

    public function ensureConsistency(): void
    {
        match ($this->lifeStatus) {
            LifeStatus::ALIVE    => $this->ensureAlive(),
            LifeStatus::DECEASED => $this->ensureDeceased(),
            LifeStatus::MISSING  => $this->ensureMissing(),
        };
    }

    private function ensureAlive(): void
    {
        if (null !== $this->deceasedAt || null !== $this->missingSince) {
            throw new InvalidLifeStatus('ALIVE status requires deceasedAt and missingSince to be null.');
        }
    }

    private function ensureDeceased(): void
    {
        if (null === $this->deceasedAt || null !== $this->missingSince) {
            throw new InvalidLifeStatus('DECEASED status requires deceasedAt to be set and missingSince to be null.');
        }
    }

    private function ensureMissing(): void
    {
        if (null !== $this->deceasedAt || null === $this->missingSince) {
            throw new InvalidLifeStatus('MISSING status requires missingSince to be set and deceasedAt to be null.');
        }
    }
}
