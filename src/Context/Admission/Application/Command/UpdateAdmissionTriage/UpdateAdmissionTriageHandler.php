<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Command\UpdateAdmissionTriage;

use App\Context\Admission\Domain\Repository\AdmissionRepositoryInterface;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateAdmissionTriageHandler
{
    public function __construct(
        private AdmissionRepositoryInterface $admissionRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateAdmissionTriage $command): void
    {
        $clinicId    = ClinicId::fromString($command->clinicId);
        $admissionId = AdmissionId::fromString($command->admissionId);
        $newLevel    = TriageLevel::from($command->newTriageLevel);

        $admission = $this->admissionRepository->get($clinicId, $admissionId);
        $now       = $this->clock->now();

        if ($newLevel->ordinal() > $admission->triageLevel()->ordinal()) {
            $admission->escalateTriage($newLevel, $now);
        } else {
            $admission->deescalateTriage($newLevel, $now);
        }

        $this->admissionRepository->save($admission);
    }
}
