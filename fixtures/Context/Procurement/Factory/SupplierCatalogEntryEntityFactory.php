<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Procurement\Factory;

use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierCatalogEntryEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<SupplierCatalogEntryEntity>
 */
final class SupplierCatalogEntryEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return SupplierCatalogEntryEntity::class;
    }

    public function active(): self
    {
        return $this->with(['status' => 'ACTIVE']);
    }

    public function discontinued(): self
    {
        return $this->with(['status' => 'DISCONTINUED']);
    }

    public function forSupplier(Uuid $supplierId): self
    {
        return $this->with(['supplierId' => $supplierId]);
    }

    protected function defaults(): array
    {
        $now = new \DateTimeImmutable();

        return [
            'id'                    => Uuid::v7(),
            'supplierId'            => Uuid::v7(),
            'productCode'           => strtoupper(self::faker()->unique()->lexify('???-????')),
            'name'                  => self::faker()->words(3, true),
            'gtin'                  => null,
            'catalogPriceMinor'     => self::faker()->numberBetween(100, 50000),
            'catalogPriceCurrency'  => 'EUR',
            'catalogPriceValidFrom' => new \DateTimeImmutable('2026-01-01'),
            'catalogPriceValidTo'   => null,
            'packagingUnit'         => null,
            'packagingAmount'       => null,
            'status'                => 'ACTIVE',
            'version'               => 1,
            'createdAt'             => $now,
            'updatedAt'             => $now,
        ];
    }
}
