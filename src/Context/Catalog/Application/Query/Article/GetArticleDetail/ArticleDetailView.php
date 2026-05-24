<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Article\GetArticleDetail;

final readonly class ArticleDetailView
{
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $name,
        public string $code,
        public string $kind,
        public ?string $gtin,
        public string $taxCategoryCode,
        public int $basePriceMinorUnits,
        public string $basePriceCurrency,
        public string $unitOfMeasure,
        public bool $trackStock,
        public string $status,
        public ?string $authorizationRef,
        public ?bool $requiresPrescription,
        public ?string $prescriptionClass,
        public ?bool $isControlledSubstance,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
