<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Catalog\Factory;

use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageStatus;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PackageEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<PackageEntity>
 */
final class PackageEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return PackageEntity::class;
    }

    public function archived(): self
    {
        return $this->with(['status' => PackageStatus::ARCHIVED]);
    }

    public function forClinic(string $clinicId): self
    {
        return $this->with(['tenantId' => Uuid::fromString($clinicId)]);
    }

    protected function defaults(): array
    {
        $now = \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-6 months'));

        return [
            'id'                   => Uuid::v7(),
            'tenantId'             => Uuid::v7(),
            'name'                 => self::faker()->sentence(3),
            'code'                 => strtoupper(self::faker()->unique()->lexify('PKG-???')),
            'taxCategoryCode'      => 'veterinary.act.consultation',
            'pricingMode'          => PackagePricingMode::SUM_OF_COMPONENTS,
            'fixedPriceMinorUnits' => null,
            'fixedPriceCurrency'   => null,
            'status'               => PackageStatus::ACTIVE,
            'createdAt'            => $now,
            'updatedAt'            => $now,
        ];
    }
}
