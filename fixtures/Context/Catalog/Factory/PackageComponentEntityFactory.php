<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Catalog\Factory;

use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PackageComponentEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<PackageComponentEntity>
 */
final class PackageComponentEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return PackageComponentEntity::class;
    }

    protected function defaults(): array
    {
        return [
            'id'        => Uuid::v7(),
            'packageId' => Uuid::v7(),
            'itemType'  => 'ACT',
            'itemId'    => Uuid::v7()->toRfc4122(),
            'quantity'  => 1,
            'weight'    => 0,
        ];
    }
}
