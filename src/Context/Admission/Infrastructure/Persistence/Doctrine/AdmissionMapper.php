<?php

declare(strict_types=1);

namespace App\Context\Admission\Infrastructure\Persistence\Doctrine;

use App\Context\Admission\Domain\Admission;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Domain\ValueObject\LocationStatus;
use App\Context\Admission\Domain\ValueObject\Presenter;
use App\Context\Admission\Infrastructure\Persistence\Doctrine\Entity\AdmissionEntity;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Symfony\Component\Uid\Uuid;

final readonly class AdmissionMapper
{
    public function toDomain(AdmissionEntity $entity): Admission
    {
        $locationStatus = new LocationStatus(
            $entity->getLocationStatusValue(),
            $entity->getLocationStatusEnteredAt(),
        );

        $presenter = null;
        if (null !== $entity->getPresenterName() && null !== $entity->getPresenterRole()) {
            $phone = null !== $entity->getPresenterPhone()
                ? PhoneNumber::fromString($entity->getPresenterPhone())
                : null;
            $presenter = Presenter::create(
                $entity->getPresenterName(),
                $phone,
                $entity->getPresenterRole(),
            );
        }

        return Admission::reconstituteFromPersistence(
            id: AdmissionId::fromString($entity->getId()->toString()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toString()),
            patientId: $entity->getPatientId()->toString(),
            isPatientIdentifiedAtOpening: $entity->isPatientIdentifiedAtOpening(),
            intakeChannel: $entity->getIntakeChannel(),
            triageLevel: $entity->getTriageLevel(),
            presenter: $presenter,
            locationStatus: $locationStatus,
            appointmentId: $entity->getAppointmentId()?->toString(),
            physicalDescription: $entity->getPhysicalDescription(),
            status: $entity->getStatus(),
            closureReason: $entity->getClosureReason(),
            triageNotes: $entity->getTriageNotes(),
            version: $entity->getVersion(),
            openedAt: $entity->getOpenedAt(),
            closedAt: $entity->getClosedAt(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public function toEntity(Admission $admission): AdmissionEntity
    {
        $entity = new AdmissionEntity();

        $entity->setId(Uuid::fromString($admission->id()->value()));
        $entity->setClinicId(Uuid::fromString($admission->clinicId()->toString()));
        $entity->setPatientId(Uuid::fromString($admission->patientId()));
        $entity->setIsPatientIdentifiedAtOpening($admission->isPatientIdentifiedAtOpening());
        $entity->setIntakeChannel($admission->intakeChannel());
        $entity->setTriageLevel($admission->triageLevel());

        $presenter = $admission->presenter();
        if (null !== $presenter) {
            $entity->setPresenterName($presenter->name());
            $entity->setPresenterPhone($presenter->phone()?->toString());
            $entity->setPresenterRole($presenter->role());
        } else {
            $entity->setPresenterName(null);
            $entity->setPresenterPhone(null);
            $entity->setPresenterRole(null);
        }

        $entity->setLocationStatusValue($admission->locationStatus()->value());
        $entity->setLocationStatusEnteredAt($admission->locationStatus()->enteredAt());
        $entity->setStatus($admission->status());
        $entity->setClosureReason($admission->closureReason());

        $appointmentId = $admission->appointmentId();
        $entity->setAppointmentId(null !== $appointmentId ? Uuid::fromString($appointmentId) : null);

        $entity->setPhysicalDescription($admission->physicalDescription());
        $entity->setTriageNotes($admission->triageNotes());
        $entity->setVersion($admission->version());
        $entity->setOpenedAt($admission->openedAt());
        $entity->setClosedAt($admission->closedAt());
        $entity->setCreatedAt($admission->createdAt());
        $entity->setUpdatedAt($admission->updatedAt());

        return $entity;
    }
}
