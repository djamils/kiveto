<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Pricing\ResolvePrice;

use App\Context\Catalog\Application\Service\PriceResolver;
use App\Context\Catalog\Domain\Pricing\ValueObject\AnimalSizeCategory;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PricingContext;
use App\Context\Catalog\Domain\Pricing\ValueObject\ResolvedPrice;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ResolvePriceHandler
{
    public function __construct(
        private readonly PriceResolver $priceResolver,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(ResolvePrice $query): ResolvedPrice
    {
        $itemRef = CatalogItemRef::fromPrimitives($query->itemType, $query->itemId);

        $context = new PricingContext(
            clinicId: ClinicId::fromString($query->clinicId),
            priceListId: null !== $query->priceListId ? PriceListId::fromString($query->priceListId) : null,
            isUrgency: $query->isUrgency,
            animalSize: null !== $query->animalSize ? AnimalSizeCategory::from($query->animalSize) : null,
            speciesGroup: $query->speciesGroup,
            discountCode: $query->discountCode,
            pricingDate: $this->clock->now(),
        );

        return $this->priceResolver->resolve($itemRef, $context);
    }
}
