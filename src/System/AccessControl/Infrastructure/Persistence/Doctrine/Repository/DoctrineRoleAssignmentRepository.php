<?php

declare(strict_types=1);

namespace App\System\AccessControl\Infrastructure\Persistence\Doctrine\Repository;

use App\System\AccessControl\Domain\Repository\RoleAssignmentRepositoryInterface;
use App\System\AccessControl\Domain\RoleAssignment;
use App\System\AccessControl\Domain\ValueObject\SubjectId;
use App\System\AccessControl\Domain\ValueObject\TenantId;
use App\System\AccessControl\Infrastructure\Persistence\Doctrine\Entity\RoleAssignmentEntity;
use App\System\AccessControl\Infrastructure\Persistence\Doctrine\Mapper\RoleAssignmentMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineRoleAssignmentRepository implements RoleAssignmentRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RoleAssignmentMapper $mapper,
    ) {
    }

    public function save(RoleAssignment $assignment): void
    {
        $repository = $this->entityManager->getRepository(RoleAssignmentEntity::class);
        $entity     = $repository->findOneBy([
            'subjectId' => Uuid::fromString($assignment->subjectId()->toString()),
            'tenantId'  => Uuid::fromString($assignment->tenantId()->toString()),
        ]);

        if (null === $entity) {
            $entity = $this->mapper->toEntity($assignment, Uuid::v7());
            $this->entityManager->persist($entity);
        } else {
            $entity->setRoleKey($assignment->roleKey());
        }

        $this->entityManager->flush();
    }

    public function findBySubjectAndTenant(SubjectId $subjectId, TenantId $tenantId): ?RoleAssignment
    {
        $repository = $this->entityManager->getRepository(RoleAssignmentEntity::class);
        $entity     = $repository->findOneBy([
            'subjectId' => Uuid::fromString($subjectId->toString()),
            'tenantId'  => Uuid::fromString($tenantId->toString()),
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function deleteBySubjectAndTenant(SubjectId $subjectId, TenantId $tenantId): void
    {
        $repository = $this->entityManager->getRepository(RoleAssignmentEntity::class);
        $entity     = $repository->findOneBy([
            'subjectId' => Uuid::fromString($subjectId->toString()),
            'tenantId'  => Uuid::fromString($tenantId->toString()),
        ]);

        if (null !== $entity) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
