<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Procurement\Factory;

use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<SupplierEntity>
 */
final class SupplierEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return SupplierEntity::class;
    }

    public function active(): self
    {
        return $this->with(['status' => 'ACTIVE']);
    }

    public function archived(): self
    {
        return $this->with(['status' => 'ARCHIVED']);
    }

    public function centrale(): self
    {
        return $this->with(['type' => 'CENTRALE']);
    }

    public function laboratory(): self
    {
        return $this->with(['type' => 'LABORATORY']);
    }

    protected function defaults(): array
    {
        $now = new \DateTimeImmutable();

        return [
            'id'                        => Uuid::v7(),
            'name'                      => self::faker()->company(),
            'code'                      => strtoupper(self::faker()->unique()->lexify('SUP-????')),
            'type'                      => self::faker()->randomElement(['CENTRALE', 'LABORATORY', 'DIVERS']),
            'countryCode'               => 'FR',
            'defaultCurrency'           => 'EUR',
            'integrationMode'           => 'SIMULATION',
            'adapterIdentifier'         => null,
            'status'                    => 'ACTIVE',
            'version'                   => 1,
            'contactEmail'              => null,
            'contactPhone'              => null,
            'contactPerson'             => null,
            'contactAddressStreet'      => null,
            'contactAddressLine2'       => null,
            'contactPostalCode'         => null,
            'contactCity'               => null,
            'contactAddressCountryCode' => null,
            'createdAt'                 => $now,
            'updatedAt'                 => $now,
        ];
    }
}
