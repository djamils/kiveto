<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/**
 * A pending appointment confirmed by the clinic. `silent` when confirmed
 * implicitly because the client is on site (check-in, service started): no
 * confirmation message is due.
 */
readonly class AppointmentConfirmed extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'scheduling';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $appointmentId,
        private string $clinicId,
        private string $practitionerUserId,
        private string $startsAtUtc,
        private ?string $ownerId,
        private ?string $animalId,
        private string $source,
        private bool $silent,
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

    public function source(): string
    {
        return $this->source;
    }

    public function silent(): bool
    {
        return $this->silent;
    }

    public function payload(): array
    {
        return [
            'appointmentId'      => $this->appointmentId,
            'clinicId'           => $this->clinicId,
            'practitionerUserId' => $this->practitionerUserId,
            'startsAtUtc'        => $this->startsAtUtc,
            'ownerId'            => $this->ownerId,
            'animalId'           => $this->animalId,
            'source'             => $this->source,
            'silent'             => $this->silent,
        ];
    }
}
