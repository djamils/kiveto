<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\IdentityAccess\Application\Port\UserReadRepositoryInterface;
use App\System\IdentityAccess\Application\Query\GetAccountSummaries\AccountSummary;
use App\System\IdentityAccess\Application\Query\ListUsers\UserCollection;
use App\System\IdentityAccess\Application\Query\ListUsers\UserListItem;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineUserReadRepository implements UserReadRepositoryInterface
{
    private const string SUMMARY_COLUMNS = 'BIN_TO_UUID(id) AS id, type, email, phone, status, created_at, last_login_at';

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
                \assert(null === $row['email'] || \is_string($row['email']));
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

    public function statuses(array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(id) AS id, status FROM ' . $this->tableName . ' WHERE id IN (:ids)',
            ['ids' => array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $userIds)],
            ['ids' => ArrayParameterType::BINARY],
        );

        $statuses = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['id']) && \is_string($row['status']));
            $statuses[$row['id']] = $row['status'];
        }

        return $statuses;
    }

    public function summaries(array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT ' . self::SUMMARY_COLUMNS . ' FROM ' . $this->tableName . ' WHERE id IN (:ids)',
            ['ids' => array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $userIds)],
            ['ids' => ArrayParameterType::BINARY],
        );

        $summaries = [];
        foreach ($rows as $row) {
            $summary                 = self::summary($row);
            $summaries[$summary->id] = $summary;
        }

        return $summaries;
    }

    public function accounts(UserType $type, ?string $text, int $offset, int $limit): array
    {
        [$where, $params] = self::accountFilter($type, $text);

        return array_map(self::summary(...), $this->connection->fetchAllAssociative(
            'SELECT ' . self::SUMMARY_COLUMNS . ' FROM ' . $this->tableName . $where
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset),
            $params,
        ));
    }

    public function countAccounts(UserType $type, ?string $text): int
    {
        [$where, $params] = self::accountFilter($type, $text);
        $count            = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $this->tableName . $where, $params);

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private static function accountFilter(UserType $type, ?string $text): array
    {
        $params = ['type' => $type->value];
        $where  = ' WHERE type = :type';

        if (null !== $text && '' !== trim($text)) {
            $where .= ' AND (email LIKE :text OR phone LIKE :text)';
            $params['text'] = '%' . addcslashes(trim($text), '%_\\') . '%';
        }

        return [$where, $params];
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function summary(array $row): AccountSummary
    {
        $lastLogin = RowAccessor::nullableString($row, 'last_login_at');

        return new AccountSummary(
            id: RowAccessor::string($row, 'id'),
            type: RowAccessor::string($row, 'type'),
            email: RowAccessor::nullableString($row, 'email'),
            phone: RowAccessor::nullableString($row, 'phone'),
            status: RowAccessor::string($row, 'status'),
            createdAt: new \DateTimeImmutable(RowAccessor::string($row, 'created_at'), new \DateTimeZone('UTC')),
            lastLoginAt: null !== $lastLogin ? new \DateTimeImmutable($lastLogin, new \DateTimeZone('UTC')) : null,
        );
    }
}
