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
        // Convert string UUIDs to binary format for comparison
        $userBinary   = Uuid::fromString($userId->toString())->toBinary();
        $clinicBinary = Uuid::fromString($clinicId->toString())->toBinary();

        $sql = <<<'SQL'
            SELECT COUNT(*) as cnt
            FROM access_control__clinic_memberships
            WHERE user_id = :userId
              AND clinic_id = :clinicId
              AND status = 'ACTIVE'
              AND role IN (:roles)
              AND valid_from_utc <= :checkDate
              AND (valid_until_utc IS NULL OR valid_until_utc >= :checkDate)
        SQL;

        $result = $this->connection->fetchAssociative($sql, [
            'userId'    => $userBinary,
            'clinicId'  => $clinicBinary,
            'checkDate' => $at->format('Y-m-d H:i:s'),
            'roles'     => $allowedRoles,
        ], [
            'roles' => ArrayParameterType::STRING,
        ]);

        return ($result['cnt'] ?? 0) > 0;
    }
}
