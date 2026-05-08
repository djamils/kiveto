<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Command\ReconcileAdmissionPatient;

use App\Context\Admission\Application\Port\PatientCreationPort;
use App\Context\Admission\Domain\Repository\AdmissionRepositoryInterface;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReconcileAdmissionPatientHandler
{
    public function __construct(
        private AdmissionRepositoryInterface $admissionRepository,
        private PatientCreationPort $patientCreationPort,
    ) {
    }

    public function __invoke(ReconcileAdmissionPatient $command): void
    {
        $admission = $this->admissionRepository->get(
            ClinicId::fromString($command->clinicId),
            AdmissionId::fromString($command->admissionId),
        );

        $this->patientCreationPort->reconcilePatientToAnimal(
            sourcePatientId: $admission->patientId(),
            animalId: $command->animalId,
            animalName: $command->animalName,
            clinicId: $command->clinicId,
        );
    }
}
