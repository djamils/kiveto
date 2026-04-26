<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Command\CreateUnidentifiedPatient;

use App\Context\Patient\Application\Service\PatientCreationService;
use App\Context\Patient\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateUnidentifiedPatientHandler
{
    public function __construct(
        private PatientCreationService $patientCreationService,
    ) {
    }

    public function __invoke(CreateUnidentifiedPatient $command): string
    {
        $clinicId  = ClinicId::fromString($command->clinicId);
        $patientId = $this->patientCreationService->createUnidentified(
            clinicId: $clinicId,
            provisionalLabel: $command->provisionalLabel,
            observedSpecies: $command->observedSpecies,
            observedColor: $command->observedColor,
        );

        return $patientId->toString();
    }
}
