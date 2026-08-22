<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Command\IssuePrescription;

use App\Context\Prescription\Domain\Repository\PrescriptionRepositoryInterface;
use App\Context\Prescription\Domain\Service\PrescriptionNumberGeneratorInterface;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IssuePrescriptionHandler
{
    public function __construct(
        private PrescriptionRepositoryInterface $prescriptions,
        private PrescriptionNumberGeneratorInterface $numbers,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(IssuePrescription $command): void
    {
        $clinicId     = ClinicId::fromString($command->clinicId);
        $prescription = $this->prescriptions->get($clinicId, PrescriptionId::fromString($command->prescriptionId));

        $now = $this->clock->now();

        $prescription->issue($this->numbers->next($clinicId, (int) $now->format('Y')), $now);

        $this->prescriptions->save($prescription);
    }
}
