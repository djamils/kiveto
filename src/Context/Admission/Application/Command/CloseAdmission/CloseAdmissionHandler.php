<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Command\CloseAdmission;

use App\Context\Admission\Domain\Repository\AdmissionRepositoryInterface;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Domain\ValueObject\ClosureReason;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CloseAdmissionHandler
{
    public function __construct(
        private AdmissionRepositoryInterface $admissionRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CloseAdmission $command): void
    {
        $clinicId      = ClinicId::fromString($command->clinicId);
        $admissionId   = AdmissionId::fromString($command->admissionId);
        $closureReason = ClosureReason::from($command->closureReason);

        $admission = $this->admissionRepository->get($clinicId, $admissionId);

        $admission->close($closureReason, $this->clock->now());

        $this->admissionRepository->save($admission);
    }
}
