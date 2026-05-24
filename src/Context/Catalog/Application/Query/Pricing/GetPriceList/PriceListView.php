<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Pricing\GetPriceList;

final readonly class PriceListView
{
    /**
     * @param list<array{id: string, itemType: string, itemId: string, netPriceMinorUnits: int, netPriceCurrency: string, taxCategoryOverride: string|null}> $items
     * @param list<array{id: string, ruleType: string, condition: array<mixed>, adjustment: array<mixed>}>                                                   $rules
     */
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $name,
        public bool $isDefault,
        public string $status,
        public array $items,
        public array $rules,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
