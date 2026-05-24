<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Catalog\Factory;

use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListStatus;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PriceListEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<PriceListEntity>
 */
final class PriceListEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return PriceListEntity::class;
    }

    public function asDefault(): self
    {
        return $this->with(['isDefault' => true]);
    }

    public function forClinic(string $clinicId): self
    {
        return $this->with(['tenantId' => Uuid::fromString($clinicId)]);
    }

    protected function defaults(): array
    {
        $now = \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-6 months'));

        return [
            'id'        => Uuid::v7(),
            'tenantId'  => Uuid::v7(),
            'name'      => 'Tarifs standard',
            'isDefault' => false,
            'status'    => PriceListStatus::ACTIVE,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
    }
}
