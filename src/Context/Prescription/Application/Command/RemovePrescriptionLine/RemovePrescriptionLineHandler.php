<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Command\RemovePrescriptionLine;

use App\Context\Prescription\Domain\Repository\PrescriptionRepositoryInterface;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionLineId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RemovePrescriptionLineHandler
{
    public function __construct(
        private PrescriptionRepositoryInterface $prescriptions,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RemovePrescriptionLine $command): void
    {
        $prescription = $this->prescriptions->get(
            ClinicId::fromString($command->clinicId),
            PrescriptionId::fromString($command->prescriptionId),
        );

        $prescription->removeLine(PrescriptionLineId::fromString($command->lineId), $this->clock->now());

        $this->prescriptions->save($prescription);
    }
}
