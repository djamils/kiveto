<?php

declare(strict_types=1);

namespace App\Context\Admission\Infrastructure\Adapter\Consultation;

use App\Context\Admission\Application\Port\ConsultationPatientTransferPort;
use App\Context\Consultation\Application\Command\TransferConsultationsToPatient\TransferConsultationsToPatient;
use App\Shared\Application\Bus\CommandBusInterface;

final readonly class CommandBusConsultationPatientTransferAdapter implements ConsultationPatientTransferPort
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function transferConsultations(string $clinicId, string $sourcePatientId, string $targetPatientId): void
    {
        $this->commandBus->dispatch(new TransferConsultationsToPatient(
            clinicId: $clinicId,
            sourcePatientId: $sourcePatientId,
            targetPatientId: $targetPatientId,
        ));
    }
}
