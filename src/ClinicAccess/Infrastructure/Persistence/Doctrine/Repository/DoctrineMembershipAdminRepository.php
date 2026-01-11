<?php

declare(strict_types=1);

namespace App\ClinicAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\ClinicAccess\Application\Port\MembershipAdminRepositoryInterface;
use App\ClinicAccess\Application\Query\ListAllMemberships\MembershipCollection;
use App\ClinicAccess\Application\Query\ListAllMemberships\MembershipListItem;
use App\ClinicAccess\Domain\ValueObject\ClinicMemberRole;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipEngagement;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipStatus;
use App\ClinicAccess\Infrastructure\Persistence\Doctrine\Entity\ClinicMembershipEntity;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineMembershipAdminRepository implements MembershipAdminRepositoryInterface
{
    /**
     * Technical coupling: This repository performs cross-BC SQL joins for admin read models.
     * Table names are hardcoded to avoid direct infrastructure dependencies on other BCs.
     * This is an acceptable technical coupling at the infrastructure layer for read models.
     */
    private const string CLINIC_TABLE_NAME = 'clinic__clinics';
    private const string USER_TABLE_NAME   = 'identity_access__users';

    private string $membershipTableName;

    public function __construct(
        private Connection $connection,
        EntityManagerInterface $entityManager,
    ) {
        $this->membershipTableName = $entityManager->getClassMetadata(ClinicMembershipEntity::class)->getTableName();
    }

    public function listAll(
        ?string $clinicId = null,
        ?string $userId = null,
        ?ClinicMembershipStatus $status = null,
        ?ClinicMemberRole $role = null,
        ?ClinicMembershipEngagement $engagement = null,
    ): MembershipCollection {
        $where  = [];
        $params = [];

        if (null !== $clinicId) {
            $where[]            = 'm.clinic_id = :clinicId';
            $params['clinicId'] = Uuid::fromString($clinicId)->toBinary();
        }

        if (null !== $userId) {
            $where[]          = 'm.user_id = :userId';
            $params['userId'] = Uuid::fromString($userId)->toBinary();
        }

        if (null !== $status) {
            $where[]          = 'm.status = :status';
            $params['status'] = $status->value;
        }

        if (null !== $role) {
            $where[]        = 'm.role = :role';
            $params['role'] = $role->value;
        }

        if (null !== $engagement) {
            $where[]              = 'm.engagement = :engagement';
            $params['engagement'] = $engagement->value;
        }

        $whereClause = \count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = \sprintf(
            <<<'SQL'
            SELECT
                BIN_TO_UUID(m.id) AS membership_id,
                BIN_TO_UUID(m.clinic_id) AS clinic_id,
                c.name AS clinic_name,
                BIN_TO_UUID(m.user_id) AS user_id,
                u.email AS user_email,
                m.role,
                m.engagement,
                m.status,
                m.valid_from_utc,
                m.valid_until_utc,
                m.created_at_utc
            FROM %s m
            INNER JOIN %s c ON c.id = m.clinic_id
            INNER JOIN %s u ON u.id = m.user_id
            %s
            ORDER BY m.created_at_utc DESC
        SQL,
            $this->membershipTableName,
            self::CLINIC_TABLE_NAME,
            self::USER_TABLE_NAME,
            $whereClause
        );

        $results = $this->connection->fetchAllAssociative($sql, $params);

        $memberships = array_map(
            function (array $row): MembershipListItem {
                \assert(\is_string($row['membership_id']));
                \assert(\is_string($row['clinic_id']));
                \assert(\is_string($row['clinic_name']));
                \assert(\is_string($row['user_id']));
                \assert(\is_string($row['user_email']));
                \assert(\is_string($row['valid_from_utc']));
                \assert(\is_string($row['created_at_utc']));
                \assert(\is_string($row['role']) || \is_int($row['role']));
                \assert(\is_string($row['engagement']) || \is_int($row['engagement']));
                \assert(\is_string($row['status']) || \is_int($row['status']));

                return new MembershipListItem(
                    membershipId: $row['membership_id'],
                    clinicId: $row['clinic_id'],
                    clinicName: $row['clinic_name'],
                    userId: $row['user_id'],
                    userEmail: $row['user_email'],
                    role: ClinicMemberRole::from($row['role']),
                    engagement: ClinicMembershipEngagement::from($row['engagement']),
                    status: ClinicMembershipStatus::from($row['status']),
                    validFrom: new \DateTimeImmutable($row['valid_from_utc']),
                    validUntil: null !== $row['valid_until_utc']
                        ? (function ($val): \DateTimeImmutable {
                            \assert(\is_string($val));

                            return new \DateTimeImmutable($val);
                        })($row['valid_until_utc'])
                        : null,
                    createdAt: new \DateTimeImmutable($row['created_at_utc']),
                );
            },
            $results
        );

        return new MembershipCollection(
            memberships: $memberships,
            total: \count($memberships),
        );
    }
}
