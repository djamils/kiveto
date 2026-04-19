<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Application\Port\PlanningBlockOverlapCheckerInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalPlanningBlockOverlapChecker implements PlanningBlockOverlapCheckerInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function hasOverlap(
        ClinicId $clinicId,
        UserId $practitionerId,
        string $date,
        string $startTime,
        string $endTime,
        ?PlanningBlockId $excludeId = null,
    ): bool {
        $sql = <<<'SQL'
            SELECT COUNT(*) as cnt
            FROM scheduling__planning_blocks
            WHERE clinic_id = :clinicId
              AND practitioner_user_id = :practitionerUserId
              AND date = :date
              AND start_time < :endTime
              AND end_time > :startTime
        SQL;

        $params = [
            'clinicId'           => Uuid::fromString($clinicId->toString())->toBinary(),
            'practitionerUserId' => Uuid::fromString($practitionerId->toString())->toBinary(),
            'date'               => $date,
            'startTime'          => $startTime,
            'endTime'            => $endTime,
        ];

        if (null !== $excludeId) {
            $sql .= ' AND id != :excludeId';
            $params['excludeId'] = Uuid::fromString($excludeId->toString())->toBinary();
        }

        $result = $this->connection->fetchAssociative($sql, $params);

        return ($result['cnt'] ?? 0) > 0;
    }
}
