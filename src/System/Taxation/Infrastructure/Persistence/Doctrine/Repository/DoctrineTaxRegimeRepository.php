<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Persistence\Doctrine\Repository;

use App\System\Taxation\Domain\Repository\TaxRegimeRepositoryInterface;
use App\System\Taxation\Domain\TaxRegime;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity\TaxRateEntity;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity\TaxRegimeEntity;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Mapper\TaxRateMapper;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Mapper\TaxRegimeMapper;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineTaxRegimeRepository implements TaxRegimeRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private TaxRegimeMapper $mapper,
        private TaxRateMapper $rateMapper,
    ) {
    }

    public function save(TaxRegime $regime): void
    {
        $entity   = $this->mapper->toEntity($regime);
        $existing = $this->em->find(TaxRegimeEntity::class, $entity->getId());

        if (null === $existing) {
            $this->em->persist($entity);
        } else {
            $existing->setName($entity->getName());
            $existing->setCountryCode($entity->getCountryCode());
            $existing->setActive($entity->isActive());
            $existing->setLoadedAt($entity->getLoadedAt());
            $existing->setUpdatedAt($entity->getUpdatedAt());
        }

        // Flush regime entity first so it's persisted before clearing the identity map
        $this->em->flush();

        // Batch DELETE existing rates; bypasses the Doctrine identity map
        $this->em->createQueryBuilder()
            ->delete(TaxRateEntity::class, 'r')
            ->where('r.regimeId = :id')
            ->setParameter('id', $regime->id()->toString())
            ->getQuery()
            ->execute()
        ;

        // Reset the identity map so same-ID rate entities can be re-persisted without collision
        $this->em->clear();

        foreach ($regime->rates() as $rate) {
            $this->em->persist($this->rateMapper->toEntity($rate, $regime->id()->toString()));
        }

        $this->em->flush();
    }

    public function findById(TaxRegimeId $id): ?TaxRegime
    {
        $entity = $this->em->find(TaxRegimeEntity::class, $id->toString());

        if (null === $entity) {
            return null;
        }

        /** @var list<TaxRateEntity> $rateEntities */
        $rateEntities = $this->em->getRepository(TaxRateEntity::class)->findBy(['regimeId' => $id->toString()]);

        return $this->mapper->toDomain($entity, $rateEntities);
    }

    /** @return list<TaxRegime> */
    public function findAllActive(): array
    {
        /** @var list<TaxRegimeEntity> $entities */
        $entities = $this->em->getRepository(TaxRegimeEntity::class)->findBy(['active' => true]);
        $result   = [];

        foreach ($entities as $entity) {
            /** @var list<TaxRateEntity> $rateEntities */
            $rateEntities = $this->em->getRepository(TaxRateEntity::class)->findBy(['regimeId' => $entity->getId()]);
            $result[]     = $this->mapper->toDomain($entity, $rateEntities);
        }

        return $result;
    }
}
