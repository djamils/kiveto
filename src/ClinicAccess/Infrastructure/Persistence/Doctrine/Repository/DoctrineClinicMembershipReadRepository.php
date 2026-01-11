<?php

declare(strict_types=1);

namespace App\ClinicAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\ClinicAccess\Application\Port\ClinicMembershipReadRepositoryInterface;
use App\ClinicAccess\Application\Query\ListClinicsForUser\AccessibleClinic;
use App\ClinicAccess\Domain\ValueObject\ClinicMemberRole;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipEngagement;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipStatus;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineClinicMembershipReadRepository implements ClinicMembershipReadRepositoryInterface
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    public function findAccessibleClinicsForUser(UserId $userId): array
    {
        $now = $this->clock->now();

        $sql = <<<'SQL'
            SELECT
                BIN_TO_UUID(m.clinic_id) AS clinic_id,
                c.name AS clinic_name,
                c.slug AS clinic_slug,
                c.status AS clinic_status,
                m.role,
                m.engagement,
                m.valid_from_utc,
                m.valid_until_utc
            FROM clinic_access__memberships m
            INNER JOIN clinic__clinics c ON c.id = m.clinic_id
            WHERE m.user_id = :userId
              AND m.status = :activeStatus
              AND m.valid_from_utc <= :now
              AND (m.valid_until_utc IS NULL OR m.valid_until_utc >= :now)
              AND c.status = 'active'
            ORDER BY c.name ASC
        SQL;

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
