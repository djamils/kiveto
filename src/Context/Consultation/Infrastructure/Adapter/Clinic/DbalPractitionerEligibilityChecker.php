<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Clinic;

use App\Context\Consultation\Application\Port\PractitionerEligibilityCheckerInterface;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\UserId;
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

        $sql = <<<'SQL'
            SELECT COUNT(*) as cnt
            FROM clinic__clinic_memberships
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
