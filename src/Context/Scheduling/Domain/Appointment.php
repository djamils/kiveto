<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain;

use App\Context\Scheduling\Domain\Event\AppointmentCancelled;
use App\Context\Scheduling\Domain\Event\AppointmentCompleted;
use App\Context\Scheduling\Domain\Event\AppointmentConfirmed;
use App\Context\Scheduling\Domain\Event\AppointmentDeclined;
use App\Context\Scheduling\Domain\Event\AppointmentMarkedNoShow;
use App\Context\Scheduling\Domain\Event\AppointmentOwnerChanged;
use App\Context\Scheduling\Domain\Event\AppointmentPractitionerAssigneeChanged;
use App\Context\Scheduling\Domain\Event\AppointmentRequested;
use App\Context\Scheduling\Domain\Event\AppointmentRescheduled;
use App\Context\Scheduling\Domain\Event\AppointmentScheduled;
use App\Context\Scheduling\Domain\Event\AppointmentServiceStarted;
use App\Context\Scheduling\Domain\Exception\AppointmentNotModifiableException;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\CancellationReason;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\DeclineReason;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class Appointment extends AggregateRoot
{
    private AppointmentId $id;
    private ClinicId $clinicId;
    private ?string $linkedAdmissionId;
    private ?string $ownerId;
    private ?string $animalId;
    private PractitionerAssignee $practitionerAssignee;
    private TimeSlot $timeSlot;
    private AppointmentStatus $status;
    private ?string $reason;
    private ?string $notes;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $serviceStartedAt;
    private AppointmentSource $source;
    private ?string $bookingMotifId;
    private ?CancellationActor $cancelledBy;
    private ?CancellationReason $cancellationReason;
    private ?\DateTimeImmutable $pendingExpiresAt;
    private bool $confirmedSilently;
    private ?string $createdPetId;

    private function __construct()
    {
    }

    public static function schedule(
        AppointmentId $id,
        ClinicId $clinicId,
        PractitionerAssignee $practitionerAssignee,
        TimeSlot $timeSlot,
        ?string $reason,
        ?string $notes,
        \DateTimeImmutable $createdAt,
        ?string $linkedAdmissionId = null,
        ?string $ownerId = null,
        ?string $animalId = null,
        ?string $actorUserId = null,
        AppointmentSource $source = AppointmentSource::CLINIC,
        ?string $bookingMotifId = null,
        ?string $createdPetId = null,
    ): self {
        $appointment                       = new self();
        $appointment->id                   = $id;
        $appointment->clinicId             = $clinicId;
        $appointment->linkedAdmissionId    = $linkedAdmissionId;
        $appointment->ownerId              = $ownerId;
        $appointment->animalId             = $animalId;
        $appointment->practitionerAssignee = $practitionerAssignee;
        $appointment->timeSlot             = $timeSlot;
        $appointment->status               = AppointmentStatus::PLANNED;
        $appointment->reason               = $reason;
        $appointment->notes                = $notes;
        $appointment->createdAt            = $createdAt;
        $appointment->serviceStartedAt     = null;
        $appointment->source               = $source;
        $appointment->bookingMotifId       = $bookingMotifId;
        $appointment->cancelledBy          = null;
        $appointment->cancellationReason   = null;
        $appointment->pendingExpiresAt     = null;
        $appointment->confirmedSilently    = false;
        $appointment->createdPetId         = $createdPetId;

        $appointment->recordDomainEvent(new AppointmentScheduled(
            appointmentId: $id->toString(),
            clinicId: $clinicId->toString(),
            linkedAdmissionId: $linkedAdmissionId,
            practitionerUserId: $practitionerAssignee->userId()->toString(),
            startsAtUtc: $timeSlot->startsAtUtc()->format(\DateTimeInterface::ATOM),
            durationMinutes: $timeSlot->durationMinutes(),
            reason: $reason,
            notes: $notes,
            ownerId: $ownerId,
            animalId: $animalId,
            actorUserId: $actorUserId,
        ));

        return $appointment;
    }

    /**
     * An online booking that waits for the clinic's confirmation. The caller
     * computes when it expires (OnlineBookingSettings::pendingExpiry); it must
     * be after the booking time, so a request never expires at creation.
     */
    public static function request(
        AppointmentId $id,
        ClinicId $clinicId,
        PractitionerAssignee $practitionerAssignee,
        TimeSlot $timeSlot,
        ?string $reason,
        ?string $notes,
        \DateTimeImmutable $createdAt,
        ?string $ownerId,
        ?string $animalId,
        ?string $bookingMotifId,
        AppointmentSource $source,
        \DateTimeImmutable $pendingExpiresAt,
        ?string $createdPetId = null,
    ): self {
        if ($pendingExpiresAt <= $createdAt) {
            throw new \InvalidArgumentException('A pending appointment must expire after it is requested.');
        }

        $appointment                       = new self();
        $appointment->id                   = $id;
        $appointment->clinicId             = $clinicId;
        $appointment->linkedAdmissionId    = null;
        $appointment->ownerId              = $ownerId;
        $appointment->animalId             = $animalId;
        $appointment->practitionerAssignee = $practitionerAssignee;
        $appointment->timeSlot             = $timeSlot;
        $appointment->status               = AppointmentStatus::PENDING_CLINIC_CONFIRMATION;
        $appointment->reason               = $reason;
        $appointment->notes                = $notes;
        $appointment->createdAt            = $createdAt;
        $appointment->serviceStartedAt     = null;
        $appointment->source               = $source;
        $appointment->bookingMotifId       = $bookingMotifId;
        $appointment->cancelledBy          = null;
        $appointment->cancellationReason   = null;
        $appointment->pendingExpiresAt     = $pendingExpiresAt;
        $appointment->confirmedSilently    = false;
        $appointment->createdPetId         = $createdPetId;

        $appointment->recordDomainEvent(new AppointmentRequested(
            appointmentId: $id->toString(),
            clinicId: $clinicId->toString(),
            linkedAdmissionId: null,
            practitionerUserId: $practitionerAssignee->userId()->toString(),
            startsAtUtc: $timeSlot->startsAtUtc()->format(\DateTimeInterface::ATOM),
            durationMinutes: $timeSlot->durationMinutes(),
            reason: $reason,
            notes: $notes,
            ownerId: $ownerId,
            animalId: $animalId,
            bookingMotifId: $bookingMotifId,
            source: $source->value,
        ));

        return $appointment;
    }

    public static function reconstitute(
        AppointmentId $id,
        ClinicId $clinicId,
        PractitionerAssignee $practitionerAssignee,
        TimeSlot $timeSlot,
        AppointmentStatus $status,
        ?string $reason,
        ?string $notes,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $serviceStartedAt,
        ?string $linkedAdmissionId = null,
        ?string $ownerId = null,
        ?string $animalId = null,
        AppointmentSource $source = AppointmentSource::CLINIC,
        ?string $bookingMotifId = null,
        ?CancellationActor $cancelledBy = null,
        ?CancellationReason $cancellationReason = null,
        ?\DateTimeImmutable $pendingExpiresAt = null,
        bool $confirmedSilently = false,
        ?string $createdPetId = null,
    ): self {
        $appointment                       = new self();
        $appointment->id                   = $id;
        $appointment->clinicId             = $clinicId;
        $appointment->linkedAdmissionId    = $linkedAdmissionId;
        $appointment->ownerId              = $ownerId;
        $appointment->animalId             = $animalId;
        $appointment->practitionerAssignee = $practitionerAssignee;
        $appointment->timeSlot             = $timeSlot;
        $appointment->status               = $status;
        $appointment->reason               = $reason;
        $appointment->notes                = $notes;
        $appointment->createdAt            = $createdAt;
        $appointment->serviceStartedAt     = $serviceStartedAt;
        $appointment->source               = $source;
        $appointment->bookingMotifId       = $bookingMotifId;
        $appointment->cancelledBy          = $cancelledBy;
        $appointment->cancellationReason   = $cancellationReason;
        $appointment->pendingExpiresAt     = $pendingExpiresAt;
        $appointment->confirmedSilently    = $confirmedSilently;
        $appointment->createdPetId         = $createdPetId;

        return $appointment;
    }

    /**
     * A new owner (set later, corrected or removed) is announced: their
     * client messages are synced again. The animal alone changes nothing the
     * client is told.
     */
    public function changeOwnerAndAnimal(?string $ownerId, ?string $animalId): void
    {
        $oldOwnerId     = $this->ownerId;
        $this->ownerId  = $ownerId;
        $this->animalId = $animalId;

        if ($oldOwnerId !== $ownerId) {
            $this->recordDomainEvent(new AppointmentOwnerChanged(
                appointmentId: $this->id->toString(),
                clinicId: $this->clinicId->toString(),
                oldOwnerId: $oldOwnerId,
                newOwnerId: $ownerId,
            ));
        }
    }

    /**
     * Rewrites the motif and the note of a booking that is still to come.
     * An empty string clears the field.
     */
    public function changeDetails(?string $reason, ?string $notes): void
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException('Cannot change the details of a terminated appointment.');
        }

        $this->reason = null === $reason || '' === trim($reason) ? null : trim($reason);
        $this->notes  = null === $notes || '' === trim($notes) ? null : trim($notes);
    }

    public function linkAdmission(string $admissionId): void
    {
        $this->linkedAdmissionId = $admissionId;
    }

    /**
     * A pending request moved to another time gets a new expiry, computed by
     * the caller for the new start (OnlineBookingSettings::pendingExpiry).
     */
    public function reschedule(TimeSlot $newTimeSlot, ?\DateTimeImmutable $newPendingExpiresAt = null): void
    {
        if ($this->status->isTerminal()) {
            throw new \DomainException('Cannot reschedule a terminated appointment.');
        }

        if ($this->timeSlot->equals($newTimeSlot)) {
            return;
        }

        if ($this->status->isPending()) {
            if (null === $newPendingExpiresAt) {
                throw new \InvalidArgumentException('A rescheduled pending appointment needs its new expiry.');
            }

            // Moved too close to the time it was requested: it would expire at once
            if ($newPendingExpiresAt <= $this->createdAt) {
                throw new AppointmentNotModifiableException('Ce créneau est trop proche pour une demande à confirmer : confirmez-la d’abord.');
            }

            $this->pendingExpiresAt = $newPendingExpiresAt;
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

        if ($this->practitionerAssignee->equals($newAssignee)) {
            return;
        }

        $oldPractitionerId          = $this->practitionerAssignee->userId()->toString();
        $this->practitionerAssignee = $newAssignee;

        $this->recordDomainEvent(new AppointmentPractitionerAssigneeChanged(
            appointmentId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            oldPractitionerUserId: $oldPractitionerId,
            newPractitionerUserId: $newAssignee->userId()->toString(),
        ));
    }

    /**
     * The clinic confirms a pending request. `silent` when the client is on
     * site (check-in, service started): the slot counts as confirmed without
     * any confirmation message.
     */
    public function confirm(bool $silent = false): void
    {
        if (AppointmentStatus::PLANNED === $this->status) {
            return;
        }

        if (!$this->status->isPending()) {
            throw new AppointmentNotModifiableException('Cette demande de rendez-vous n’est plus en attente de confirmation.');
        }

        $this->status            = AppointmentStatus::PLANNED;
        $this->confirmedSilently = $silent;
        $this->pendingExpiresAt  = null;

        $this->recordDomainEvent(new AppointmentConfirmed(
            appointmentId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            practitionerUserId: $this->practitionerAssignee->userId()->toString(),
            startsAtUtc: $this->timeSlot->startsAtUtc()->format(\DateTimeInterface::ATOM),
            ownerId: $this->ownerId,
            animalId: $this->animalId,
            source: $this->source->value,
            silent: $silent,
        ));
    }

    /**
     * The clinic does not confirm a pending request (declined, expired, or
     * cancelled from the agenda while pending).
     *
     * @param bool $notifyClient false when the client must not be told (death of the animal)
     */
    public function decline(DeclineReason $reason, bool $notifyClient = true): void
    {
        if (AppointmentStatus::DECLINED === $this->status) {
            return;
        }

        if (!$this->status->isPending()) {
            throw new AppointmentNotModifiableException('Cette demande de rendez-vous n’est plus en attente de confirmation.');
        }

        $this->status = AppointmentStatus::DECLINED;

        $this->recordDomainEvent(new AppointmentDeclined(
            appointmentId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            practitionerUserId: $this->practitionerAssignee->userId()->toString(),
            startsAtUtc: $this->timeSlot->startsAtUtc()->format(\DateTimeInterface::ATOM),
            ownerId: $this->ownerId,
            animalId: $this->animalId,
            source: $this->source->value,
            reason: $reason->value,
            notifyClient: $notifyClient,
        ));
    }

    /**
     * A clinic cancel of a pending request is a decline; a client cancel of
     * it stays a cancellation (the client withdraws their own request).
     *
     * @param bool $notifyClient false when the client must not be told (death of the animal, D3)
     */
    public function cancel(CancellationActor $actor, ?CancellationReason $reason = null, bool $notifyClient = true): void
    {
        if (AppointmentStatus::CANCELLED === $this->status) {
            return;
        }

        if (CancellationActor::CLINIC === $actor && AppointmentStatus::DECLINED === $this->status) {
            return;
        }

        if ($this->status->isTerminal()) {
            throw new \DomainException('Cannot cancel an appointment that is already terminated.');
        }

        $wasPending = $this->status->isPending();

        if ($wasPending && CancellationActor::CLINIC === $actor) {
            $this->decline(DeclineReason::CLINIC_CANCELLED, $notifyClient);

            return;
        }

        $this->status             = AppointmentStatus::CANCELLED;
        $this->cancelledBy        = $actor;
        $this->cancellationReason = $reason;

        $this->recordDomainEvent(new AppointmentCancelled(
            appointmentId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            notifyClient: $notifyClient,
            cancelledBy: $actor->value,
            cancellationReason: $reason?->value,
            wasPending: $wasPending,
            source: $this->source->value,
            ownerId: $this->ownerId,
            animalId: $this->animalId,
            startsAtUtc: $this->timeSlot->startsAtUtc()->format(\DateTimeInterface::ATOM),
        ));
    }

    public function markNoShow(): void
    {
        if (AppointmentStatus::NO_SHOW === $this->status) {
            return;
        }

        if ($this->status->isPending()) {
            throw new AppointmentNotModifiableException('Un rendez-vous à confirmer ne peut pas être marqué « Non venu ».');
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

    /**
     * A pending request completed at the desk is confirmed first, silently:
     * the client is on site.
     */
    public function complete(): void
    {
        if (AppointmentStatus::COMPLETED === $this->status) {
            return;
        }

        if ($this->status->isTerminal()) {
            throw new \DomainException('Cannot complete an appointment that is already terminated.');
        }

        if ($this->status->isPending()) {
            $this->confirm(silent: true);
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

        // The client is on site: a pending request is confirmed silently
        if ($this->status->isPending()) {
            $this->confirm(silent: true);
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

    public function linkedAdmissionId(): ?string
    {
        return $this->linkedAdmissionId;
    }

    public function ownerId(): ?string
    {
        return $this->ownerId;
    }

    public function animalId(): ?string
    {
        return $this->animalId;
    }

    public function practitionerAssignee(): PractitionerAssignee
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

    public function source(): AppointmentSource
    {
        return $this->source;
    }

    public function bookingMotifId(): ?string
    {
        return $this->bookingMotifId;
    }

    public function cancelledBy(): ?CancellationActor
    {
        return $this->cancelledBy;
    }

    public function cancellationReason(): ?CancellationReason
    {
        return $this->cancellationReason;
    }

    public function pendingExpiresAt(): ?\DateTimeImmutable
    {
        return $this->pendingExpiresAt;
    }

    public function confirmedSilently(): bool
    {
        return $this->confirmedSilently;
    }

    /**
     * The animal registered on the Portal for this booking (with-new-pet),
     * archived when the request is declined or expires unused.
     */
    public function createdPetId(): ?string
    {
        return $this->createdPetId;
    }
}
