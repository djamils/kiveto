<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Repository;

use App\System\PharmaceuticalRegistry\Domain\ActiveSubstance;
use App\System\PharmaceuticalRegistry\Domain\Repository\ActiveSubstanceRepositoryInterface;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\ActiveSubstanceEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Mapper\ActiveSubstanceMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineActiveSubstanceRepository implements ActiveSubstanceRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActiveSubstanceMapper $mapper,
    ) {
    }

    public function save(ActiveSubstance $substance): void
    {
        $existing = $this->em->find(ActiveSubstanceEntity::class, Uuid::fromString($substance->id()->toString()));

        if (null === $existing) {
            $entity = $this->mapper->toEntity($substance);
            $this->em->persist($entity);
        } else {
            $existing->setLabel($substance->label());
            $existing->setLabelNormalized($substance->labelNormalized());
            $existing->setInnCode($substance->innCode());
            $existing->setUpdatedAt($substance->updatedAt());
        }

        $this->em->flush();
    }

    public function findByNormalizedLabel(string $normalizedLabel): ?ActiveSubstance
    {
        $entity = $this->em->getRepository(ActiveSubstanceEntity::class)
            ->findOneBy(['labelNormalized' => $normalizedLabel])
        ;

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }
}
