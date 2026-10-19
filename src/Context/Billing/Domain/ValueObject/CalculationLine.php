<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

use App\Shared\Money\Domain\ValueObject\Money;

/**
 * What the amount calculator needs to know about one line: the price as
 * entered, its mode, the quantity and the VAT rate.
 */
final readonly class CalculationLine
{
    public function __construct(
        public Money $enteredUnitPrice,
        public PriceMode $priceMode,
        public string $quantity,
        public string $ratePercent,
    ) {
    }
}
