<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Patient;

use App\Context\Consultation\Application\Port\PatientSnapshotProviderInterface;
use App\Context\Consultation\Application\Port\ReportPatient;
use App\Context\Consultation\Application\Port\ReportPatientSnapshot;
use App\Context\Consultation\Infrastructure\Adapter\Animal\QueryBusAnimalReportReader;
use App\Context\Consultation\Infrastructure\Adapter\Client\QueryBusOwnerReportReader;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\GetPatientAnimalLink;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\PatientAnimalLinkDto;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Patient → animal (Patient context), then the animal and its primary owner
 * (Animal and Client contexts). A patient with no linked animal still prints:
 * its provisional label and observed species stand in for the animal record.
 */
final readonly class QueryBusPatientSnapshotProvider implements PatientSnapshotProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private QueryBusAnimalReportReader $animals,
        private QueryBusOwnerReportReader $owners,
    ) {
    }

    public function snapshot(string $clinicId, string $patientId): ReportPatientSnapshot
    {
        $link   = $this->queryBus->ask(new GetPatientAnimalLink($patientId, $clinicId));
        $link   = $link instanceof PatientAnimalLinkDto ? $link : null;
        $animal = null !== $link?->animalId ? $this->animals->read($clinicId, $link->animalId) : null;

        if (null === $animal) {
            return new ReportPatientSnapshot(
                new ReportPatient(
                    name: null !== $link ? $link->displayLabel : 'Patient non identifié',
                    speciesLabel: $link?->observedSpecies,
                ),
                null,
            );
        }

        return new ReportPatientSnapshot(
            $animal['patient'],
            null !== $animal['ownerClientId'] ? $this->owners->read($clinicId, $animal['ownerClientId']) : null,
        );
    }
}
