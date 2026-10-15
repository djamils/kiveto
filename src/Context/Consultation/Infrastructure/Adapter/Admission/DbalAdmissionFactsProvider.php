<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Admission;

use App\Context\Consultation\Application\Port\AdmissionFactsProviderInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Reads the Admission context's visits for the register of consultations.
 *
 * A visit counts as an emergency when it was triaged as one (emergency or
 * vital emergency) or came in through one of the emergency intake channels.
 */
final readonly class DbalAdmissionFactsProvider implements AdmissionFactsProviderInterface
{
    private const string EMERGENCY = "(a.triage_level IN ('emergency', 'vital_emergency')
        OR a.intake_channel IN ('emergency', 'emergency_by_third_party', 'emergency_by_authority', 'emergency_by_association'))";

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function closedAmong(string $clinicId, array $admissionIds): array
    {
        return $this->among($clinicId, $admissionIds, "a.status = 'closed'");
    }

    public function emergenciesAmong(string $clinicId, array $admissionIds): array
    {
        return $this->among($clinicId, $admissionIds, self::EMERGENCY);
    }

    public function emergencyAdmissionIds(string $clinicId): array
    {
        return $this->ids(
            'SELECT BIN_TO_UUID(a.id) FROM admission__admissions a WHERE a.clinic_id = :clinicId AND ' . self::EMERGENCY,
            ['clinicId' => Uuid::fromString($clinicId)->toBinary()],
            [],
        );
    }

    /**
     * @param list<string> $admissionIds
     *
     * @return list<string>
     */
    private function among(string $clinicId, array $admissionIds, string $condition): array
    {
        if ([] === $admissionIds) {
            return [];
        }

        return $this->ids(
            'SELECT BIN_TO_UUID(a.id) FROM admission__admissions a
             WHERE a.clinic_id = :clinicId AND a.id IN (:ids) AND ' . $condition,
            [
                'clinicId' => Uuid::fromString($clinicId)->toBinary(),
                'ids'      => array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $admissionIds),
            ],
            ['ids' => ArrayParameterType::BINARY],
        );
    }

    /**
     * @param array<string, mixed>              $params
     * @param array<string, ArrayParameterType> $types
     *
     * @return list<string>
     */
    private function ids(string $sql, array $params, array $types): array
    {
        return array_values(array_filter($this->connection->fetchFirstColumn($sql, $params, $types), 'is_string'));
    }
}
