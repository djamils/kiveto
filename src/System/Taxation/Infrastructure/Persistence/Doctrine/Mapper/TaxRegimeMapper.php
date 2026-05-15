<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Persistence\Doctrine\Mapper;

use App\Shared\Domain\ValueObject\CountryCode;
use App\System\Taxation\Domain\TaxRegime;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity\TaxRateEntity;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity\TaxRegimeEntity;

final readonly class TaxRegimeMapper
{
    public function __construct(private TaxRateMapper $rateMapper)
    {
    }

    /**
     * @param list<TaxRateEntity> $rateEntities
     */
    public function toDomain(TaxRegimeEntity $entity, array $rateEntities): TaxRegime
    {
        $rates = array_map(fn (TaxRateEntity $e) => $this->rateMapper->toDomain($e), $rateEntities);

        return TaxRegime::reconstitute(
            TaxRegimeId::fromString($entity->getId()),
            $entity->getName(),
            CountryCode::fromString($entity->getCountryCode()),
            $rates,
            $entity->isActive(),
            $entity->getLoadedAt(),
            $entity->getUpdatedAt(),
        );
    }

    public function toEntity(TaxRegime $regime): TaxRegimeEntity
    {
        $entity = new TaxRegimeEntity();
        $entity->setId($regime->id()->toString());
        $entity->setName($regime->name());
        $entity->setCountryCode($regime->country()->toString());
        $entity->setActive($regime->isActive());
        $entity->setLoadedAt($regime->loadedAt());
        $entity->setUpdatedAt($regime->updatedAt());

        return $entity;
    }
}
