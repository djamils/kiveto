<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Service;

use App\Context\Patient\Application\Port\PatientReadRepositoryInterface;
use App\Context\Patient\Domain\Event\PatientLinkedToAnimalIntegrationEvent;
use App\Context\Patient\Domain\Event\PatientReconciledIntoIntegrationEvent;
use App\Context\Patient\Domain\Exception\PatientAlreadyExistsForAnimalException;
use App\Context\Patient\Domain\Repository\PatientRepositoryInterface;
use App\Context\Patient\Domain\ValueObject\AnimalLink;
use App\Context\Patient\Domain\ValueObject\ClinicId;
use App\Context\Patient\Domain\ValueObject\DisplayLabel;
use App\Context\Patient\Domain\ValueObject\PatientId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Shared\Domain\Time\ClockInterface;

final readonly class PatientIdentityResolutionService
{
    public function __construct(
        private PatientRepositoryInterface $patientRepository,
        private PatientReadRepositoryInterface $patientReadRepository,
        private DomainEventPublisher $domainEventPublisher,
        private IntegrationEventPublisher $integrationEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function linkToNewlyCreatedAnimal(
        PatientId $patientId,
        string $clinicId,
        string $animalId,
        string $animalName,
    ): void {
        $clinicIdVo = ClinicId::fromString($clinicId);
        $patient    = $this->patientRepository->get($clinicIdVo, $patientId);

        if ($this->patientReadRepository->existsActiveForAnimal($clinicId, $animalId)) {
            throw new PatientAlreadyExistsForAnimalException($animalId, $clinicId);
        }

        $now = $this->clock->now();

        $patient->linkToAnimal(AnimalLink::fromAnimalId($animalId), $now);
        $patient->updateDisplayLabel(DisplayLabel::fromAnimal($animalName), $now);

        $this->patientRepository->save($patient);
        $this->domainEventPublisher->publish($patient);

        $this->integrationEventPublisher->publish(new PatientLinkedToAnimalIntegrationEvent(
            patientId: $patientId->toString(),
            animalId: $animalId,
            clinicId: $clinicId,
        ));
    }

    public function linkToExistingAnimal(
        PatientId $patientId,
        string $clinicId,
        string $animalId,
        string $animalName,
    ): void {
        $clinicIdVo = ClinicId::fromString($clinicId);
        $patient    = $this->patientRepository->get($clinicIdVo, $patientId);

        if ($this->patientReadRepository->existsActiveForAnimal($clinicId, $animalId)) {
            throw new PatientAlreadyExistsForAnimalException($animalId, $clinicId);
        }

        $now = $this->clock->now();

        $patient->linkToAnimal(AnimalLink::fromAnimalId($animalId), $now);
        $patient->updateDisplayLabel(DisplayLabel::fromAnimal($animalName), $now);

        $this->patientRepository->save($patient);
        $this->domainEventPublisher->publish($patient);

        $this->integrationEventPublisher->publish(new PatientLinkedToAnimalIntegrationEvent(
            patientId: $patientId->toString(),
            animalId: $animalId,
            clinicId: $clinicId,
        ));
    }

    public function reconcileInto(
        PatientId $sourcePatientId,
        PatientId $targetPatientId,
        string $clinicId,
    ): void {
        $clinicIdVo    = ClinicId::fromString($clinicId);
        $sourcePatient = $this->patientRepository->get($clinicIdVo, $sourcePatientId);

        $now = $this->clock->now();

        $sourcePatient->reconcileInto($targetPatientId->toString(), $now);

        $this->patientRepository->save($sourcePatient);
        $this->domainEventPublisher->publish($sourcePatient);

        $this->integrationEventPublisher->publish(new PatientReconciledIntoIntegrationEvent(
            sourcePatientId: $sourcePatientId->toString(),
            targetPatientId: $targetPatientId->toString(),
            clinicId: $clinicId,
        ));
    }
}
