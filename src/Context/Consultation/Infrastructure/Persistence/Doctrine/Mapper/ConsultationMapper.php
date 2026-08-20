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
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\BillingLineEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ClinicalNoteEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ConsultationEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\DiagnosisEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ExamSystemEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\MotifEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\PerformedActEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\PlanActionEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\PrescriptionLineEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\TypedVitalEntity;
use Symfony\Component\Uid\Uuid;

final readonly class ConsultationMapper
{
    public function __construct(
        private ClinicalNoteMapper $noteMapper,
        private PerformedActMapper $actMapper,
        private MotifMapper $motifMapper,
        private TypedVitalMapper $typedVitalMapper,
        private ExamSystemMapper $examSystemMapper,
        private DiagnosisMapper $diagnosisMapper,
        private PlanActionMapper $planActionMapper,
        private PrescriptionLineMapper $prescriptionLineMapper,
        private BillingLineMapper $billingLineMapper,
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
        $entity->setSubjectiveText($consultation->getSubjectiveText());
        $entity->setObjectiveObservations($consultation->getObjectiveObservations());
        $entity->setTeamMemo($consultation->getTeamMemo());

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
     * @param array<ClinicalNoteEntity>     $noteEntities
     * @param array<PerformedActEntity>     $actEntities
     * @param array<MotifEntity>            $motifEntities
     * @param array<TypedVitalEntity>       $typedVitalEntities
     * @param array<ExamSystemEntity>       $examSystemEntities
     * @param array<DiagnosisEntity>        $diagnosisEntities
     * @param array<PlanActionEntity>       $planActionEntities
     * @param array<PrescriptionLineEntity> $prescriptionLineEntities
     * @param array<BillingLineEntity>      $billingLineEntities
     */
    public function toDomain(
        ConsultationEntity $entity,
        array $noteEntities,
        array $actEntities,
        array $motifEntities = [],
        array $typedVitalEntities = [],
        array $examSystemEntities = [],
        array $diagnosisEntities = [],
        array $planActionEntities = [],
        array $prescriptionLineEntities = [],
        array $billingLineEntities = [],
    ): Consultation {
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
            notes: array_values(array_map($this->noteMapper->toDomain(...), $noteEntities)),
            acts: array_values(array_map($this->actMapper->toDomain(...), $actEntities)),
            subjectiveText: $entity->getSubjectiveText(),
            objectiveObservations: $entity->getObjectiveObservations(),
            teamMemo: $entity->getTeamMemo(),
            motifs: array_values(array_map($this->motifMapper->toDomain(...), $motifEntities)),
            typedVitals: array_values(array_map($this->typedVitalMapper->toDomain(...), $typedVitalEntities)),
            examSystems: array_values(array_map($this->examSystemMapper->toDomain(...), $examSystemEntities)),
            diagnoses: array_values(array_map($this->diagnosisMapper->toDomain(...), $diagnosisEntities)),
            planActions: array_values(array_map($this->planActionMapper->toDomain(...), $planActionEntities)),
            prescriptionLines: array_values(
                array_map($this->prescriptionLineMapper->toDomain(...), $prescriptionLineEntities),
            ),
            billingLines: array_values(array_map($this->billingLineMapper->toDomain(...), $billingLineEntities)),
        );
    }
}
