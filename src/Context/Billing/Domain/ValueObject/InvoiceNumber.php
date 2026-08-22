<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

use App\Context\Billing\Domain\Exception\InvalidInvoiceNumberException;

/**
 * A clinic's invoice number, e.g. "FAC-2026-000042".
 *
 * The sequence restarts every year and is gap-free per clinic, which is what a
 * French tax audit expects; the format is fixed here so the parts can be read
 * back without a second column.
 */
final class InvoiceNumber
{
    private const string PATTERN = '/^FAC-(\d{4})-(\d{6})$/';

    private function __construct(
        private readonly string $value,
        private readonly int $year,
        private readonly int $sequence,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::PATTERN, $value, $matches)) {
            throw new InvalidInvoiceNumberException($value);
        }

        return new self($value, (int) $matches[1], (int) $matches[2]);
    }

    public static function of(int $year, int $sequence): self
    {
        if ($year < 1000 || $year > 9999) {
            throw new InvalidInvoiceNumberException((string) $year);
        }

        if ($sequence < 1 || $sequence > 999999) {
            throw new InvalidInvoiceNumberException((string) $sequence);
        }

        return new self(\sprintf('FAC-%04d-%06d', $year, $sequence), $year, $sequence);
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
