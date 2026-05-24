<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Catalog\Factory;

use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PriceListItemEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<PriceListItemEntity>
 */
final class PriceListItemEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return PriceListItemEntity::class;
    }

    protected function defaults(): array
    {
        return [
            'id'                  => Uuid::v7(),
            'priceListId'         => Uuid::v7(),
            'itemType'            => 'ACT',
            'itemId'              => Uuid::v7()->toRfc4122(),
            'netPriceMinorUnits'  => self::faker()->numberBetween(1000, 50000),
            'netPriceCurrency'    => 'EUR',
            'taxCategoryOverride' => null,
        ];
    }
}
