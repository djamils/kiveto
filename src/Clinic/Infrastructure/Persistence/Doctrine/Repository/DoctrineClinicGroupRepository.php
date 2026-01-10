<?php

declare(strict_types=1);

namespace App\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Clinic\Domain\ClinicGroup;
use App\Clinic\Domain\Repository\ClinicGroupRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicGroupEntity;
use App\Clinic\Infrastructure\Persistence\Doctrine\Mapper\ClinicGroupMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineClinicGroupRepository implements ClinicGroupRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClinicGroupMapper $mapper,
    ) {
    }

    public function save(ClinicGroup $clinicGroup): void
    {
        $entity = $this->mapper->toEntity($clinicGroup);

        $existing = $this->em->find(ClinicGroupEntity::class, $entity->getId());

        if (null === $existing) {
            $this->em->persist($entity);
        } else {
            $existing->setName($entity->getName());
            $existing->setStatus($entity->getStatus());
        }

        $this->em->flush();
    }

    public function findById(ClinicGroupId $id): ?ClinicGroup
    {
        $entity = $this->em->getRepository(ClinicGroupEntity::class)->find(
            Uuid::fromString($id->toString()),
        );

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }
}
