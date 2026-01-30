<?php

declare(strict_types=1);

namespace App\Scheduling\Domain;

use App\Scheduling\Domain\Event\WaitingRoomEntryCalled;
use App\Scheduling\Domain\Event\WaitingRoomEntryClosed;
use App\Scheduling\Domain\Event\WaitingRoomEntryCreatedFromAppointment;
use App\Scheduling\Domain\Event\WaitingRoomEntryLinkedToOwnerAndAnimal;
use App\Scheduling\Domain\Event\WaitingRoomEntryServiceStarted;
use App\Scheduling\Domain\Event\WaitingRoomEntryTriageUpdated;
use App\Scheduling\Domain\Event\WaitingRoomWalkInEntryCreated;
use App\Scheduling\Domain\ValueObject\AnimalId;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\OwnerId;
use App\Scheduling\Domain\ValueObject\UserId;
use App\Scheduling\Domain\ValueObject\WaitingRoomArrivalMode;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryOrigin;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryStatus;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class WaitingRoomEntry extends AggregateRoot
{
    private WaitingRoomEntryId $id;
    private ClinicId $clinicId;
    private WaitingRoomEntryOrigin $origin;
    private WaitingRoomArrivalMode $arrivalMode;
    private ?AppointmentId $linkedAppointmentId;
    private ?OwnerId $ownerId;
    private ?AnimalId $animalId;
    private ?string $foundAnimalDescription;
    private int $priority;
    private ?string $triageNotes;
    private WaitingRoomEntryStatus $status;
    private \DateTimeImmutable $arrivedAtUtc;
    private ?\DateTimeImmutable $calledAtUtc;
    private ?\DateTimeImmutable $serviceStartedAtUtc;
    private ?\DateTimeImmutable $closedAtUtc;
    private ?UserId $calledByUserId;
    private ?UserId $serviceStartedByUserId;
    private ?UserId $closedByUserId;

    private function __construct()
    {
    }

    public static function createFromAppointment(
        WaitingRoomEntryId $id,
        ClinicId $clinicId,
        AppointmentId $linkedAppointmentId,
        ?OwnerId $ownerId,
        ?AnimalId $animalId,
        WaitingRoomArrivalMode $arrivalMode,
        int $priority,
        \DateTimeImmutable $arrivedAtUtc,
    ): self {
        $entry                         = new self();
        $entry->id                     = $id;
        $entry->clinicId               = $clinicId;
        $entry->origin                 = WaitingRoomEntryOrigin::SCHEDULED;
        $entry->arrivalMode            = $arrivalMode;
        $entry->linkedAppointmentId    = $linkedAppointmentId;
        $entry->ownerId                = $ownerId;
        $entry->animalId               = $animalId;
        $entry->foundAnimalDescription = null;
        $entry->priority               = $priority;
        $entry->triageNotes            = null;
        $entry->status                 = WaitingRoomEntryStatus::WAITING;
        $entry->arrivedAtUtc           = $arrivedAtUtc;
        $entry->calledAtUtc            = null;
        $entry->serviceStartedAtUtc    = null;
        $entry->closedAtUtc            = null;
        $entry->calledByUserId         = null;
        $entry->serviceStartedByUserId = null;
        $entry->closedByUserId         = null;

        $entry->recordDomainEvent(new WaitingRoomEntryCreatedFromAppointment(
            waitingRoomEntryId: $id->toString(),
            clinicId: $clinicId->toString(),
            linkedAppointmentId: $linkedAppointmentId->toString(),
            ownerId: $ownerId?->toString(),
            animalId: $animalId?->toString(),
            arrivalMode: $arrivalMode->value,
            priority: $priority,
            arrivedAtUtc: $arrivedAtUtc->format(\DateTimeInterface::ATOM),
        ));

        return $entry;
    }

    public static function createWalkIn(
        WaitingRoomEntryId $id,
        ClinicId $clinicId,
        ?OwnerId $ownerId,
        ?AnimalId $animalId,
        ?string $foundAnimalDescription,
        WaitingRoomArrivalMode $arrivalMode,
        int $priority,
        ?string $triageNotes,
        \DateTimeImmutable $arrivedAtUtc,
    ): self {
        $entry                         = new self();
        $entry->id                     = $id;
        $entry->clinicId               = $clinicId;
        $entry->origin                 = WaitingRoomEntryOrigin::WALK_IN;
        $entry->arrivalMode            = $arrivalMode;
        $entry->linkedAppointmentId    = null;
        $entry->ownerId                = $ownerId;
        $entry->animalId               = $animalId;
        $entry->foundAnimalDescription = $foundAnimalDescription;
        $entry->priority               = $priority;
        $entry->triageNotes            = $triageNotes;
        $entry->status                 = WaitingRoomEntryStatus::WAITING;
        $entry->arrivedAtUtc           = $arrivedAtUtc;
        $entry->calledAtUtc            = null;
        $entry->serviceStartedAtUtc    = null;
        $entry->closedAtUtc            = null;
        $entry->calledByUserId         = null;
        $entry->serviceStartedByUserId = null;
        $entry->closedByUserId         = null;

        $entry->recordDomainEvent(new WaitingRoomWalkInEntryCreated(
            waitingRoomEntryId: $id->toString(),
            clinicId: $clinicId->toString(),
            ownerId: $ownerId?->toString(),
            animalId: $animalId?->toString(),
            foundAnimalDescription: $foundAnimalDescription,
            arrivalMode: $arrivalMode->value,
            priority: $priority,
            triageNotes: $triageNotes,
            arrivedAtUtc: $arrivedAtUtc->format(\DateTimeInterface::ATOM),
        ));

        return $entry;
    }

    public static function reconstitute(
        WaitingRoomEntryId $id,
        ClinicId $clinicId,
        WaitingRoomEntryOrigin $origin,
        WaitingRoomArrivalMode $arrivalMode,
        ?AppointmentId $linkedAppointmentId,
        ?OwnerId $ownerId,
        ?AnimalId $animalId,
        ?string $foundAnimalDescription,
        int $priority,
        ?string $triageNotes,
        WaitingRoomEntryStatus $status,
        \DateTimeImmutable $arrivedAtUtc,
        ?\DateTimeImmutable $calledAtUtc,
        ?\DateTimeImmutable $serviceStartedAtUtc,
        ?\DateTimeImmutable $closedAtUtc,
        ?UserId $calledByUserId,
        ?UserId $serviceStartedByUserId,
        ?UserId $closedByUserId,
    ): self {
        $entry                         = new self();
        $entry->id                     = $id;
        $entry->clinicId               = $clinicId;
        $entry->origin                 = $origin;
        $entry->arrivalMode            = $arrivalMode;
        $entry->linkedAppointmentId    = $linkedAppointmentId;
        $entry->ownerId                = $ownerId;
        $entry->animalId               = $animalId;
        $entry->foundAnimalDescription = $foundAnimalDescription;
        $entry->priority               = $priority;
        $entry->triageNotes            = $triageNotes;
        $entry->status                 = $status;
        $entry->arrivedAtUtc           = $arrivedAtUtc;
        $entry->calledAtUtc            = $calledAtUtc;
        $entry->serviceStartedAtUtc    = $serviceStartedAtUtc;
        $entry->closedAtUtc            = $closedAtUtc;
        $entry->calledByUserId         = $calledByUserId;
        $entry->serviceStartedByUserId = $serviceStartedByUserId;
        $entry->closedByUserId         = $closedByUserId;

        return $entry;
    }

    public function updateTriage(
        int $priority,
        ?string $triageNotes,
        WaitingRoomArrivalMode $arrivalMode,
    ): void {
        if (WaitingRoomEntryStatus::CLOSED === $this->status) {
            throw new \DomainException('Cannot update triage for a closed entry.');
        }

        if ($this->priority === $priority
            && $this->triageNotes === $triageNotes
            && $this->arrivalMode === $arrivalMode
        ) {
            return;
        }

        $this->priority     = $priority;
        $this->triageNotes  = $triageNotes;
        $this->arrivalMode  = $arrivalMode;

        $this->recordDomainEvent(new WaitingRoomEntryTriageUpdated(
            waitingRoomEntryId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            priority: $priority,
            triageNotes: $triageNotes,
            arrivalMode: $arrivalMode->value,
        ));
    }

    public function call(\DateTimeImmutable $calledAt, ?UserId $calledByUserId): void
    {
        if (!$this->status->canTransitionTo(WaitingRoomEntryStatus::CALLED)) {
            throw new \DomainException(\sprintf(
                'Cannot transition from %s to CALLED.',
                $this->status->value
            ));
        }

        $this->status          = WaitingRoomEntryStatus::CALLED;
        $this->calledAtUtc     = $calledAt;
        $this->calledByUserId  = $calledByUserId;

        $this->recordDomainEvent(new WaitingRoomEntryCalled(
            waitingRoomEntryId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            calledAtUtc: $calledAt->format(\DateTimeInterface::ATOM),
            calledByUserId: $calledByUserId?->toString(),
        ));
    }

    public function startService(\DateTimeImmutable $serviceStartedAt, ?UserId $serviceStartedByUserId): void
    {
        if (!$this->status->canTransitionTo(WaitingRoomEntryStatus::IN_SERVICE)) {
            throw new \DomainException(\sprintf(
                'Cannot transition from %s to IN_SERVICE.',
                $this->status->value
            ));
        }

        $this->status                  = WaitingRoomEntryStatus::IN_SERVICE;
        $this->serviceStartedAtUtc     = $serviceStartedAt;
        $this->serviceStartedByUserId  = $serviceStartedByUserId;

        $this->recordDomainEvent(new WaitingRoomEntryServiceStarted(
            waitingRoomEntryId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            serviceStartedAtUtc: $serviceStartedAt->format(\DateTimeInterface::ATOM),
            serviceStartedByUserId: $serviceStartedByUserId?->toString(),
        ));
    }

    public function close(\DateTimeImmutable $closedAt, ?UserId $closedByUserId): void
    {
        if (!$this->status->canTransitionTo(WaitingRoomEntryStatus::CLOSED)) {
            throw new \DomainException(\sprintf(
                'Cannot transition from %s to CLOSED.',
                $this->status->value
            ));
        }

        $this->status         = WaitingRoomEntryStatus::CLOSED;
        $this->closedAtUtc    = $closedAt;
        $this->closedByUserId = $closedByUserId;

        $this->recordDomainEvent(new WaitingRoomEntryClosed(
            waitingRoomEntryId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            closedAtUtc: $closedAt->format(\DateTimeInterface::ATOM),
            closedByUserId: $closedByUserId?->toString(),
        ));
    }

    public function linkToOwnerAndAnimal(?OwnerId $ownerId, ?AnimalId $animalId): void
    {
        if (WaitingRoomEntryStatus::CLOSED === $this->status) {
            throw new \DomainException('Cannot link owner/animal to a closed entry.');
        }

        if ($this->ownerId === $ownerId && $this->animalId === $animalId) {
            return;
        }

        $this->ownerId  = $ownerId;
        $this->animalId = $animalId;

        $this->recordDomainEvent(new WaitingRoomEntryLinkedToOwnerAndAnimal(
            waitingRoomEntryId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            ownerId: $ownerId?->toString(),
            animalId: $animalId?->toString(),
        ));
    }

    public function id(): WaitingRoomEntryId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function origin(): WaitingRoomEntryOrigin
    {
        return $this->origin;
    }

    public function arrivalMode(): WaitingRoomArrivalMode
    {
        return $this->arrivalMode;
    }

    public function linkedAppointmentId(): ?AppointmentId
    {
        return $this->linkedAppointmentId;
    }

    public function ownerId(): ?OwnerId
    {
        return $this->ownerId;
    }

    public function animalId(): ?AnimalId
    {
        return $this->animalId;
    }

    public function foundAnimalDescription(): ?string
    {
        return $this->foundAnimalDescription;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function triageNotes(): ?string
    {
        return $this->triageNotes;
    }

    public function status(): WaitingRoomEntryStatus
    {
        return $this->status;
    }

    public function arrivedAtUtc(): \DateTimeImmutable
    {
        return $this->arrivedAtUtc;
    }

    public function calledAtUtc(): ?\DateTimeImmutable
    {
        return $this->calledAtUtc;
    }

    public function serviceStartedAtUtc(): ?\DateTimeImmutable
    {
        return $this->serviceStartedAtUtc;
    }

    public function closedAtUtc(): ?\DateTimeImmutable
    {
        return $this->closedAtUtc;
    }

    public function calledByUserId(): ?UserId
    {
        return $this->calledByUserId;
    }

    public function serviceStartedByUserId(): ?UserId
    {
        return $this->serviceStartedByUserId;
    }

    public function closedByUserId(): ?UserId
    {
        return $this->closedByUserId;
    }
}
