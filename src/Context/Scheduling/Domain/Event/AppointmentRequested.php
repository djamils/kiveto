<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/**
 * An online booking waiting for the clinic's confirmation. Same payload as
 * AppointmentScheduled, plus the booking motif and the source.
 */
readonly class AppointmentRequested extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'scheduling';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $appointmentId,
        private string $clinicId,
        private ?string $linkedAdmissionId,
        private string $practitionerUserId,
        private string $startsAtUtc,
        private int $durationMinutes,
        private ?string $reason,
        private ?string $notes,
        private ?string $ownerId,
        private ?string $animalId,
        private ?string $bookingMotifId,
        private string $source,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->appointmentId;
    }

    public function appointmentId(): string
    {
        return $this->appointmentId;
    }

    public function clinicId(): string
    {
        return $this->clinicId;
    }

    public function practitionerUserId(): string
    {
        return $this->practitionerUserId;
    }

    public function startsAtUtc(): string
    {
        return $this->startsAtUtc;
    }

    public function ownerId(): ?string
    {
        return $this->ownerId;
    }

    public function animalId(): ?string
    {
        return $this->animalId;
    }

    public function bookingMotifId(): ?string
    {
        return $this->bookingMotifId;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function payload(): array
    {
        return [
            'appointmentId'      => $this->appointmentId,
            'clinicId'           => $this->clinicId,
            'linkedAdmissionId'  => $this->linkedAdmissionId,
            'practitionerUserId' => $this->practitionerUserId,
            'startsAtUtc'        => $this->startsAtUtc,
            'durationMinutes'    => $this->durationMinutes,
            'reason'             => $this->reason,
            'notes'              => $this->notes,
            'ownerId'            => $this->ownerId,
            'animalId'           => $this->animalId,
            'bookingMotifId'     => $this->bookingMotifId,
            'source'             => $this->source,
        ];
    }
}
