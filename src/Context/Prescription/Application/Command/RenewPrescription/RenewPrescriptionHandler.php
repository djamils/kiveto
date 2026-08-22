<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Command\RenewPrescription;

use App\Context\Prescription\Domain\Repository\PrescriptionRepositoryInterface;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;
use App\Context\Prescription\Domain\ValueObject\RenewalId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RenewPrescriptionHandler
{
    public function __construct(
        private PrescriptionRepositoryInterface $prescriptions,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RenewPrescription $command): void
    {
        $prescription = $this->prescriptions->get(
            ClinicId::fromString($command->clinicId),
            PrescriptionId::fromString($command->prescriptionId),
        );

        $prescription->renew(
            RenewalId::fromString($this->uuidGenerator->generate()),
            $command->renewedByUserId,
            $this->clock->now(),
        );

        $this->prescriptions->save($prescription);
    }
}
