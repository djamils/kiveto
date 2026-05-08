<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Command\UpdateAdmissionLocationStatus;

use App\Context\Admission\Domain\Repository\AdmissionRepositoryInterface;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Domain\ValueObject\LocationStatus;
use App\Context\Admission\Domain\ValueObject\LocationStatusValue;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateAdmissionLocationStatusHandler
{
    public function __construct(
        private AdmissionRepositoryInterface $admissionRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateAdmissionLocationStatus $command): void
    {
        $clinicId    = ClinicId::fromString($command->clinicId);
        $admissionId = AdmissionId::fromString($command->admissionId);

        $admission = $this->admissionRepository->get($clinicId, $admissionId);
        $now       = $this->clock->now();

        $admission->updateLocationStatus(
            new LocationStatus(LocationStatusValue::from($command->newLocationStatus), $now),
            $now,
        );

        $this->admissionRepository->save($admission);
    }
}
