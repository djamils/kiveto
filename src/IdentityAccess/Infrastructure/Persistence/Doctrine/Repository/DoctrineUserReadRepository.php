<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\IdentityAccess\Application\Port\UserReadRepositoryInterface;
use App\IdentityAccess\Application\Query\ListUsers\UserCollection;
use App\IdentityAccess\Application\Query\ListUsers\UserListItem;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserReadRepository implements UserReadRepositoryInterface
{
    private string $tableName;

    public function __construct(
        private Connection $connection,
        EntityManagerInterface $entityManager,
    ) {
        $this->tableName = $entityManager->getClassMetadata(UserEntity::class)->getTableName();
    }

    public function listAll(
        ?string $search = null,
        ?string $type = null,
        ?string $status = null,
    ): UserCollection {
        $qb = $this->connection->createQueryBuilder()
            ->select(
                'BIN_TO_UUID(id) AS id',
                'email',
                'type',
                'status',
                'email_verified_at',
                'created_at'
            )
            ->from($this->tableName)
            ->orderBy('created_at', 'DESC')
        ;

        if (null !== $search && '' !== $search) {
            $qb->andWhere('email LIKE :search')
                ->setParameter('search', '%' . $search . '%')
            ;
        }

        if (null !== $type && '' !== $type) {
            $qb->andWhere('type = :type')
                ->setParameter('type', $type)
            ;
        }

        if (null !== $status && '' !== $status) {
            $qb->andWhere('status = :status')
                ->setParameter('status', $status)
            ;
        }

        $results = $qb->executeQuery()->fetchAllAssociative();

        $users = array_map(
            static function (array $row): UserListItem {
                \assert(\is_string($row['id']));
                \assert(\is_string($row['email']));
                \assert(\is_string($row['type']));
                \assert(\is_string($row['status']));
                \assert(\is_string($row['created_at']));

                $emailVerifiedAt = null;
                if (null !== $row['email_verified_at']) {
                    \assert(\is_string($row['email_verified_at']));
                    $emailVerifiedAt = new \DateTimeImmutable($row['email_verified_at']);
                }

                return new UserListItem(
                    id: $row['id'],
                    email: $row['email'],
                    type: $row['type'],
                    status: $row['status'],
                    emailVerifiedAt: $emailVerifiedAt,
                    createdAt: new \DateTimeImmutable($row['created_at']),
                );
            },
            $results
        );

        return new UserCollection(
            users: $users,
            total: \count($users),
        );
    }
}
