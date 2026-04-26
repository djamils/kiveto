<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Command\OpenEmergencyAdmission;

use App\Context\Admission\Application\Service\EmergencyAdmissionService;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Domain\ValueObject\IntakeChannel;
use App\Context\Admission\Domain\ValueObject\Presenter;
use App\Context\Admission\Domain\ValueObject\PresenterRole;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class OpenEmergencyAdmissionHandler
{
    public function __construct(
        private EmergencyAdmissionService $emergencyAdmissionService,
    ) {
    }

    public function __invoke(OpenEmergencyAdmission $command): string
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $channel  = IntakeChannel::from($command->intakeChannel);
        $triage   = TriageLevel::from($command->triageLevel);

        $presenter = null;
        if (null !== $command->presenterName && '' !== $command->presenterName) {
            $phone = null;
            if (null !== $command->presenterPhone && '' !== $command->presenterPhone) {
                $phone = PhoneNumber::fromString($command->presenterPhone);
            }

            $role = null !== $command->presenterRole && '' !== $command->presenterRole
                ? PresenterRole::from($command->presenterRole)
                : PresenterRole::Unknown;

            $presenter = Presenter::create($command->presenterName, $phone, $role);
        }

        $admissionId = $this->emergencyAdmissionService->openEmergencyAdmission(
            clinicId: $clinicId,
            channel: $channel,
            triage: $triage,
            presenter: $presenter,
            knownAnimalId: $command->knownAnimalId,
            animalName: $command->animalName,
            provisionalLabel: $command->provisionalLabel,
            observedSpecies: $command->observedSpecies,
            observedColor: $command->observedColor,
            physicalDescription: $command->physicalDescription,
            triageNotes: $command->triageNotes,
        );

        return $admissionId->value();
    }
}
