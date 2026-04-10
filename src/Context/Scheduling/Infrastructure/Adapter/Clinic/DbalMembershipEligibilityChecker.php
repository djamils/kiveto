<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter\Clinic;

use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

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
            FROM clinic__clinic_memberships
            WHERE user_id = :userId
              AND clinic_id = :clinicId
              AND status = 'ACTIVE'
              AND role IN (:allowedRoles)
              AND valid_from_utc <= :checkDate
              AND (valid_until_utc IS NULL OR valid_until_utc >= :checkDate)
        SQL;

        $result = $this->connection->fetchAssociative($sql, [
            'userId'       => Uuid::fromString($userId->toString())->toBinary(),
            'clinicId'     => Uuid::fromString($clinicId->toString())->toBinary(),
            'checkDate'    => $at->format('Y-m-d H:i:s'),
            'allowedRoles' => $allowedRoles,
        ], [
            'allowedRoles' => ArrayParameterType::STRING,
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
            FROM clinic__clinic_memberships
            WHERE clinic_id = :clinicId
              AND status = 'ACTIVE'
              AND role IN (:allowedRoles)
              AND valid_from_utc <= :checkDate
              AND (valid_until_utc IS NULL OR valid_until_utc >= :checkDate)
        SQL;

        $results = $this->connection->fetchAllAssociative($sql, [
            'clinicId'     => Uuid::fromString($clinicId->toString())->toBinary(),
            'checkDate'    => $at->format('Y-m-d H:i:s'),
            'allowedRoles' => $allowedRoles,
        ], [
            'allowedRoles' => ArrayParameterType::STRING,
        ]);

        $practitioners = [];
        foreach ($results as $row) {
            $practitioners[] = [
                'userId'      => RowAccessor::uuid($row, 'user_id'),
                'displayName' => null,
            ];
        }

        return $practitioners;
    }
}
