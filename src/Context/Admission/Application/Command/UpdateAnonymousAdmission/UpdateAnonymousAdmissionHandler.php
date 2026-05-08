<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Command\UpdateAnonymousAdmission;

use App\Context\Admission\Domain\Repository\AdmissionRepositoryInterface;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateAnonymousAdmissionHandler
{
    public function __construct(
        private AdmissionRepositoryInterface $admissionRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateAnonymousAdmission $command): void
    {
        if (null === $command->physicalDescription && null === $command->triageNotes) {
            return;
        }

        $admission = $this->admissionRepository->get(
            ClinicId::fromString($command->clinicId),
            AdmissionId::fromString($command->admissionId),
        );

        $now = $this->clock->now();

        if (null !== $command->physicalDescription) {
            $admission->updatePhysicalDescription($command->physicalDescription, $now);
        }

        if (null !== $command->triageNotes) {
            $admission->updateTriageNotes($command->triageNotes, $now);
        }

        $this->admissionRepository->save($admission);
    }
}
