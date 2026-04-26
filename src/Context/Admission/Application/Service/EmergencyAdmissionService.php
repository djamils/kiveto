<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Service;

use App\Context\Admission\Application\Port\PatientCreationPort;
use App\Context\Admission\Domain\Admission;
use App\Context\Admission\Domain\Event\AdmissionOpenedWithUnidentifiedPatient;
use App\Context\Admission\Domain\Repository\AdmissionRepositoryInterface;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Domain\ValueObject\IntakeChannel;
use App\Context\Admission\Domain\ValueObject\Presenter;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;

final class EmergencyAdmissionService
{
    public function __construct(
        private AdmissionRepositoryInterface $admissionRepository,
        private PatientCreationPort $patientCreationPort,
        private UuidGeneratorInterface $uuidGenerator,
        private DomainEventPublisher $domainEventPublisher,
        private IntegrationEventPublisher $integrationEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function openEmergencyAdmission(
        ClinicId $clinicId,
        IntakeChannel $channel,
        TriageLevel $triage,
        ?Presenter $presenter,
        ?string $knownAnimalId,
        ?string $animalName,
        ?string $provisionalLabel,
        ?string $observedSpecies,
        ?string $observedColor,
        ?string $physicalDescription = null,
        ?string $triageNotes = null,
    ): AdmissionId {
        $now          = $this->clock->now();
        $isIdentified = null !== $knownAnimalId;

        if (null !== $knownAnimalId) {
            \assert(null !== $animalName);
            $patientId = $this->patientCreationPort->createIdentifiedPatient(
                $clinicId->toString(),
                $knownAnimalId,
                $animalName,
            );
        } else {
            // Generate a provisional label from available data if not provided
            $label = $provisionalLabel
                ?? \sprintf(
                    '%s · %s',
                    $observedSpecies ?? 'Animal',
                    $now->format('d/m H\\hi'),
                );
            $patientId = $this->patientCreationPort->createUnidentifiedPatient(
                $clinicId->toString(),
                $label,
                $observedSpecies,
                $observedColor,
            );
        }

        $admissionId = AdmissionId::fromString($this->uuidGenerator->generate());

        $admission = Admission::open(
            id: $admissionId,
            clinicId: $clinicId,
            patientId: $patientId,
            isPatientIdentified: $isIdentified,
            channel: $channel,
            triage: $triage,
            presenter: $presenter,
            now: $now,
            appointmentId: null,
            physicalDescription: $physicalDescription,
            triageNotes: $triageNotes,
        );

        $this->admissionRepository->save($admission);
        $this->domainEventPublisher->publish($admission);

        if (!$isIdentified) {
            $this->integrationEventPublisher->publish(new AdmissionOpenedWithUnidentifiedPatient(
                admissionId: $admissionId->value(),
                patientId: $patientId,
                clinicId: $clinicId->toString(),
                openedAt: $now->format(\DateTimeInterface::ATOM),
            ));
        }

        return $admissionId;
    }
}
