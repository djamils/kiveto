<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingPreventiveCareKind;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\BookingMotifEntity;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\BookingMotifPractitionerEntity;
use Symfony\Component\Uid\Uuid;

final readonly class BookingMotifMapper
{
    public function toDomain(BookingMotifEntity $entity): BookingMotif
    {
        $preventiveCareKind = $entity->getPreventiveCareKind();

        return BookingMotif::reconstitute(
            id: BookingMotifId::fromString($entity->getId()->toRfc4122()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toRfc4122()),
            label: $entity->getLabel(),
            description: $entity->getDescription(),
            durationMin: $entity->getDurationMin(),
            onlineEnabled: $entity->isOnlineEnabled(),
            confirmationKnownClient: ConfirmationMode::from($entity->getConfirmationKnownClient()),
            allowedBlockTypes: array_map(PlanningBlockType::from(...), $entity->getAllowedBlockTypes()),
            practitionerIds: array_values($entity->getPractitioners()->map(
                static fn (BookingMotifPractitionerEntity $row): UserId => UserId::fromString($row->getUserId()->toRfc4122()),
            )->toArray()),
            preventiveCareKind: null === $preventiveCareKind ? null : BookingPreventiveCareKind::from($preventiveCareKind),
            newClientsAllowed: $entity->isNewClientsAllowed(),
            sortOrder: $entity->getSortOrder(),
            archivedAt: $entity->getArchivedAt(),
        );
    }

    public function toEntity(BookingMotif $motif): BookingMotifEntity
    {
        $entity = new BookingMotifEntity();
        $entity->setId(Uuid::fromString($motif->id()->toString()));
        $entity->setClinicId(Uuid::fromString($motif->clinicId()->toString()));
        $this->updateEntity($motif, $entity);

        return $entity;
    }

    public function updateEntity(BookingMotif $motif, BookingMotifEntity $entity): void
    {
        $entity->setLabel($motif->label());
        $entity->setDescription($motif->description());
        $entity->setDurationMin($motif->durationMin());
        $entity->setOnlineEnabled($motif->isOnlineEnabled());
        $entity->setConfirmationKnownClient($motif->confirmationKnownClient()->value);
        $entity->setAllowedBlockTypes(array_map(
            static fn (PlanningBlockType $type): string => $type->value,
            $motif->allowedBlockTypes(),
        ));
        $entity->setPreventiveCareKind($motif->preventiveCareKind()?->value);
        $entity->setNewClientsAllowed($motif->newClientsAllowed());
        $entity->setSortOrder($motif->sortOrder());
        $entity->setArchivedAt($motif->archivedAt());
        $entity->syncPractitioners(array_map(
            static fn (UserId $id): Uuid => Uuid::fromString($id->toString()),
            $motif->practitionerIds(),
        ));
    }
}
