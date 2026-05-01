<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Command\OpenAdmissionForWalkIn;

use App\Context\Admission\Application\Service\EmergencyAdmissionService;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Domain\ValueObject\IntakeChannel;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class OpenAdmissionForWalkInHandler
{
    public function __construct(
        private EmergencyAdmissionService $emergencyAdmissionService,
    ) {
    }

    public function __invoke(OpenAdmissionForWalkIn $command): string
    {
        $admissionId = $this->emergencyAdmissionService->openEmergencyAdmission(
            clinicId: ClinicId::fromString($command->clinicId),
            channel: IntakeChannel::WalkIn,
            triage: TriageLevel::from($command->triageLevel),
            presenter: null,
            knownAnimalId: $command->knownAnimalId,
            animalName: $command->animalName,
            provisionalLabel: $command->provisionalLabel,
            observedSpecies: null,
            observedColor: null,
            physicalDescription: null,
            triageNotes: $command->triageNotes,
        );

        return $admissionId->value();
    }
}
