<?php

declare(strict_types=1);

namespace App\AccessControl\Infrastructure\Persistence\Doctrine\Repository;

use App\AccessControl\Application\Port\ClinicMembershipReadRepositoryInterface;
use App\AccessControl\Application\Query\ListClinicsForUser\AccessibleClinic;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;
use App\AccessControl\Infrastructure\Persistence\Doctrine\Entity\ClinicMembershipEntity;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineClinicMembershipReadRepository implements ClinicMembershipReadRepositoryInterface
{
    /**
     * Technical coupling: This repository performs a cross-BC SQL join with the Clinic BC.
     * The table name is hardcoded to avoid direct infrastructure dependency on Clinic BC.
     * This is an acceptable technical coupling at the infrastructure layer for read models.
     */
    private const string CLINIC_TABLE_NAME = 'clinic__clinics';

    private string $membershipTableName;

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
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
                m.valid_until_utc
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
                );
            },
            $results
        );
    }
}
