<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Command\RecordArrivalCheckIn;

use App\Context\Admission\Domain\Repository\AdmissionRepositoryInterface;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\ArrivalCheckIn;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordArrivalCheckInHandler
{
    public function __construct(
        private AdmissionRepositoryInterface $admissionRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordArrivalCheckIn $command): void
    {
        $admission = $this->admissionRepository->get(
            ClinicId::fromString($command->clinicId),
            AdmissionId::fromString($command->admissionId),
        );

        $admission->recordArrivalCheckIn(
            ArrivalCheckIn::record(
                fasting: $command->fasting,
                newSymptom: $command->newSymptom,
                documentsBrought: $command->documentsBrought,
                ongoingTreatment: $command->ongoingTreatment,
                weightGrams: $command->weightGrams,
                note: $command->note,
            ),
            $this->clock->now(),
        );

        $this->admissionRepository->save($admission);
    }
}
