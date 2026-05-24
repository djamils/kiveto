<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Catalog\Domain\Pricing\PriceList;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListName;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PriceListEntity;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PriceListItemEntity;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PriceRuleEntity;
use Symfony\Component\Uid\Uuid;

final readonly class PriceListMapper
{
    public function __construct(
        private PriceListItemMapper $itemMapper,
        private PriceRuleMapper $ruleMapper,
    ) {
    }

    /**
     * @param list<PriceListItemEntity> $itemEntities
     * @param list<PriceRuleEntity>     $ruleEntities
     */
    public function toEntity(
        PriceList $priceList,
        array $itemEntities,
        array $ruleEntities,
        ?PriceListEntity $existing = null,
    ): PriceListEntity {
        $entity = $existing ?? new PriceListEntity();

        $entity->setId(Uuid::fromString($priceList->id()->toString()));
        $entity->setTenantId(Uuid::fromString($priceList->clinicId()->toString()));
        $entity->setName($priceList->name()->toString());
        $entity->setIsDefault($priceList->isDefault());
        $entity->setStatus($priceList->status());
        $entity->setCreatedAt($priceList->createdAt());
        $entity->setUpdatedAt($priceList->updatedAt());

        return $entity;
    }

    /**
     * @param list<PriceListItemEntity> $itemEntities
     * @param list<PriceRuleEntity>     $ruleEntities
     */
    public function toDomain(
        PriceListEntity $entity,
        array $itemEntities,
        array $ruleEntities,
    ): PriceList {
        $items = array_map(
            fn (PriceListItemEntity $e) => $this->itemMapper->toDomain($e),
            $itemEntities,
        );

        $rules = array_map(
            fn (PriceRuleEntity $e) => $this->ruleMapper->toDomain($e),
            $ruleEntities,
        );

        return PriceList::reconstitute(
            id: PriceListId::fromString($entity->getId()->toString()),
            clinicId: ClinicId::fromString($entity->getTenantId()->toString()),
            name: PriceListName::fromString($entity->getName()),
            isDefault: $entity->getIsDefault(),
            status: $entity->getStatus(),
            items: $items,
            rules: $rules,
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }
}
