<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Procurement\Factory;

use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierAccountEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<SupplierAccountEntity>
 */
final class SupplierAccountEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return SupplierAccountEntity::class;
    }

    public function active(): self
    {
        return $this->with(['status' => 'ACTIVE']);
    }

    public function suspended(): self
    {
        return $this->with(['status' => 'SUSPENDED']);
    }

    public function forClinic(Uuid $clinicId): self
    {
        return $this->with(['clinicId' => $clinicId]);
    }

    protected function defaults(): array
    {
        $now = new \DateTimeImmutable();

        return [
            'id'                  => Uuid::v7(),
            'clinicId'            => Uuid::v7(),
            'supplierId'          => Uuid::v7(),
            'customerCode'        => self::faker()->numerify('########'),
            'status'              => 'ACTIVE',
            'billingAddressJson'  => null,
            'deliveryAddressJson' => null,
            'notes'               => null,
            'version'             => 1,
            'createdAt'           => $now,
            'updatedAt'           => $now,
        ];
    }
}
