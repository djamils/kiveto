<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Catalog\ListCatalog;

final readonly class ArticleListRow
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public string $kind,
        public ?string $gtin,
        public string $taxCategoryCode,
        public int $basePriceMinorUnits,
        public string $currency,
        public string $unitOfMeasure,
        public bool $trackStock,
        public ?string $authorizationRef,
        public bool $requiresPrescription,
        public ?string $prescriptionClass,
        public bool $isControlledSubstance,
        public string $status,
    ) {
    }

    public function isDrug(): bool
    {
        return 'DRUG' === $this->kind;
    }

    public function isArchived(): bool
    {
        return 'ARCHIVED' === $this->status;
    }
}
