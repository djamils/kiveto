<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\ValueObject;

use App\Context\Prescription\Domain\Exception\InvalidPrescriptionNumberException;

/**
 * A clinic's prescription number, e.g. "ORD-2026-000042".
 *
 * Same shape and the same gap-free, per-year discipline as an invoice number:
 * a prescription is an act the clinic must be able to produce again years
 * later, and it is found by its number.
 */
final class PrescriptionNumber
{
    private const string PATTERN = '/^ORD-(\d{4})-(\d{6})$/';

    private function __construct(
        private readonly string $value,
        private readonly int $year,
        private readonly int $sequence,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::PATTERN, $value, $matches)) {
            throw new InvalidPrescriptionNumberException($value);
        }

        return new self($value, (int) $matches[1], (int) $matches[2]);
    }

    public static function of(int $year, int $sequence): self
    {
        if ($year < 1000 || $year > 9999) {
            throw new InvalidPrescriptionNumberException((string) $year);
        }

        if ($sequence < 1 || $sequence > 999999) {
            throw new InvalidPrescriptionNumberException((string) $sequence);
        }

        return new self(\sprintf('ORD-%04d-%06d', $year, $sequence), $year, $sequence);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function year(): int
    {
        return $this->year;
    }

    public function sequence(): int
    {
        return $this->sequence;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
