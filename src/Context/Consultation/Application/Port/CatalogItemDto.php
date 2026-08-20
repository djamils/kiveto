<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * A Catalog act or article as the consultation cockpit needs it: enough to show
 * it in a search list and to build a prescription or billing line from it.
 */
final readonly class CatalogItemDto
{
    public function __construct(
        public string $itemType,
        public string $itemId,
        public string $name,
        public string $code,
        public bool $requiresPrescription,
        public int $basePriceMinorUnits,
        public string $currency,
        public string $taxCategoryCode,
        public string $status,
    ) {
    }
}
