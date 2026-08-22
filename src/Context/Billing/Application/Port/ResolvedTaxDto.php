<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

final readonly class ResolvedTaxDto
{
    public function __construct(
        public int $netMinorUnits,
        public int $taxMinorUnits,
        public int $grossMinorUnits,
        public string $ratePercent,
    ) {
    }

    /**
     * Fallback for a line the taxation rules cannot price — the amount stands,
     * untaxed, rather than the whole invoice failing.
     */
    public static function untaxed(int $netMinorUnits): self
    {
        return new self($netMinorUnits, 0, $netMinorUnits, '0');
    }
}
