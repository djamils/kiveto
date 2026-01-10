<?php

declare(strict_types=1);

namespace App\ClinicAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\ClinicAccess\Application\Port\MembershipAdminRepositoryInterface;
use App\ClinicAccess\Application\Query\ListAllMemberships\MembershipCollection;
use App\ClinicAccess\Application\Query\ListAllMemberships\MembershipListItem;
use App\ClinicAccess\Domain\ValueObject\ClinicMemberRole;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipEngagement;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipStatus;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineMembershipAdminRepository implements MembershipAdminRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {
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

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = <<<SQL
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
            FROM clinic_access__memberships m
            INNER JOIN clinic__clinics c ON c.id = m.clinic_id
            INNER JOIN identity_access__users u ON u.id = m.user_id
            {$whereClause}
            ORDER BY m.created_at_utc DESC
        SQL;

        $results = $this->connection->fetchAllAssociative($sql, $params);

        $memberships = array_map(
            fn (array $row) => new MembershipListItem(
                membershipId: $row['membership_id'],
                clinicId: $row['clinic_id'],
                clinicName: $row['clinic_name'],
                userId: $row['user_id'],
                userEmail: $row['user_email'],
                role: ClinicMemberRole::from($row['role']),
                engagement: ClinicMembershipEngagement::from($row['engagement']),
                status: ClinicMembershipStatus::from($row['status']),
                validFrom: new \DateTimeImmutable($row['valid_from_utc']),
                validUntil: null !== $row['valid_until_utc'] ? new \DateTimeImmutable($row['valid_until_utc']) : null,
                createdAt: new \DateTimeImmutable($row['created_at_utc']),
            ),
            $results
        );

        return new MembershipCollection(
            memberships: $memberships,
            total: count($memberships),
        );
    }
}
