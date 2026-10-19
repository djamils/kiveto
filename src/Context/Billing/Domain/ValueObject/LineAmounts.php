<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

use App\Shared\Money\Domain\ValueObject\Money;
use App\Shared\Money\Domain\ValueObject\UnitPrice;

/**
 * The calculated amounts of one line, before any document-level discount.
 *
 * - net: the line net amount (BT-131);
 * - gross: the line amount incl. VAT, for display — not part of EN16931;
 * - unitNet: the unit net price at six decimals (BT-146).
 */
final class LineAmounts
{
    private function __construct(
        private readonly Money $net,
        private readonly Money $gross,
        private readonly UnitPrice $unitNet,
    ) {
    }

    public static function of(Money $net, Money $gross, UnitPrice $unitNet): self
    {
        return new self($net, $gross, $unitNet);
    }

    public function net(): Money
    {
        return $this->net;
    }

    public function gross(): Money
    {
        return $this->gross;
    }

    public function unitNet(): UnitPrice
    {
        return $this->unitNet;
    }

    public function equals(self $other): bool
    {
        return $this->net->equals($other->net)
            && $this->gross->equals($other->gross)
            && $this->unitNet->equals($other->unitNet);
    }
}
