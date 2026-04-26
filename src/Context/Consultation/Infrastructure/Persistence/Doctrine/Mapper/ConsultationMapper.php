<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\ConsultationStatus;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Context\Consultation\Domain\ValueObject\Vitals;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ClinicalNoteEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ConsultationEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\PerformedActEntity;
use Symfony\Component\Uid\Uuid;

final readonly class ConsultationMapper
{
    public function __construct(
        private ClinicalNoteMapper $noteMapper,
        private PerformedActMapper $actMapper,
    ) {
    }

    public function toEntity(Consultation $consultation, ?ConsultationEntity $entity = null): ConsultationEntity
    {
        $entity ??= new ConsultationEntity();
        $entity->setId(Uuid::fromString($consultation->getId()->toString())->toBinary());
        $entity->setClinicId(Uuid::fromString($consultation->getClinicId()->toString())->toBinary());
        $entity->setAppointmentId($consultation->getAppointmentId()
            ? Uuid::fromString($consultation->getAppointmentId()->toString())->toBinary()
            : null);
        $entity->setAdmissionId(Uuid::fromString($consultation->getAdmissionId()->toString())->toBinary());
        $entity->setPatientId(Uuid::fromString($consultation->getPatientId()->toString())->toBinary());
        $entity->setPractitionerUserId(
            Uuid::fromString($consultation->getPractitionerUserId()->toString())->toBinary(),
        );
        $entity->setStatus($consultation->getStatus()->value);
        $entity->setChiefComplaint($consultation->getChiefComplaint());
        $entity->setSummary($consultation->getSummary());

        $vitals = $consultation->getVitals();
        $entity->setWeightKg(null !== $vitals?->getWeightKg() ? (string) $vitals->getWeightKg() : null);
        $entity->setTemperatureC(null !== $vitals?->getTemperatureC() ? (string) $vitals->getTemperatureC() : null);

        $entity->setStartedAtUtc($consultation->getStartedAtUtc());
        $entity->setClosedAtUtc($consultation->getClosedAtUtc());
        $entity->setCreatedAtUtc($consultation->getCreatedAtUtc());
        $entity->setUpdatedAtUtc($consultation->getUpdatedAtUtc());

        return $entity;
    }

    /**
     * @param array<ClinicalNoteEntity> $noteEntities
     * @param array<PerformedActEntity> $actEntities
     */
    public function toDomain(ConsultationEntity $entity, array $noteEntities, array $actEntities): Consultation
    {
        $notes = array_values(array_map($this->noteMapper->toDomain(...), $noteEntities));
        $acts  = array_values(array_map($this->actMapper->toDomain(...), $actEntities));

        $vitals = null;
        if (null !== $entity->getWeightKg() || null !== $entity->getTemperatureC()) {
            $vitals = Vitals::create(
                $entity->getWeightKg() ? (float) $entity->getWeightKg() : null,
                $entity->getTemperatureC() ? (float) $entity->getTemperatureC() : null,
            );
        }

        return Consultation::reconstitute(
            id: ConsultationId::fromString(Uuid::fromBinary($entity->getId())->toRfc4122()),
            clinicId: ClinicId::fromString(Uuid::fromBinary($entity->getClinicId())->toRfc4122()),
            appointmentId: $entity->getAppointmentId()
                ? AppointmentId::fromString(Uuid::fromBinary($entity->getAppointmentId())->toRfc4122())
                : null,
            admissionId: AdmissionId::fromString(Uuid::fromBinary($entity->getAdmissionId())->toRfc4122()),
            patientId: PatientId::fromString(Uuid::fromBinary($entity->getPatientId())->toRfc4122()),
            practitionerUserId: UserId::fromString(Uuid::fromBinary($entity->getPractitionerUserId())->toRfc4122()),
            status: ConsultationStatus::from($entity->getStatus()),
            chiefComplaint: $entity->getChiefComplaint(),
            vitals: $vitals,
            summary: $entity->getSummary(),
            startedAtUtc: $entity->getStartedAtUtc(),
            closedAtUtc: $entity->getClosedAtUtc(),
            createdAtUtc: $entity->getCreatedAtUtc(),
            updatedAtUtc: $entity->getUpdatedAtUtc(),
            notes: $notes,
            acts: $acts,
        );
    }
}
