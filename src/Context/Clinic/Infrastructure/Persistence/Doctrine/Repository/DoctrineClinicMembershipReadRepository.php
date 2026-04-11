<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Application\Port\ClinicMembershipReadRepositoryInterface;
use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\AccessibleClinic;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicMembershipEntity;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineClinicMembershipReadRepository implements ClinicMembershipReadRepositoryInterface
{
    /**
     * Local join: both membership and clinic tables belong to the Clinic BC.
     */
    private const string CLINIC_TABLE_NAME = 'clinic__clinics';

    private string $membershipTableName;

    public function __construct(
        private Connection $connection,
        private \App\Shared\Domain\Time\ClockInterface $clock,
        EntityManagerInterface $entityManager,
    ) {
        $this->membershipTableName = $entityManager->getClassMetadata(ClinicMembershipEntity::class)->getTableName();
    }

    public function findAccessibleClinicsForUser(UserId $userId): array
    {
        $now = $this->clock->now();

        $sql = \sprintf(
            <<<'SQL'
            SELECT
                BIN_TO_UUID(m.clinic_id) AS clinic_id,
                c.name AS clinic_name,
                c.slug AS clinic_slug,
                c.status AS clinic_status,
                m.role,
                m.engagement,
                m.valid_from_utc,
                m.valid_until_utc,
                m.is_default
            FROM %s m
            INNER JOIN %s c ON c.id = m.clinic_id
            WHERE m.user_id = :userId
              AND m.status = :activeStatus
              AND m.valid_from_utc <= :now
              AND (m.valid_until_utc IS NULL OR m.valid_until_utc >= :now)
              AND c.status = 'active'
            ORDER BY c.name ASC
        SQL,
            $this->membershipTableName,
            self::CLINIC_TABLE_NAME
        );

        $results = $this->connection->fetchAllAssociative($sql, [
            'userId'       => Uuid::fromString($userId->toString())->toBinary(),
            'activeStatus' => ClinicMembershipStatus::ACTIVE->value,
            'now'          => $now->format('Y-m-d H:i:s.u'),
        ]);

        return array_map(
            function (array $row): AccessibleClinic {
                \assert(\is_string($row['clinic_id']));
                \assert(\is_string($row['clinic_name']));
                \assert(\is_string($row['clinic_slug']));
                \assert(\is_string($row['clinic_status']));
                \assert(\is_string($row['valid_from_utc']));
                \assert(\is_string($row['role']) || \is_int($row['role']));
                \assert(\is_string($row['engagement']) || \is_int($row['engagement']));
                \assert(\is_int($row['is_default']) || \is_bool($row['is_default']));

                return new AccessibleClinic(
                    clinicId: $row['clinic_id'],
                    clinicName: $row['clinic_name'],
                    clinicSlug: $row['clinic_slug'],
                    clinicStatus: $row['clinic_status'],
                    memberRole: ClinicMemberRole::from($row['role']),
                    engagement: ClinicMembershipEngagement::from($row['engagement']),
                    validFrom: new \DateTimeImmutable($row['valid_from_utc']),
                    validUntil: null !== $row['valid_until_utc']
                        ? (function ($val): \DateTimeImmutable {
                            \assert(\is_string($val));

                            return new \DateTimeImmutable($val);
                        })($row['valid_until_utc'])
                        : null,
                    isDefault: (bool) $row['is_default'],
                );
            },
            $results
        );
    }

    public function findVeterinariansForClinic(ClinicId $clinicId): array
    {
        $sql = \sprintf(
            <<<'SQL'
            SELECT
                BIN_TO_UUID(m.user_id) AS user_id,
                m.role,
                m.engagement
            FROM %s m
            INNER JOIN %s c ON c.id = m.clinic_id
            WHERE m.clinic_id = :clinicId
              AND m.status = :activeStatus
              AND m.role = :veterinaryRole
              AND c.status = 'active'
            ORDER BY m.created_at_utc ASC, m.user_id ASC
        SQL,
            $this->membershipTableName,
            self::CLINIC_TABLE_NAME,
        );

        $results = $this->connection->fetchAllAssociative($sql, [
            'clinicId'       => Uuid::fromString($clinicId->toString())->toBinary(),
            'activeStatus'   => ClinicMembershipStatus::ACTIVE->value,
            'veterinaryRole' => ClinicMemberRole::VETERINARY->value,
        ]);

        return array_map(
            function (array $row): ClinicVeterinarianItem {
                \assert(\is_string($row['user_id']));
                \assert(\is_string($row['role']) || \is_int($row['role']));
                \assert(\is_string($row['engagement']) || \is_int($row['engagement']));

                return new ClinicVeterinarianItem(
                    userId: $row['user_id'],
                    role: (string) $row['role'],
                    engagement: (string) $row['engagement'],
                );
            },
            $results
        );
    }
}
