<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListLastClosedConsultations;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\PatientIdsProviderInterface;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * An animal never seen, unknown or of another clinic, is left out. An animal
 * behind several patient records (reconciliation) gets the latest visit of
 * them all.
 */
#[AsMessageHandler]
final readonly class ListLastClosedConsultationsHandler
{
    public function __construct(
        private PatientIdsProviderInterface $patientIds,
        private ConsultationReadRepositoryInterface $consultations,
    ) {
    }

    /**
     * @return array<string, LastClosedConsultation> by animal id
     */
    public function __invoke(ListLastClosedConsultations $query): array
    {
        $animalIds = array_values(array_unique($query->animalIds));

        if ([] === $animalIds) {
            return [];
        }

        $animalByPatient = $this->patientIds->findAnimalIdsByPatient($animalIds, $query->clinicId);

        if ([] === $animalByPatient) {
            return [];
        }

        $last = [];

        foreach ($this->consultations->latestClosedForPatients(ClinicId::fromString($query->clinicId), array_keys($animalByPatient)) as $row) {
            $animalId = $animalByPatient[$row->patientId];
            $current  = $last[$animalId] ?? null;

            if (null === $current || [$row->startedAtUtc, $row->consultationId] > [$current->startedAtUtc, $current->consultationId]) {
                $last[$animalId] = new LastClosedConsultation(
                    consultationId: $row->consultationId,
                    animalId: $animalId,
                    practitionerUserId: $row->practitionerUserId,
                    startedAtUtc: $row->startedAtUtc,
                    chiefComplaint: $row->chiefComplaint,
                    motifs: $row->motifs,
                );
            }
        }

        return $last;
    }
}
