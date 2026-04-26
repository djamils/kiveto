<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Infrastructure\Persistence\Doctrine;

use App\Context\Regulatory\Application\Port\RegulatoryTasksReadRepositoryInterface;
use App\Context\Regulatory\Domain\ValueObject\MairieNotificationStatus;
use App\Context\Regulatory\Domain\ValueObject\StrayCustodyStatus;
use Doctrine\DBAL\Connection;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineRegulatoryTasksReadRepository implements RegulatoryTasksReadRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findPendingMairieNotifications(string $clinicId): array
    {
        $clinicUuid = Uuid::fromString($clinicId)->toBinary();

        return $this->connection->fetchAllAssociative(
            'SELECT id, admission_id, patient_id, deadline, created_at
             FROM regulatory__mairie_notification_entity
             WHERE clinic_id = :clinicId
               AND status = :status
             ORDER BY deadline ASC',
            [
                'clinicId' => $clinicUuid,
                'status'   => MairieNotificationStatus::Pending->value,
            ],
            ['clinicId' => UuidType::NAME],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findActiveStrayCustodies(string $clinicId): array
    {
        $clinicUuid = Uuid::fromString($clinicId)->toBinary();

        return $this->connection->fetchAllAssociative(
            'SELECT id, admission_id, patient_id, deadline, created_at
             FROM regulatory__stray_custody_entity
             WHERE clinic_id = :clinicId
               AND status = :status
             ORDER BY deadline ASC',
            [
                'clinicId' => $clinicUuid,
                'status'   => StrayCustodyStatus::Active->value,
            ],
            ['clinicId' => UuidType::NAME],
        );
    }
}
