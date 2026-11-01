<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class AppointmentCancelled extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'scheduling';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $appointmentId,
        private string $clinicId,
        // false when the client must not be told (death of the animal, D3)
        private bool $notifyClient = true,
        // 'clinic' or 'client'
        private string $cancelledBy = 'clinic',
        private ?string $cancellationReason = null,
        // A client withdrew their own pending request
        private bool $wasPending = false,
        private string $source = 'clinic',
        private ?string $ownerId = null,
        private ?string $animalId = null,
        private ?string $startsAtUtc = null,
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

    public function notifyClient(): bool
    {
        return $this->notifyClient;
    }

    public function cancelledBy(): string
    {
        return $this->cancelledBy;
    }

    public function cancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function wasPending(): bool
    {
        return $this->wasPending;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function ownerId(): ?string
    {
        return $this->ownerId;
    }

    public function animalId(): ?string
    {
        return $this->animalId;
    }

    public function startsAtUtc(): ?string
    {
        return $this->startsAtUtc;
    }

    public function payload(): array
    {
        return [
            'appointmentId'      => $this->appointmentId,
            'clinicId'           => $this->clinicId,
            'notifyClient'       => $this->notifyClient,
            'cancelledBy'        => $this->cancelledBy,
            'cancellationReason' => $this->cancellationReason,
            'wasPending'         => $this->wasPending,
            'source'             => $this->source,
            'ownerId'            => $this->ownerId,
            'animalId'           => $this->animalId,
            'startsAtUtc'        => $this->startsAtUtc,
        ];
    }
}
