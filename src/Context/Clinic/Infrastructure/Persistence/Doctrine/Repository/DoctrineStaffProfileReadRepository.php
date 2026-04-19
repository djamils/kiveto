<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Application\Port\StaffProfileReadItem;
use App\Context\Clinic\Application\Port\StaffProfileReadRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\StaffProfileEntity;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineStaffProfileReadRepository implements StaffProfileReadRepositoryInterface
{
    private string $profileTableName;

    public function __construct(
        private Connection $connection,
        EntityManagerInterface $entityManager,
    ) {
        $this->profileTableName = $entityManager->getClassMetadata(StaffProfileEntity::class)->getTableName();
    }

    public function findByMembershipIds(array $membershipIds): array
    {
        if ([] === $membershipIds) {
            return [];
        }

        $sql = \sprintf(
            <<<'SQL'
            SELECT
                BIN_TO_UUID(p.id) AS profile_id,
                BIN_TO_UUID(p.membership_id) AS membership_id,
                p.display_name,
                p.professional_title,
                p.agenda_color,
                p.sort_order,
                p.is_visible_in_agenda,
                p.registration_number
            FROM %s p
            WHERE BIN_TO_UUID(p.membership_id) IN (:membershipIds)
        SQL,
            $this->profileTableName,
        );

        $results = $this->connection->fetchAllAssociative(
            $sql,
            ['membershipIds' => $membershipIds],
            ['membershipIds' => ArrayParameterType::STRING],
        );

        $map = [];

        foreach ($results as $row) {
            \assert(\is_string($row['profile_id']));
            \assert(\is_string($row['membership_id']));
            \assert(\is_string($row['display_name']));
            \assert(\is_string($row['agenda_color']));

            $sortOrder         = $row['sort_order'];
            $isVisibleInAgenda = $row['is_visible_in_agenda'];

            \assert(\is_int($sortOrder) || \is_string($sortOrder));
            \assert(\is_int($isVisibleInAgenda) || \is_bool($isVisibleInAgenda));

            $map[$row['membership_id']] = new StaffProfileReadItem(
                profileId: $row['profile_id'],
                membershipId: $row['membership_id'],
                userId: '',
                displayName: $row['display_name'],
                professionalTitle: isset($row['professional_title']) && \is_string($row['professional_title'])
                    ? $row['professional_title']
                    : null,
                agendaColor: $row['agenda_color'],
                sortOrder: (int) $sortOrder,
                isVisibleInAgenda: (bool) $isVisibleInAgenda,
            );
        }

        return $map;
    }

    public function findByMembershipId(string $membershipId): ?StaffProfileReadItem
    {
        $map = $this->findByMembershipIds([$membershipId]);

        return $map[$membershipId] ?? null;
    }

    public function hasVeterinaryCredentialsFor(ClinicMembershipId $membershipId): bool
    {
        $sql = \sprintf(
            'SELECT COUNT(*) FROM %s WHERE membership_id = :membershipId AND registration_number IS NOT NULL',
            $this->profileTableName,
        );

        $count = $this->connection->fetchOne($sql, [
            'membershipId' => Uuid::fromString($membershipId->toString())->toBinary(),
        ]);

        \assert(\is_int($count) || \is_string($count) || false === $count);

        return false !== $count && (int) $count > 0;
    }
}
