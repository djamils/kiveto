<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Adapter\AccessControl;

use App\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;

final readonly class DbalMembershipEligibilityChecker implements MembershipEligibilityCheckerInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function isUserEligibleForClinicAt(
        UserId $userId,
        ClinicId $clinicId,
        \DateTimeImmutable $at,
        array $allowedRoles,
    ): bool {
        $sql = <<<'SQL'
            SELECT COUNT(*) as cnt
            FROM access_control__clinic_memberships
            WHERE user_id = :userId
              AND clinic_id = :clinicId
              AND status = 'ACTIVE'
              AND role IN (:allowedRoles)
              AND valid_from_utc <= :checkDate
              AND (valid_until_utc IS NULL OR valid_until_utc >= :checkDate)
        SQL;

        $result = $this->connection->fetchAssociative($sql, [
            'userId'       => $userId->toString(),
            'clinicId'     => $clinicId->toString(),
            'checkDate'    => $at->format('Y-m-d H:i:s'),
            'allowedRoles' => $allowedRoles,
        ], [
            'allowedRoles' => Connection::PARAM_STR_ARRAY,
        ]);

        return ($result['cnt'] ?? 0) > 0;
    }

    public function listEligiblePractitionerUsersForClinic(
        ClinicId $clinicId,
        \DateTimeImmutable $at,
        array $allowedRoles,
    ): array {
        $sql = <<<'SQL'
            SELECT DISTINCT user_id
            FROM access_control__clinic_memberships
            WHERE clinic_id = :clinicId
              AND status = 'ACTIVE'
              AND role IN (:allowedRoles)
              AND valid_from_utc <= :checkDate
              AND (valid_until_utc IS NULL OR valid_until_utc >= :checkDate)
        SQL;

        $results = $this->connection->fetchAllAssociative($sql, [
            'clinicId'     => $clinicId->toString(),
            'checkDate'    => $at->format('Y-m-d H:i:s'),
            'allowedRoles' => $allowedRoles,
        ], [
            'allowedRoles' => Connection::PARAM_STR_ARRAY,
        ]);

        $practitioners = [];
        foreach ($results as $row) {
            $practitioners[] = [
                'userId'      => $row['user_id'],
                'displayName' => null, // Could be enriched from IdentityAccess BC if needed
            ];
        }

        return $practitioners;
    }
}
