<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Pricing\ResolvePrice;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ResolvePrice implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public ?string $priceListId,
        public string $itemType,
        public string $itemId,
        public bool $isUrgency = false,
        public ?string $animalSize = null,
        public ?string $speciesGroup = null,
        public ?string $discountCode = null,
    ) {
    }
}
