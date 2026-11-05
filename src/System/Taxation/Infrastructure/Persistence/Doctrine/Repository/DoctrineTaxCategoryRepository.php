<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Persistence\Doctrine\Repository;

use App\System\Taxation\Domain\Repository\TaxCategoryRepositoryInterface;
use App\System\Taxation\Domain\TaxCategory;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity\TaxCategoryEntity;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Mapper\TaxCategoryMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The categories are reference data: once one is looked up, all of them are
 * kept by code for the request. A save forgets them, and so does the kernel
 * reset between requests and worker messages.
 */
final class DoctrineTaxCategoryRepository implements TaxCategoryRepositoryInterface, ResetInterface
{
    /** @var array<string, TaxCategory>|null */
    private ?array $byCode = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TaxCategoryMapper $mapper,
    ) {
    }

    public function reset(): void
    {
        $this->byCode = null;
    }

    public function save(TaxCategory $category): void
    {
        $this->byCode = null;

        $entity   = $this->mapper->toEntity($category);
        $existing = $this->em->find(TaxCategoryEntity::class, $entity->getCode());

        if (null === $existing) {
            $this->em->persist($entity);
        } else {
            $existing->setDisplayName($entity->getDisplayName());
            $existing->setDescription($entity->getDescription());
            $existing->setNature($entity->getNature());
            $existing->setActive($entity->isActive());
            $existing->setUpdatedAt($entity->getUpdatedAt());
        }

        $this->em->flush();
    }

    public function findByCode(TaxCategoryCode $code): ?TaxCategory
    {
        // A page totals lines of many categories: the first lookup reads them all.
        if (null === $this->byCode) {
            $this->byCode = [];

            foreach ($this->findAll() as $category) {
                $this->byCode[$category->code()->toString()] = $category;
            }
        }

        return $this->byCode[$code->toString()] ?? null;
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
