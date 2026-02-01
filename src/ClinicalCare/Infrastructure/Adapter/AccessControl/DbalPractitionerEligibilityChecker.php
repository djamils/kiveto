<?php

declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Adapter\AccessControl;

use App\ClinicalCare\Application\Port\PractitionerEligibilityCheckerInterface;
use App\ClinicalCare\Domain\ValueObject\ClinicId;
use App\ClinicalCare\Domain\ValueObject\UserId;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalPractitionerEligibilityChecker implements PractitionerEligibilityCheckerInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function isEligibleForClinicAt(
        UserId $userId,
        ClinicId $clinicId,
        \DateTimeImmutable $at,
        array $allowedRoles,
    ): bool {
        $userBinary   = Uuid::fromString($userId->toString())->toBinary();
        $clinicBinary = Uuid::fromString($clinicId->toString())->toBinary();

        $sql = '
            SELECT COUNT(*) as cnt
            FROM access_control__clinic_memberships
            WHERE user_id = :userId
              AND clinic_id = :clinicId
              AND role IN (:roles)
              AND effective_from_date <= :atDate
              AND (effective_to_date IS NULL OR effective_to_date >= :atDate)
        ';

        $result = $this->connection->fetchAssociative($sql, [
            'userId'   => $userBinary,
            'clinicId' => $clinicBinary,
            'atDate'   => $at->format('Y-m-d'),
            'roles'    => $allowedRoles,
        ], [
            'roles' => ArrayParameterType::STRING,
        ]);

        return ($result['cnt'] ?? 0) > 0;
    }
}
