<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicEntity;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Mapper\ClinicMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineClinicRepository implements ClinicRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClinicMapper $mapper,
    ) {
    }

    public function save(Clinic $clinic): void
    {
        $entity = $this->mapper->toEntity($clinic);

        $existing = $this->em->find(ClinicEntity::class, $entity->getId());

        if (null === $existing) {
            $this->em->persist($entity);
        } else {
            $existing->setName($entity->getName());
            $existing->setSlug($entity->getSlug());
            $existing->setTimeZone($entity->getTimeZone());
            $existing->setLocale($entity->getLocale());
            $existing->setStatus($entity->getStatus());
            $existing->setClinicGroupId($entity->getClinicGroupId());
            $existing->setUpdatedAt($entity->getUpdatedAt());
        }

        $this->em->flush();
    }

    public function findById(ClinicId $id): ?Clinic
    {
        $entity = $this->em->getRepository(ClinicEntity::class)->find(
            Uuid::fromString($id->toString()),
        );

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function findBySlug(ClinicSlug $slug): ?Clinic
    {
        $entity = $this->em->getRepository(ClinicEntity::class)->findOneBy([
            'slug' => $slug->toString(),
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function existsBySlug(ClinicSlug $slug): bool
    {
        $count = $this->em->getRepository(ClinicEntity::class)->count([
            'slug' => $slug->toString(),
        ]);

        return $count > 0;
    }
}
