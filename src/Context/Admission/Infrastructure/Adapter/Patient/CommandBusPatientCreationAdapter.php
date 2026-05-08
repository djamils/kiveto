<?php

declare(strict_types=1);

namespace App\Context\Admission\Infrastructure\Adapter\Patient;

use App\Context\Admission\Application\Port\PatientCreationPort;
use App\Context\Patient\Application\Command\CreateIdentifiedPatient\CreateIdentifiedPatient;
use App\Context\Patient\Application\Command\CreateUnidentifiedPatient\CreateUnidentifiedPatient;
use App\Context\Patient\Application\Command\ReconcilePatientToAnimal\ReconcilePatientToAnimal;
use App\Shared\Application\Bus\CommandBusInterface;

final readonly class CommandBusPatientCreationAdapter implements PatientCreationPort
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function createUnidentifiedPatient(
        string $clinicId,
        string $provisionalLabel,
        ?string $observedSpecies,
        ?string $observedColor,
    ): string {
        $result = $this->commandBus->dispatch(new CreateUnidentifiedPatient(
            clinicId: $clinicId,
            provisionalLabel: $provisionalLabel,
            observedSpecies: $observedSpecies,
            observedColor: $observedColor,
        ));

        \assert(\is_string($result));

        return $result;
    }

    public function createIdentifiedPatient(
        string $clinicId,
        string $animalId,
        string $animalName,
    ): string {
        $result = $this->commandBus->dispatch(new CreateIdentifiedPatient(
            clinicId: $clinicId,
            animalId: $animalId,
            animalName: $animalName,
        ));

        \assert(\is_string($result));

        return $result;
    }

    public function reconcilePatientToAnimal(
        string $sourcePatientId,
        string $animalId,
        string $animalName,
        string $clinicId,
    ): void {
        $this->commandBus->dispatch(new ReconcilePatientToAnimal(
            sourcePatientId: $sourcePatientId,
            animalId: $animalId,
            animalName: $animalName,
            clinicId: $clinicId,
        ));
    }
}
