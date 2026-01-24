<?php

declare(strict_types=1);

namespace App\Animal\Domain\ValueObject;

final readonly class LifeCycle
{
    public function __construct(
        public \App\Animal\Domain\Enum\LifeStatus $lifeStatus,
        public ?\DateTimeImmutable $deceasedAt,
        public ?\DateTimeImmutable $missingSince,
    ) {
    }

    public static function alive(): self
    {
        return new self(
            lifeStatus: \App\Animal\Domain\Enum\LifeStatus::ALIVE,
            deceasedAt: null,
            missingSince: null,
        );
    }

    public function ensureConsistency(): void
    {
        match ($this->lifeStatus) {
            \App\Animal\Domain\Enum\LifeStatus::ALIVE    => $this->ensureAlive(),
            \App\Animal\Domain\Enum\LifeStatus::DECEASED => $this->ensureDeceased(),
            \App\Animal\Domain\Enum\LifeStatus::MISSING  => $this->ensureMissing(),
        };
    }

    private function ensureAlive(): void
    {
        if (null !== $this->deceasedAt || null !== $this->missingSince) {
            throw new \App\Animal\Domain\Exception\InvalidLifeStatus('ALIVE status requires deceasedAt and missingSince to be null.');
        }
    }

    private function ensureDeceased(): void
    {
        if (null === $this->deceasedAt || null !== $this->missingSince) {
            throw new \App\Animal\Domain\Exception\InvalidLifeStatus('DECEASED status requires deceasedAt to be set and missingSince to be null.');
        }
    }

    private function ensureMissing(): void
    {
        if (null !== $this->deceasedAt || null === $this->missingSince) {
            throw new \App\Animal\Domain\Exception\InvalidLifeStatus('MISSING status requires missingSince to be set and deceasedAt to be null.');
        }
    }
}
