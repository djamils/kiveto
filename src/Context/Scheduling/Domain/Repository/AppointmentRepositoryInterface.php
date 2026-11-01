<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Repository;

use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;

interface AppointmentRepositoryInterface
{
    public function save(Appointment $appointment): void;

    public function findById(AppointmentId $id): ?Appointment;

    /**
     * The appointment as last committed, its row locked until the end of the
     * transaction (`SELECT … FOR UPDATE`, a current read).
     */
    public function findByIdForUpdate(AppointmentId $id): ?Appointment;

    /**
     * The pending requests whose expiry has passed (`pending_expires_at <= now`).
     *
     * @return list<string> appointment ids
     */
    public function expiredPendingIds(\DateTimeImmutable $now): array;

    /**
     * The online booking already made for that owner, animal, motif and
     * start, still holding its slot and created at or after `createdSince`
     * (natural idempotency of a retried booking). A current read
     * (`… FOR SHARE`): call it under the practitioner-day lock.
     */
    public function findRecentOnlineBookingId(
        ClinicId $clinicId,
        string $ownerId,
        string $animalId,
        string $bookingMotifId,
        \DateTimeImmutable $startsAtUtc,
        \DateTimeImmutable $createdSince,
    ): ?AppointmentId;

    /**
     * Whether the animal has an appointment of the clinic other than
     * `$except` that was not withdrawn (cancelled or declined).
     */
    public function hasOtherAppointmentForAnimal(ClinicId $clinicId, string $animalId, AppointmentId $except): bool;
}
