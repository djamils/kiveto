<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListAppointmentsForOwners;

use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Shared\Infrastructure\Persistence\RowAccessor;

/**
 * An appointment as its owner sees it on the Portal: never its free-text
 * reason nor its notes, only the booking motif it was taken for.
 */
final readonly class PortalAppointmentItem
{
    /**
     * The columns read, `a` being the appointment and `m` its booking motif
     * (left joined). Never `reason` nor `notes`.
     */
    public const string COLUMNS = <<<'SQL'
        BIN_TO_UUID(a.id) AS id,
        BIN_TO_UUID(a.owner_id) AS owner_id,
        BIN_TO_UUID(a.animal_id) AS animal_id,
        BIN_TO_UUID(a.practitioner_user_id) AS practitioner_user_id,
        a.starts_at_utc,
        a.duration_minutes,
        a.status,
        a.source,
        a.cancelled_by,
        a.created_at_utc,
        BIN_TO_UUID(m.id) AS motif_id,
        m.label AS motif_label,
        m.preventive_care_kind
        SQL;

    /** The booking motif of the appointment, from Scheduling's own table. */
    public const string MOTIF_JOIN = 'LEFT JOIN scheduling__booking_motifs m ON m.id = a.booking_motif_id AND m.clinic_id = a.clinic_id';

    /**
     * @param string              $status             a PortalAppointmentStatus value
     * @param string              $source             clinic|portal
     * @param ?string             $cancelledBy        clinic|client
     * @param ?string             $motifId            null for an appointment taken without online motif
     * @param ?string             $preventiveCareKind the motif's vaccination|internal_parasite|external_parasite|other
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
        public ?string $cancelledBy,
        public ?string $motifId,
        public ?string $motifLabel,
        public ?string $preventiveCareKind,
        public \DateTimeImmutable $createdAtUtc,
        public ?\DateTimeImmutable $cancellableUntil,
    ) {
    }

    /**
     * @param array<string, mixed> $row the COLUMNS of one appointment
     */
    public static function fromRow(array $row, OnlineBookingSettings $settings, \DateTimeImmutable $nowUtc): self
    {
        $utc         = new \DateTimeZone('UTC');
        $status      = AppointmentStatus::from(RowAccessor::string($row, 'status'));
        $cancelledBy = CancellationActor::tryFrom((string) RowAccessor::nullableString($row, 'cancelled_by'));
        $startsAt    = new \DateTimeImmutable(RowAccessor::string($row, 'starts_at_utc'), $utc);
        $createdAt   = new \DateTimeImmutable(RowAccessor::string($row, 'created_at_utc'), $utc);
        $open        = $status->occupiesSlot() && $nowUtc < $startsAt;

        return new self(
            id: RowAccessor::string($row, 'id'),
            ownerId: RowAccessor::nullableString($row, 'owner_id'),
            animalId: RowAccessor::nullableString($row, 'animal_id'),
            practitionerUserId: RowAccessor::string($row, 'practitioner_user_id'),
            startsAtUtc: $startsAt,
            durationMinutes: RowAccessor::int($row, 'duration_minutes'),
            status: PortalAppointmentStatus::of($status, $cancelledBy)->value,
            occupiesSlot: $status->occupiesSlot(),
            isPending: $status->isPending(),
            source: RowAccessor::string($row, 'source'),
            cancelledBy: $cancelledBy?->value,
            motifId: RowAccessor::nullableString($row, 'motif_id'),
            motifLabel: RowAccessor::nullableString($row, 'motif_label'),
            preventiveCareKind: RowAccessor::nullableString($row, 'preventive_care_kind'),
            createdAtUtc: $createdAt,
            cancellableUntil: $open ? $settings->cancellableUntil($startsAt, $createdAt) : null,
        );
    }
}
