<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Pricing\UpdatePriceListItem;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdatePriceListItem implements CommandInterface
{
    public function __construct(
        public string $priceListId,
        public string $priceListItemId,
        public string $clinicId,
        public int $netPriceMinorUnits,
        public string $netPriceCurrency,
        public ?string $taxCategoryOverride,
    ) {
    }
}
