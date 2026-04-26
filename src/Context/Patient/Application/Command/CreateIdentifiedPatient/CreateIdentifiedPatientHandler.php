<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Command\CreateIdentifiedPatient;

use App\Context\Patient\Application\Service\PatientCreationService;
use App\Context\Patient\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateIdentifiedPatientHandler
{
    public function __construct(
        private PatientCreationService $patientCreationService,
    ) {
    }

    public function __invoke(CreateIdentifiedPatient $command): string
    {
        $clinicId  = ClinicId::fromString($command->clinicId);
        $patientId = $this->patientCreationService->createFromAnimal(
            clinicId: $clinicId,
            animalId: $command->animalId,
            animalName: $command->animalName,
        );

        return $patientId->toString();
    }
}
