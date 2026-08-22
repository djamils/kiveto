<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\ValueObject;

use App\Context\Prescription\Domain\Exception\InvalidRenewalsException;

/**
 * How many times the ordonnance may be presented again, and how many of those
 * have been used.
 */
final class Renewals
{
    private const int MAXIMUM = 12;

    private function __construct(
        private readonly int $allowed,
        private readonly int $used,
    ) {
    }

    public static function none(): self
    {
        return new self(0, 0);
    }

    public static function of(int $allowed): self
    {
        if ($allowed < 0) {
            throw new InvalidRenewalsException('A renewal allowance cannot be negative.');
        }

        if ($allowed > self::MAXIMUM) {
            throw new InvalidRenewalsException(
                \sprintf('A prescription cannot be renewed more than %d times.', self::MAXIMUM),
            );
        }

        return new self($allowed, 0);
    }

    public static function reconstitute(int $allowed, int $used): self
    {
        if ($used < 0 || $used > $allowed) {
            throw new InvalidRenewalsException('The renewals used cannot exceed the allowance.');
        }

        return new self($allowed, $used);
    }

    public function allowed(): int
    {
        return $this->allowed;
    }

    public function used(): int
    {
        return $this->used;
    }

    public function remaining(): int
    {
        return $this->allowed - $this->used;
    }

    public function hasRemaining(): bool
    {
        return $this->remaining() > 0;
    }

    public function consumeOne(): self
    {
        if (!$this->hasRemaining()) {
            throw new InvalidRenewalsException('No renewal left on this prescription.');
        }

        return new self($this->allowed, $this->used + 1);
    }
}
