<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Package\GetPackageDetail;

final readonly class PackageDetailView
{
    /**
     * @param list<array{id: string, itemType: string, itemId: string, quantity: int, weight: int}> $components
     */
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $name,
        public string $code,
        public string $taxCategoryCode,
        public string $pricingMode,
        public ?int $fixedPriceMinorUnits,
        public ?string $fixedPriceCurrency,
        /** 'incl' (all taxes included) or 'excl': the mode the price was entered in. */
        public ?string $fixedPriceMode,
        public array $components,
        public string $status,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
