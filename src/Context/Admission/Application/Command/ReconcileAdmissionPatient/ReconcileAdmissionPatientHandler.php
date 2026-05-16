<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Command\ReconcileAdmissionPatient;

use App\Context\Admission\Application\Port\PatientCreationPort;
use App\Context\Admission\Application\Port\PatientReadRepositoryPort;
use App\Context\Admission\Domain\Repository\AdmissionRepositoryInterface;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class ReconcileAdmissionPatientHandler
{
    public function __construct(
        private AdmissionRepositoryInterface $admissionRepository,
        private PatientCreationPort $patientCreationPort,
        private PatientReadRepositoryPort $patientReadRepository,
        private Connection $connection,
    ) {
    }

    public function __invoke(ReconcileAdmissionPatient $command): void
    {
        $admission = $this->admissionRepository->get(
            ClinicId::fromString($command->clinicId),
            AdmissionId::fromString($command->admissionId),
        );
        $sourcePatientId = $admission->patientId();

        $this->patientCreationPort->reconcilePatientToAnimal(
            sourcePatientId: $sourcePatientId,
            animalId: $command->animalId,
            animalName: $command->animalName,
            clinicId: $command->clinicId,
        );

        // If the reconcile path was taken (existing patient for the animal), the source patient
        // is now archived and admission.patient_id must point to the target patient.
        // PatientReconciledIntoIntegrationEvent handles this asynchronously via the worker,
        // but we apply it synchronously here so the UI reflects the change immediately.
        $activePatientId = $this->patientReadRepository->getActivePatientIdForAnimal(
            $command->clinicId,
            $command->animalId,
        );

        if (null !== $activePatientId && $activePatientId !== $sourcePatientId) {
            $this->connection->executeStatement(
                'UPDATE admission__admissions SET patient_id = :targetId'
                    . ' WHERE patient_id = :sourceId AND clinic_id = :clinicId',
                [
                    'targetId' => Uuid::fromString($activePatientId)->toBinary(),
                    'sourceId' => Uuid::fromString($sourcePatientId)->toBinary(),
                    'clinicId' => Uuid::fromString($command->clinicId)->toBinary(),
                ],
            );
        }
    }
}
