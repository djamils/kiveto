<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;

/**
 * Every amount of a document: per line, per VAT rate and in total.
 *
 * Built by the calculator while a document is a draft, then frozen with it
 * at issue — the totals are always sums of the buckets, so the printed
 * breakdown adds up to the totals by construction.
 */
final class CalculatedAmounts
{
    /**
     * @param list<LineAmounts> $lines
     * @param list<RateBucket>  $buckets highest rate first
     */
    private function __construct(
        private readonly array $lines,
        private readonly array $buckets,
        private readonly CurrencyCode $currency,
    ) {
    }

    /**
     * @param list<LineAmounts> $lines
     * @param list<RateBucket>  $buckets
     */
    public static function of(array $lines, array $buckets, CurrencyCode $currency): self
    {
        usort(
            $buckets,
            static function (RateBucket $a, RateBucket $b): int {
                /** @var numeric-string $left */
                $left = $b->ratePercent();
                /** @var numeric-string $right */
                $right = $a->ratePercent();

                return bccomp($left, $right, 4);
            },
        );

        return new self($lines, $buckets, $currency);
    }

    public static function none(CurrencyCode $currency): self
    {
        return new self([], [], $currency);
    }

    /**
     * @return list<LineAmounts> in the order of the lines they were computed for
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * @return list<RateBucket> highest rate first — the order an invoice prints them in
     */
    public function buckets(): array
    {
        return $this->buckets;
    }

    /** Σ line nets (BT-106). */
    public function totalLineNet(): Money
    {
        return $this->sum(static fn (RateBucket $bucket): int => $bucket->lineTotal()->minorUnits());
    }

    /** Σ document-level allowances (BT-107). */
    public function totalAllowance(): Money
    {
        return $this->sum(static fn (RateBucket $bucket): int => $bucket->allowance()->minorUnits());
    }

    /** Total without VAT (BT-109). */
    public function taxBasis(): Money
    {
        return $this->sum(static fn (RateBucket $bucket): int => $bucket->base()->minorUnits());
    }

    /** Total VAT (BT-110). */
    public function totalVat(): Money
    {
        return $this->sum(static fn (RateBucket $bucket): int => $bucket->vat()->minorUnits());
    }

    /** Total with VAT (BT-112). */
    public function totalGross(): Money
    {
        return $this->sum(static fn (RateBucket $bucket): int => $bucket->gross()->minorUnits());
    }

    public function currency(): CurrencyCode
    {
        return $this->currency;
    }

    /**
     * @param callable(RateBucket): int $extract
     */
    private function sum(callable $extract): Money
    {
        $total = 0;

        foreach ($this->buckets as $bucket) {
            $total += $extract($bucket);
        }

        return Money::fromMinorUnits($total, $this->currency);
    }
}
