<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Procurement\Factory;

use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierPricingEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<SupplierPricingEntity>
 */
final class SupplierPricingEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return SupplierPricingEntity::class;
    }

    public function forClinic(Uuid $clinicId): self
    {
        return $this->with(['clinicId' => $clinicId]);
    }

    public function forSupplier(Uuid $supplierId): self
    {
        return $this->with(['supplierId' => $supplierId]);
    }

    public function forCatalogEntry(Uuid $catalogEntryId): self
    {
        return $this->with(['catalogEntryId' => $catalogEntryId]);
    }

    protected function defaults(): array
    {
        $now = new \DateTimeImmutable();

        return [
            'id'                 => Uuid::v7(),
            'clinicId'           => Uuid::v7(),
            'supplierId'         => Uuid::v7(),
            'catalogEntryId'     => Uuid::v7(),
            'amountMinor'        => self::faker()->numberBetween(100, 50000),
            'amountCurrency'     => 'EUR',
            'discountPercentage' => null,
            'pricingNotes'       => null,
            'expiresAt'          => null,
            'negotiatedAt'       => $now,
            'version'            => 1,
            'createdAt'          => $now,
            'updatedAt'          => $now,
        ];
    }
}
