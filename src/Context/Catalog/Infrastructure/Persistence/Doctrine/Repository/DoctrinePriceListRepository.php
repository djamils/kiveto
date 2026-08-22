<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Catalog\Domain\Pricing\PriceList;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PriceListEntity;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PriceListItemEntity;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PriceRuleEntity;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Mapper\PriceListItemMapper;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Mapper\PriceListMapper;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Mapper\PriceRuleMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePriceListRepository implements PriceListRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private PriceListMapper $mapper,
        private PriceListItemMapper $itemMapper,
        private PriceRuleMapper $ruleMapper,
    ) {
    }

    public function save(PriceList $priceList): void
    {
        $uuid     = Uuid::fromString($priceList->id()->toString());
        $existing = $this->em->find(PriceListEntity::class, $uuid);

        $itemEntities = $this->em->getRepository(PriceListItemEntity::class)->findBy([
            'priceListId' => $uuid,
        ]);

        $ruleEntities = $this->em->getRepository(PriceRuleEntity::class)->findBy([
            'priceListId' => $uuid,
        ]);

        if (null === $existing) {
            $entity = $this->mapper->toEntity($priceList, $itemEntities, $ruleEntities, null);
            $this->em->persist($entity);
        } else {
            $this->mapper->toEntity($priceList, $itemEntities, $ruleEntities, $existing);
        }

        // Items and rules keep their identity across saves, so each one is
        // updated where it stands; wiping the rows and re-inserting them under
        // the same ids collides with what the identity map already holds.
        $knownItems = [];

        foreach ($itemEntities as $itemEntity) {
            $knownItems[$itemEntity->getId()->toString()] = $itemEntity;
        }

        foreach ($priceList->items() as $item) {
            $itemId       = $item->id()->toString();
            $existingItem = $knownItems[$itemId] ?? null;
            $itemEntity   = $this->itemMapper->toEntity($item, $priceList->id(), $existingItem);

            if (null === $existingItem) {
                $this->em->persist($itemEntity);
            }

            unset($knownItems[$itemId]);
        }

        foreach ($knownItems as $orphan) {
            $this->em->remove($orphan);
        }

        $knownRules = [];

        foreach ($ruleEntities as $ruleEntity) {
            $knownRules[$ruleEntity->getId()->toString()] = $ruleEntity;
        }

        foreach ($priceList->rules() as $rule) {
            $ruleId       = $rule->id()->toString();
            $existingRule = $knownRules[$ruleId] ?? null;
            $ruleEntity   = $this->ruleMapper->toEntity($rule, $priceList->id(), $existingRule);

            if (null === $existingRule) {
                $this->em->persist($ruleEntity);
            }

            unset($knownRules[$ruleId]);
        }

        foreach ($knownRules as $orphan) {
            $this->em->remove($orphan);
        }

        $this->em->flush();
    }

    public function findById(PriceListId $id, ClinicId $clinicId): ?PriceList
    {
        $uuid   = Uuid::fromString($id->toString());
        $entity = $this->em->getRepository(PriceListEntity::class)->findOneBy([
            'id'       => $uuid,
            'tenantId' => Uuid::fromString($clinicId->toString()),
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->hydrate($entity);
    }

    public function findDefaultForClinic(ClinicId $clinicId): ?PriceList
    {
        $entity = $this->em->getRepository(PriceListEntity::class)->findOneBy([
            'tenantId'  => Uuid::fromString($clinicId->toString()),
            'isDefault' => true,
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->hydrate($entity);
    }

    public function hasDefaultForClinic(ClinicId $clinicId): bool
    {
        $count = $this->em->getRepository(PriceListEntity::class)->count([
            'tenantId'  => Uuid::fromString($clinicId->toString()),
            'isDefault' => true,
        ]);

        return $count > 0;
    }

    private function hydrate(PriceListEntity $entity): PriceList
    {
        /** @var list<PriceListItemEntity> $itemEntities */
        $itemEntities = $this->em->getRepository(PriceListItemEntity::class)->findBy([
            'priceListId' => $entity->getId(),
        ]);

        /** @var list<PriceRuleEntity> $ruleEntities */
        $ruleEntities = $this->em->getRepository(PriceRuleEntity::class)->findBy([
            'priceListId' => $entity->getId(),
        ]);

        return $this->mapper->toDomain($entity, $itemEntities, $ruleEntities);
    }
}
