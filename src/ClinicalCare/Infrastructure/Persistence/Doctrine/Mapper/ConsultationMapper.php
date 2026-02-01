<?php

declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Persistence\Doctrine\Mapper;

use App\ClinicalCare\Domain\Consultation;
use App\ClinicalCare\Domain\ValueObject\AnimalId;
use App\ClinicalCare\Domain\ValueObject\AppointmentId;
use App\ClinicalCare\Domain\ValueObject\ClinicId;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\ClinicalCare\Domain\ValueObject\ConsultationStatus;
use App\ClinicalCare\Domain\ValueObject\OwnerId;
use App\ClinicalCare\Domain\ValueObject\UserId;
use App\ClinicalCare\Domain\ValueObject\Vitals;
use App\ClinicalCare\Domain\ValueObject\WaitingRoomEntryId;
use App\ClinicalCare\Infrastructure\Persistence\Doctrine\Entity\ConsultationEntity;
use Symfony\Component\Uid\Uuid;

final readonly class ConsultationMapper
{
    public function __construct(
        private ClinicalNoteMapper $noteMapper,
        private PerformedActMapper $actMapper,
    ) {
    }

    public function toEntity(Consultation $consultation): ConsultationEntity
    {
        $entity = new ConsultationEntity();
        $entity->setId(Uuid::fromString($consultation->getId()->toString())->toBinary());
        $entity->setClinicId(Uuid::fromString($consultation->getClinicId()->toString())->toBinary());
        $entity->setAppointmentId($consultation->getAppointmentId() 
            ? Uuid::fromString($consultation->getAppointmentId()->toString())->toBinary()
            : null
        );
        $entity->setWaitingRoomEntryId($consultation->getWaitingRoomEntryId()
            ? Uuid::fromString($consultation->getWaitingRoomEntryId()->toString())->toBinary()
            : null
        );
        $entity->setOwnerId($consultation->getOwnerId()
            ? Uuid::fromString($consultation->getOwnerId()->toString())->toBinary()
            : null
        );
        $entity->setAnimalId($consultation->getAnimalId()
            ? Uuid::fromString($consultation->getAnimalId()->toString())->toBinary()
            : null
        );
        $entity->setPractitionerUserId(Uuid::fromString($consultation->getPractitionerUserId()->toString())->toBinary());
        $entity->setStatus($consultation->getStatus()->value);
        $entity->setChiefComplaint($consultation->getChiefComplaint());
        $entity->setSummary($consultation->getSummary());

        $vitals = $consultation->getVitals();
        $entity->setWeightKg($vitals?->weightKg !== null ? (string) $vitals->weightKg : null);
        $entity->setTemperatureC($vitals?->temperatureC !== null ? (string) $vitals->temperatureC : null);

        $entity->setStartedAtUtc($consultation->getStartedAtUtc());
        $entity->setClosedAtUtc($consultation->getClosedAtUtc());
        $entity->setCreatedAtUtc($consultation->getCreatedAtUtc());
        $entity->setUpdatedAtUtc($consultation->getUpdatedAtUtc());

        return $entity;
    }

    public function toDomain(ConsultationEntity $entity, array $noteEntities, array $actEntities): Consultation
    {
        $notes = array_map($this->noteMapper->toDomain(...), $noteEntities);
        $acts = array_map($this->actMapper->toDomain(...), $actEntities);

        $vitals = null;
        if ($entity->getWeightKg() !== null || $entity->getTemperatureC() !== null) {
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
            waitingRoomEntryId: $entity->getWaitingRoomEntryId()
                ? WaitingRoomEntryId::fromString(Uuid::fromBinary($entity->getWaitingRoomEntryId())->toRfc4122())
                : null,
            practitionerUserId: UserId::fromString(Uuid::fromBinary($entity->getPractitionerUserId())->toRfc4122()),
            ownerId: $entity->getOwnerId()
                ? OwnerId::fromString(Uuid::fromBinary($entity->getOwnerId())->toRfc4122())
                : null,
            animalId: $entity->getAnimalId()
                ? AnimalId::fromString(Uuid::fromBinary($entity->getAnimalId())->toRfc4122())
                : null,
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
