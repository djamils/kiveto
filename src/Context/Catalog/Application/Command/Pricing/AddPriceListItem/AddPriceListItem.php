<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Pricing\AddPriceListItem;

use App\Shared\Application\Bus\CommandInterface;

final readonly class AddPriceListItem implements CommandInterface
{
    public function __construct(
        public string $priceListId,
        public string $clinicId,
        public string $itemType,
        public string $itemId,
        public int $netPriceMinorUnits,
        public string $netPriceCurrency,
        public ?string $taxCategoryOverride,
    ) {
    }
}
