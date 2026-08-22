<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Command\CancelPrescription;

use App\Context\Prescription\Domain\Repository\PrescriptionRepositoryInterface;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CancelPrescriptionHandler
{
    public function __construct(
        private PrescriptionRepositoryInterface $prescriptions,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CancelPrescription $command): void
    {
        $prescription = $this->prescriptions->get(
            ClinicId::fromString($command->clinicId),
            PrescriptionId::fromString($command->prescriptionId),
        );

        $prescription->cancel($command->reason, $this->clock->now());

        $this->prescriptions->save($prescription);
    }
}
