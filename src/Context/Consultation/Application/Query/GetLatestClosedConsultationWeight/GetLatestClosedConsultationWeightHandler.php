<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetLatestClosedConsultationWeight;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\PatientIdsProviderInterface;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetLatestClosedConsultationWeightHandler
{
    public function __construct(
        private ConsultationReadRepositoryInterface $consultations,
        private PatientIdsProviderInterface $patientIds,
    ) {
    }

    public function __invoke(GetLatestClosedConsultationWeight $query): ?ConsultationWeight
    {
        $patientIds = $this->patientIds->findPatientIdsForAnimal($query->animalId, $query->clinicId);

        if ([] === $patientIds) {
            return null;
        }

        $latest = $this->consultations->latestClosedWeight(ClinicId::fromString($query->clinicId), $patientIds);

        if (null === $latest) {
            return null;
        }

        return new ConsultationWeight(
            weightGrams: (int) round((float) $latest['weightKg'] * 1000),
            measuredAtUtc: new \DateTimeImmutable($latest['startedAtUtc'], new \DateTimeZone('UTC')),
        );
    }
}
