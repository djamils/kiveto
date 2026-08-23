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
    public const string DEFAULT_PREFIX = 'FAC';

    /** The prefix is the clinic's own; the year and the counter are not. */
    private const string PATTERN = '/^([A-Z0-9][A-Z0-9\-\/]{0,11})-(\d{4})-(\d{6})$/';

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

        return new self($value, (int) $matches[2], (int) $matches[3]);
    }

    public static function of(int $year, int $sequence, ?string $prefix = null): self
    {
        $prefix = null !== $prefix && '' !== $prefix ? mb_strtoupper($prefix) : self::DEFAULT_PREFIX;
        if ($year < 1000 || $year > 9999) {
            throw new InvalidInvoiceNumberException((string) $year);
        }

        if ($sequence < 1 || $sequence > 999999) {
            throw new InvalidInvoiceNumberException((string) $sequence);
        }

        $value = \sprintf('%s-%04d-%06d', $prefix, $year, $sequence);

        if (1 !== preg_match(self::PATTERN, $value)) {
            throw new InvalidInvoiceNumberException($value);
        }

        return new self($value, $year, $sequence);
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
