<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * The price to snapshot on a prescription or plan line at add time.
 */
final readonly class CatalogPriceDto
{
    public function __construct(
        public int $minorUnits,
        public string $currency,
        public string $taxCategoryCode,
    ) {
    }
}
