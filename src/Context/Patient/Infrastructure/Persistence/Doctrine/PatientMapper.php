<?php

declare(strict_types=1);

namespace App\Context\Patient\Infrastructure\Persistence\Doctrine;

use App\Context\Patient\Domain\Patient;
use App\Context\Patient\Domain\ValueObject\AnimalLink;
use App\Context\Patient\Domain\ValueObject\ClinicId;
use App\Context\Patient\Domain\ValueObject\DisplayLabel;
use App\Context\Patient\Domain\ValueObject\DisplayLabelKind;
use App\Context\Patient\Domain\ValueObject\PatientId;
use App\Context\Patient\Infrastructure\Persistence\Doctrine\Entity\PatientEntity;
use Symfony\Component\Uid\Uuid;

final readonly class PatientMapper
{
    public function toDomain(PatientEntity $entity): Patient
    {
        $displayLabel = match ($entity->getDisplayLabelKind()) {
            DisplayLabelKind::Provisional => DisplayLabel::provisional($entity->getDisplayLabelValue()),
            DisplayLabelKind::FromAnimal  => DisplayLabel::fromAnimal($entity->getDisplayLabelValue()),
            DisplayLabelKind::Custom      => DisplayLabel::custom($entity->getDisplayLabelValue()),
        };

        $animalLinkId = $entity->getAnimalLinkId();
        $animalLink   = null !== $animalLinkId
            ? AnimalLink::fromAnimalId($animalLinkId->toString())
            : null;

        return Patient::reconstituteFromPersistence(
            id: PatientId::fromString($entity->getId()->toString()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toString()),
            status: $entity->getStatus(),
            displayLabel: $displayLabel,
            animalLink: $animalLink,
            observedSpecies: $entity->getObservedSpecies(),
            observedColor: $entity->getObservedColor(),
            version: $entity->getVersion(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public function toEntity(Patient $patient): PatientEntity
    {
        $entity = new PatientEntity();

        $entity->setId(Uuid::fromString($patient->id()->toString()));
        $entity->setClinicId(Uuid::fromString($patient->clinicId()->toString()));
        $entity->setStatus($patient->status());
        $entity->setDisplayLabelKind($patient->displayLabel()->kind());
        $entity->setDisplayLabelValue($patient->displayLabel()->value());

        $animalLink = $patient->animalLink();
        if (null !== $animalLink) {
            $entity->setAnimalLinkId(Uuid::fromString($animalLink->animalId()));
        } else {
            $entity->setAnimalLinkId(null);
        }

        $entity->setObservedSpecies($patient->observedSpecies());
        $entity->setObservedColor($patient->observedColor());
        $entity->setVersion($patient->version());
        $entity->setCreatedAt($patient->createdAt());
        $entity->setUpdatedAt($patient->updatedAt());

        return $entity;
    }
}
