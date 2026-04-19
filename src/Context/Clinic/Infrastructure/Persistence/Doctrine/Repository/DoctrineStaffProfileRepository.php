<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Domain\Staff\Repository\StaffProfileRepositoryInterface;
use App\Context\Clinic\Domain\Staff\StaffProfile;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\StaffProfileEntity;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Mapper\StaffProfileMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineStaffProfileRepository implements StaffProfileRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StaffProfileMapper $mapper,
    ) {
    }

    public function save(StaffProfile $profile): void
    {
        $repository = $this->entityManager->getRepository(StaffProfileEntity::class);
        $entity     = $repository->find(Uuid::fromString($profile->id()->toString()));

        if (null === $entity) {
            $entity = $this->mapper->toEntity($profile);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->toEntity($profile, $entity);
        }

        $this->entityManager->flush();
    }

    public function findById(StaffProfileId $id): ?StaffProfile
    {
        $repository = $this->entityManager->getRepository(StaffProfileEntity::class);
        $entity     = $repository->find(Uuid::fromString($id->toString()));

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function findByMembershipId(ClinicMembershipId $membershipId): ?StaffProfile
    {
        $repository = $this->entityManager->getRepository(StaffProfileEntity::class);

        $entity = $repository->findOneBy([
            'membershipId' => Uuid::fromString($membershipId->toString()),
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }
}
