<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Persistence\Doctrine\Repository;

use App\System\Taxation\Domain\Repository\TaxCategoryRepositoryInterface;
use App\System\Taxation\Domain\TaxCategory;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity\TaxCategoryEntity;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Mapper\TaxCategoryMapper;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineTaxCategoryRepository implements TaxCategoryRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private TaxCategoryMapper $mapper,
    ) {
    }

    public function save(TaxCategory $category): void
    {
        $entity   = $this->mapper->toEntity($category);
        $existing = $this->em->find(TaxCategoryEntity::class, $entity->getCode());

        if (null === $existing) {
            $this->em->persist($entity);
        } else {
            $existing->setDisplayName($entity->getDisplayName());
            $existing->setDescription($entity->getDescription());
            $existing->setActive($entity->isActive());
            $existing->setUpdatedAt($entity->getUpdatedAt());
        }

        $this->em->flush();
    }

    public function findByCode(TaxCategoryCode $code): ?TaxCategory
    {
        $entity = $this->em->find(TaxCategoryEntity::class, $code->toString());

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    /** @return list<TaxCategory> */
    public function findAll(): array
    {
        /** @var list<TaxCategoryEntity> $entities */
        $entities = $this->em->getRepository(TaxCategoryEntity::class)->findAll();

        return array_map(fn (TaxCategoryEntity $e) => $this->mapper->toDomain($e), $entities);
    }

    /** @return list<TaxCategory> */
    public function findAllActive(): array
    {
        /** @var list<TaxCategoryEntity> $entities */
        $entities = $this->em->getRepository(TaxCategoryEntity::class)->findBy(['active' => true]);

        return array_map(fn (TaxCategoryEntity $e) => $this->mapper->toDomain($e), $entities);
    }
}
