<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;

/**
 * The amounts of one VAT rate of a document (EN16931 BG-23).
 *
 * - lineTotal: Σ line nets at that rate;
 * - allowance: the share of the document-level discount (BT-92);
 * - base: lineTotal − allowance, the taxable amount (BT-116);
 * - vat: the VAT of the rate (BT-117);
 * - gross: base + vat.
 */
final class RateBucket
{
    private function __construct(
        private readonly string $ratePercent,
        private readonly int $lineTotal,
        private readonly int $allowance,
        private readonly int $vat,
        private readonly CurrencyCode $currency,
    ) {
    }

    public static function of(string $ratePercent, int $lineTotal, int $allowance, int $vat, CurrencyCode $currency): self
    {
        if (1 !== preg_match('/^\d+(\.\d{1,4})?$/', $ratePercent)) {
            throw new \InvalidArgumentException(\sprintf('Invalid tax rate "%s".', $ratePercent));
        }

        return new self($ratePercent, $lineTotal, $allowance, $vat, $currency);
    }

    public function ratePercent(): string
    {
        return $this->ratePercent;
    }

    public function lineTotal(): Money
    {
        return Money::fromMinorUnits($this->lineTotal, $this->currency);
    }

    public function allowance(): Money
    {
        return Money::fromMinorUnits($this->allowance, $this->currency);
    }

    public function base(): Money
    {
        return Money::fromMinorUnits($this->lineTotal - $this->allowance, $this->currency);
    }

    public function vat(): Money
    {
        return Money::fromMinorUnits($this->vat, $this->currency);
    }

    public function gross(): Money
    {
        return Money::fromMinorUnits($this->lineTotal - $this->allowance + $this->vat, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->ratePercent === $other->ratePercent
            && $this->lineTotal === $other->lineTotal
            && $this->allowance === $other->allowance
            && $this->vat === $other->vat
            && $this->currency->equals($other->currency);
    }
}
