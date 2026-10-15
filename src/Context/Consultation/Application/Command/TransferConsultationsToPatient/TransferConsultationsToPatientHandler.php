<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\TransferConsultationsToPatient;

use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TransferConsultationsToPatientHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
    ) {
    }

    public function __invoke(TransferConsultationsToPatient $command): void
    {
        $this->consultations->transferToPatient(
            ClinicId::fromString($command->clinicId),
            PatientId::fromString($command->sourcePatientId),
            PatientId::fromString($command->targetPatientId),
        );
    }
}
