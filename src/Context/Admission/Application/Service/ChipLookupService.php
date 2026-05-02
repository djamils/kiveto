<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Service;

use App\Context\Admission\Application\Port\AnimalInClinicWithActivePatient;
use App\Context\Admission\Application\Port\AnimalInClinicWithoutActivePatient;
use App\Context\Admission\Application\Port\AnimalNotInClinic;
use App\Context\Admission\Application\Port\AnimalReadRepositoryPort;
use App\Context\Admission\Application\Port\ChipLookupResult;
use App\Context\Admission\Application\Port\MicrochipRegistryLookupPort;
use App\Context\Admission\Application\Port\PatientReadRepositoryPort;

final class ChipLookupService
{
    public function __construct(
        private AnimalReadRepositoryPort $animalReadRepository,
        private MicrochipRegistryLookupPort $microchipRegistryLookupPort,
        private PatientReadRepositoryPort $patientReadRepository,
    ) {
    }

    public function lookup(string $chipNumber, string $clinicId): ChipLookupResult
    {
        $animalView = $this->animalReadRepository->findByMicrochip($chipNumber, $clinicId);

        if (null === $animalView) {
            // Trigger audit-trailed microchip registry lookup for unknown chips
            $this->microchipRegistryLookupPort->initiateChipLookup($chipNumber, $clinicId);

            return new AnimalNotInClinic();
        }

        $patientId = $this->patientReadRepository->getActivePatientIdForAnimal($clinicId, $animalView->animalId);

        if (null !== $patientId) {
            return new AnimalInClinicWithActivePatient(
                animalId: $animalView->animalId,
                patientId: $patientId,
                animalName: $animalView->animalName,
            );
        }

        return new AnimalInClinicWithoutActivePatient(
            animalId: $animalView->animalId,
            animalName: $animalView->animalName,
        );
    }
}
