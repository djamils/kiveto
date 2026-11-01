<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * An appointment as Scheduling describes it to its owner: its Portal
 * status already mapped, never its free-text reason or notes.
 */
final readonly class OwnerAppointment
{
    /**
     * @param string              $status             confirmed|pending_clinic_confirmation|cancelled_by_client|cancelled_by_clinic|declined|completed|no_show
     * @param string              $source             clinic|portal
     * @param ?string             $motifId            null when taken without online motif
     * @param ?string             $preventiveCareKind the motif's care kind
     * @param ?\DateTimeImmutable $cancellableUntil   UTC; null once the slot is no longer held or the start is past
     */
    public function __construct(
        public string $id,
        public ?string $ownerId,
        public ?string $animalId,
        public string $practitionerUserId,
        public \DateTimeImmutable $startsAtUtc,
        public int $durationMinutes,
        public string $status,
        public bool $occupiesSlot,
        public bool $isPending,
        public string $source,
        public ?string $motifId,
        public ?string $motifLabel,
        public ?string $preventiveCareKind,
        public \DateTimeImmutable $createdAtUtc,
        public ?\DateTimeImmutable $cancellableUntil,
    ) {
    }
}
