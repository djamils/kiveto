<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Infrastructure\Persistence\Doctrine;

use App\Context\Regulatory\Domain\Repository\StrayCustodyRepositoryInterface;
use App\Context\Regulatory\Domain\StrayCustody;
use App\Context\Regulatory\Domain\ValueObject\StrayCustodyStatus;
use App\Context\Regulatory\Infrastructure\Persistence\Doctrine\Entity\StrayCustodyEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineStrayCustodyRepository implements StrayCustodyRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StrayCustodyMapper $mapper,
    ) {
    }

    public function save(StrayCustody $custody): void
    {
        $uuid       = Uuid::fromString($custody->id()->value());
        $repository = $this->entityManager->getRepository(StrayCustodyEntity::class);
        $entity     = $repository->find($uuid);

        if (null === $entity) {
            $entity = $this->mapper->toEntity($custody);
            $this->entityManager->persist($entity);
        } else {
            $entity->setStatus($custody->status());
            $entity->setUpdatedAt($custody->updatedAt());
        }

        $this->entityManager->flush();
    }

    public function findByAdmissionId(string $admissionId): ?StrayCustody
    {
        $admissionUuid = Uuid::fromString($admissionId);
        $repository    = $this->entityManager->getRepository(StrayCustodyEntity::class);

        $entity = $repository->findOneBy(['admissionId' => $admissionUuid]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function findActiveByPatientId(string $patientId): ?StrayCustody
    {
        $patientUuid = Uuid::fromString($patientId);
        $repository  = $this->entityManager->getRepository(StrayCustodyEntity::class);

        $entity = $repository->findOneBy([
            'patientId' => $patientUuid,
            'status'    => StrayCustodyStatus::Active,
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }
}
