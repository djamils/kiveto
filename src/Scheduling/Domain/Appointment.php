<?php

declare(strict_types=1);

namespace App\Scheduling\Domain;

use App\Scheduling\Domain\Event\AppointmentCancelled;
use App\Scheduling\Domain\Event\AppointmentCompleted;
use App\Scheduling\Domain\Event\AppointmentMarkedNoShow;
use App\Scheduling\Domain\Event\AppointmentPractitionerAssigneeChanged;
use App\Scheduling\Domain\Event\AppointmentPractitionerAssigneeUnassigned;
use App\Scheduling\Domain\Event\AppointmentRescheduled;
use App\Scheduling\Domain\Event\AppointmentScheduled;
use App\Scheduling\Domain\Event\AppointmentServiceStarted;
use App\Scheduling\Domain\ValueObject\AnimalId;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\OwnerId;
use App\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class Appointment extends AggregateRoot
{
    private AppointmentId $id;
    private ClinicId $clinicId;
    private ?OwnerId $ownerId;
    private ?AnimalId $animalId;
    private ?PractitionerAssignee $practitionerAssignee;
    private TimeSlot $timeSlot;
    private AppointmentStatus $status;
    private ?string $reason;
    private ?string $notes;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $serviceStartedAt;

    private function __construct()
    {
    }

    public static function schedule(
        AppointmentId $id,
        ClinicId $clinicId,
        ?OwnerId $ownerId,
        ?AnimalId $animalId,
        ?PractitionerAssignee $practitionerAssignee,
        TimeSlot $timeSlot,
        ?string $reason,
        ?string $notes,
        \DateTimeImmutable $createdAt,
    ): self {
        $appointment                       = new self();
        $appointment->id                   = $id;
        $appointment->clinicId             = $clinicId;
        $appointment->ownerId              = $ownerId;
        $appointment->animalId             = $animalId;
        $appointment->practitionerAssignee = $practitionerAssignee;
        $appointment->timeSlot             = $timeSlot;
        $appointment->status               = AppointmentStatus::PLANNED;
        $appointment->reason               = $reason;
        $appointment->notes                = $notes;
        $appointment->createdAt            = $createdAt;
        $appointment->serviceStartedAt     = null;

        $appointment->recordDomainEvent(new AppointmentScheduled(
            appointmentId: $id->toString(),
            clinicId: $clinicId->toString(),
            ownerId: $ownerId?->toString(),
            animalId: $animalId?->toString(),
            practitionerUserId: $practitionerAssignee?->userId()->toString(),
            startsAtUtc: $timeSlot->startsAtUtc()->format(\DateTimeInterface::ATOM),
            durationMinutes: $timeSlot->durationMinutes(),
            reason: $reason,
            notes: $notes,
        ));

        return $appointment;
    }

    public static function reconstitute(
        AppointmentId $id,
        ClinicId $clinicId,
        ?OwnerId $ownerId,
        ?AnimalId $animalId,
        ?PractitionerAssignee $practitionerAssignee,
        TimeSlot $timeSlot,
        AppointmentStatus $status,
        ?string $reason,
        ?string $notes,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $serviceStartedAt,
    ): self {
        $appointment                       = new self();
        $appointment->id                   = $id;
        $appointment->clinicId             = $clinicId;
        $appointment->ownerId              = $ownerId;
        $appointment->animalId             = $animalId;
        $appointment->practitionerAssignee = $practitionerAssignee;
        $appointment->timeSlot             = $timeSlot;
        $appointment->status               = $status;
        $appointment->reason               = $reason;
        $appointment->notes                = $notes;
        $appointment->createdAt            = $createdAt;
        $appointment->serviceStartedAt     = $serviceStartedAt;

        return $appointment;
    }

    public function reschedule(TimeSlot $newTimeSlot): void
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException('Cannot reschedule a terminated appointment.');
        }

        if ($this->timeSlot->equals($newTimeSlot)) {
            return;
        }

        $oldTimeSlot    = $this->timeSlot;
        $this->timeSlot = $newTimeSlot;

        $this->recordDomainEvent(new AppointmentRescheduled(
            appointmentId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            oldStartsAtUtc: $oldTimeSlot->startsAtUtc()->format(\DateTimeInterface::ATOM),
            oldDurationMinutes: $oldTimeSlot->durationMinutes(),
            newStartsAtUtc: $newTimeSlot->startsAtUtc()->format(\DateTimeInterface::ATOM),
            newDurationMinutes: $newTimeSlot->durationMinutes(),
        ));
    }

    public function changePractitionerAssignee(PractitionerAssignee $newAssignee): void
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException('Cannot change practitioner for a terminated appointment.');
        }

        if (null !== $this->practitionerAssignee && $this->practitionerAssignee->equals($newAssignee)) {
            return;
        }

        $oldPractitionerId          = $this->practitionerAssignee?->userId()->toString();
        $this->practitionerAssignee = $newAssignee;

        $this->recordDomainEvent(new AppointmentPractitionerAssigneeChanged(
            appointmentId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            oldPractitionerUserId: $oldPractitionerId,
            newPractitionerUserId: $newAssignee->userId()->toString(),
        ));
    }

    public function unassignPractitioner(): void
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException('Cannot unassign practitioner from a terminated appointment.');
        }

        if (null === $this->practitionerAssignee) {
            return;
        }

        $previousPractitionerId     = $this->practitionerAssignee->userId()->toString();
        $this->practitionerAssignee = null;

        $this->recordDomainEvent(new AppointmentPractitionerAssigneeUnassigned(
            appointmentId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            previousPractitionerUserId: $previousPractitionerId,
        ));
    }

    public function cancel(): void
    {
        if (AppointmentStatus::CANCELLED === $this->status) {
            return;
        }

        if ($this->status->isTerminal()) {
            throw new \DomainException('Cannot cancel an appointment that is already terminated.');
        }

        $this->status = AppointmentStatus::CANCELLED;

        $this->recordDomainEvent(new AppointmentCancelled(
            appointmentId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    public function markNoShow(): void
    {
        if (AppointmentStatus::NO_SHOW === $this->status) {
            return;
        }

        if ($this->status->isTerminal()) {
            throw new \DomainException('Cannot mark as no-show an appointment that is already terminated.');
        }

        $this->status = AppointmentStatus::NO_SHOW;

        $this->recordDomainEvent(new AppointmentMarkedNoShow(
            appointmentId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    public function complete(): void
    {
        if (AppointmentStatus::COMPLETED === $this->status) {
            return;
        }

        if ($this->status->isTerminal()) {
            throw new \DomainException('Cannot complete an appointment that is already terminated.');
        }

        $this->status = AppointmentStatus::COMPLETED;

        $this->recordDomainEvent(new AppointmentCompleted(
            appointmentId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    public function startService(\DateTimeImmutable $serviceStartedAt): void
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException('Cannot start service for a terminated appointment.');
        }

        if (null !== $this->serviceStartedAt) {
            return; // already started
        }

        $this->serviceStartedAt = $serviceStartedAt;

        $this->recordDomainEvent(new AppointmentServiceStarted(
            appointmentId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            serviceStartedAtUtc: $serviceStartedAt->format(\DateTimeInterface::ATOM),
        ));
    }

    public function id(): AppointmentId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function ownerId(): ?OwnerId
    {
        return $this->ownerId;
    }

    public function animalId(): ?AnimalId
    {
        return $this->animalId;
    }

    public function practitionerAssignee(): ?PractitionerAssignee
    {
        return $this->practitionerAssignee;
    }

    public function timeSlot(): TimeSlot
    {
        return $this->timeSlot;
    }

    public function status(): AppointmentStatus
    {
        return $this->status;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function serviceStartedAt(): ?\DateTimeImmutable
    {
        return $this->serviceStartedAt;
    }
}
