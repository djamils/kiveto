<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Command\SetPrescriptionNotes;

use App\Context\Prescription\Domain\Repository\PrescriptionRepositoryInterface;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SetPrescriptionNotesHandler
{
    public function __construct(
        private PrescriptionRepositoryInterface $prescriptions,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SetPrescriptionNotes $command): void
    {
        $prescription = $this->prescriptions->get(
            ClinicId::fromString($command->clinicId),
            PrescriptionId::fromString($command->prescriptionId),
        );

        $prescription->setNotes($command->notes, $this->clock->now());

        $this->prescriptions->save($prescription);
    }
}
