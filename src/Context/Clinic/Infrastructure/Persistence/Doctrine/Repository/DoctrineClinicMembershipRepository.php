<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicMembershipEntity;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Mapper\ClinicMembershipMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineClinicMembershipRepository implements ClinicMembershipRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClinicMembershipMapper $mapper,
    ) {
    }

    public function save(ClinicMembership $membership): void
    {
        $repository = $this->entityManager->getRepository(ClinicMembershipEntity::class);
        $entity     = $repository->find(Uuid::fromString($membership->id()->toString()));

        if (null === $entity) {
            $entity = $this->mapper->toEntity($membership);
            $this->entityManager->persist($entity);
        } else {
            $entity->setRole($membership->role());
            $entity->setEngagement($membership->engagement());
            $entity->setStatus($membership->status());
            $entity->setValidFrom($membership->validFrom());
            $entity->setValidUntil($membership->validUntil());
        }

        $this->entityManager->flush();
    }

    public function findById(ClinicMembershipId $id): ?ClinicMembership
    {
        $repository = $this->entityManager->getRepository(ClinicMembershipEntity::class);
        $entity     = $repository->find(Uuid::fromString($id->toString()));

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function findByClinicAndUser(ClinicId $clinicId, UserId $userId): ?ClinicMembership
    {
        $repository = $this->entityManager->getRepository(ClinicMembershipEntity::class);

        $entity = $repository->findOneBy([
            'clinicId' => Uuid::fromString($clinicId->toString()),
            'userId'   => Uuid::fromString($userId->toString()),
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function existsByClinicAndUser(ClinicId $clinicId, UserId $userId): bool
    {
        $repository = $this->entityManager->getRepository(ClinicMembershipEntity::class);

        $count = $repository->count([
            'clinicId' => Uuid::fromString($clinicId->toString()),
            'userId'   => Uuid::fromString($userId->toString()),
        ]);

        return $count > 0;
    }
}
