<?php

declare(strict_types=1);

namespace App\System\AccessControl\Infrastructure\Persistence\Doctrine\Repository;

use App\System\AccessControl\Domain\Repository\RolePermissionRepositoryInterface;
use App\System\AccessControl\Domain\ValueObject\Permission;
use App\System\AccessControl\Infrastructure\Persistence\Doctrine\Entity\RolePermissionEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineRolePermissionRepository implements RolePermissionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function saveAll(string $roleKey, Permission ...$permissions): void
    {
        $repository = $this->entityManager->getRepository(RolePermissionEntity::class);

        foreach ($permissions as $permission) {
            $existing = $repository->findOneBy([
                'roleKey'    => $roleKey,
                'permission' => $permission->toString(),
            ]);

            if (null !== $existing) {
                continue;
            }

            $entity = new RolePermissionEntity();
            $entity->setId(Uuid::v7());
            $entity->setRoleKey($roleKey);
            $entity->setPermission($permission->toString());

            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }

    /**
     * @return list<Permission>
     */
    public function findByRoleKey(string $roleKey): array
    {
        $repository = $this->entityManager->getRepository(RolePermissionEntity::class);
        $entities   = $repository->findBy(['roleKey' => $roleKey]);

        return array_map(
            static fn (RolePermissionEntity $entity): Permission => Permission::fromString($entity->getPermission()),
            $entities
        );
    }

    public function deleteAll(): void
    {
        $connection = $this->entityManager->getConnection();
        $tableName  = $this->entityManager->getClassMetadata(RolePermissionEntity::class)->getTableName();

        $connection->executeStatement(\sprintf('DELETE FROM %s', $tableName));
    }
}
