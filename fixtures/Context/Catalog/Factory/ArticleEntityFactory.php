<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Catalog\Factory;

use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleStatus;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ArticleEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ArticleEntity>
 */
final class ArticleEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ArticleEntity::class;
    }

    public function drug(): self
    {
        return $this->with([
            'kind'             => ArticleKind::DRUG,
            'taxCategoryCode'  => 'veterinary.drug.prescription',
            'unitOfMeasure'    => 'TABLET',
            'drugRequiresRx'   => true,
            'drugIsControlled' => false,
        ]);
    }

    public function archived(): self
    {
        return $this->with(['status' => ArticleStatus::ARCHIVED]);
    }

    public function forClinic(string $clinicId): self
    {
        return $this->with(['tenantId' => Uuid::fromString($clinicId)]);
    }

    protected function defaults(): array
    {
        $now = \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-6 months'));

        return [
            'id'                    => Uuid::v7(),
            'tenantId'              => Uuid::v7(),
            'name'                  => self::faker()->sentence(3),
            'code'                  => strtoupper(self::faker()->unique()->lexify('ART-???')),
            'kind'                  => ArticleKind::CONSUMABLE,
            'gtin'                  => null,
            'taxCategoryCode'       => 'veterinary.consumable.standard',
            'basePriceMinorUnits'   => self::faker()->numberBetween(100, 10000),
            'basePriceCurrency'     => 'EUR',
            'unitOfMeasure'         => 'UNIT',
            'drugAuthRef'           => null,
            'drugRequiresRx'        => null,
            'drugPrescriptionClass' => null,
            'drugIsControlled'      => null,
            'trackStock'            => true,
            'status'                => ArticleStatus::ACTIVE,
            'createdAt'             => $now,
            'updatedAt'             => $now,
        ];
    }
}
