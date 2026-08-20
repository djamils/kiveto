<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\ValueObject;

use App\Context\Catalog\Domain\ValueObject\ClinicId;

final readonly class PricingContext
{
    public function __construct(
        public ClinicId $clinicId,
        public ?PriceListId $priceListId,
        public bool $isUrgency,
        public ?AnimalSizeCategory $animalSize,
        public ?string $speciesGroup,
        public ?string $discountCode,
        public \DateTimeImmutable $pricingDate,
    ) {
    }
}
