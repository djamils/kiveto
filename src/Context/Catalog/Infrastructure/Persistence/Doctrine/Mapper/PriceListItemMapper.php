<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Catalog\Domain\Pricing\PriceListItem;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListItemId;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PriceListItemEntity;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use Symfony\Component\Uid\Uuid;

final readonly class PriceListItemMapper
{
    public function toEntity(PriceListItem $item, PriceListId $priceListId, ?PriceListItemEntity $existing = null): PriceListItemEntity
    {
        $entity = $existing ?? new PriceListItemEntity();

        $entity->setId(Uuid::fromString($item->id()->toString()));
        $entity->setPriceListId(Uuid::fromString($priceListId->toString()));
        $entity->setItemType($item->itemRef()->type()->value);
        $entity->setItemId($item->itemRef()->id());
        $entity->setNetPriceMinorUnits($item->netPrice()->minorUnits());
        $entity->setNetPriceCurrency($item->netPrice()->currency()->toString());
        $entity->setTaxCategoryOverride($item->taxCategoryOverride()?->toString());

        return $entity;
    }

    public function toDomain(PriceListItemEntity $entity): PriceListItem
    {
        $taxCategoryOverride = null !== $entity->getTaxCategoryOverride()
            ? TaxCategoryCode::fromString($entity->getTaxCategoryOverride())
            : null;

        return PriceListItem::create(
            id: PriceListItemId::fromString($entity->getId()->toString()),
            itemRef: CatalogItemRef::fromPrimitives($entity->getItemType(), $entity->getItemId()),
            netPrice: Money::fromMinorUnits(
                $entity->getNetPriceMinorUnits(),
                CurrencyCode::fromString($entity->getNetPriceCurrency()),
            ),
            taxCategoryOverride: $taxCategoryOverride,
        );
    }
}
