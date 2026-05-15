<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

final class ValidityPeriod
{
    private function __construct(
        private readonly \DateTimeImmutable $validFrom,
        private readonly ?\DateTimeImmutable $validTo,
    ) {
    }

    public static function between(\DateTimeImmutable $from, \DateTimeImmutable $to): self
    {
        if ($from > $to) {
            throw new \InvalidArgumentException('validFrom must be <= validTo.');
        }

        return new self($from, $to);
    }

    public static function openEndedFrom(\DateTimeImmutable $from): self
    {
        return new self($from, null);
    }

    public function contains(\DateTimeImmutable $date): bool
    {
        if ($date < $this->validFrom) {
            return false;
        }

        if (null !== $this->validTo && $date > $this->validTo) {
            return false;
        }

        return true;
    }

    public function validFrom(): \DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }
}
