<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListConsultationsForPatient;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\PatientIdsProviderInterface;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListConsultationsForPatientHandler
{
    public function __construct(
        private ConsultationReadRepositoryInterface $consultations,
        private PatientIdsProviderInterface $patientIds,
    ) {
    }

    /**
     * @return list<ConsultationHistoryRow>
     */
    public function __invoke(ListConsultationsForPatient $query): array
    {
        $patientIds = [$query->patientId];

        if (null !== $query->animalId) {
            $linked = $this->patientIds->findPatientIdsForAnimal($query->animalId, $query->clinicId);

            if ([] !== $linked) {
                $patientIds = array_values(array_unique(array_merge($patientIds, $linked)));
            }
        }

        $rows = $this->consultations->listForPatients(
            $patientIds,
            ClinicId::fromString($query->clinicId),
            null !== $query->excludeConsultationId
                ? ConsultationId::fromString($query->excludeConsultationId)
                : null,
        );

        return array_map(
            static fn (array $row): ConsultationHistoryRow => new ConsultationHistoryRow(
                consultationId: $row['consultationId'],
                startedAtUtc: $row['startedAtUtc'],
                closedAtUtc: $row['closedAtUtc'],
                status: $row['status'],
                chiefComplaint: $row['chiefComplaint'],
                summary: $row['summary'],
                weightKg: $row['weightKg'],
            ),
            $rows,
        );
    }
}
