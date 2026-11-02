<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;

final class InMemoryAppointments implements AppointmentRepositoryInterface
{
    /** @var list<string> appointment ids read for update, in order */
    public array $lockedForUpdate = [];
    /** @var array<string, Appointment> */
    private array $appointments = [];

    public function save(Appointment $appointment): void
    {
        $this->appointments[$appointment->id()->toString()] = $appointment;
    }

    public function findById(AppointmentId $id): ?Appointment
    {
        return $this->appointments[$id->toString()] ?? null;
    }

    public function findByIdForUpdate(AppointmentId $id): ?Appointment
    {
        $this->lockedForUpdate[] = $id->toString();

        return $this->findById($id);
    }

    public function expiredPendingIds(\DateTimeImmutable $now): array
    {
        $expired = array_filter(
            $this->appointments,
            static fn (Appointment $appointment): bool => $appointment->status()->isPending() && null !== $appointment->pendingExpiresAt() && $appointment->pendingExpiresAt() <= $now,
        );

        return array_values(array_map(static fn (Appointment $appointment): string => $appointment->id()->toString(), $expired));
    }

    public function findRecentOnlineBookingId(
        ClinicId $clinicId,
        string $ownerId,
        string $animalId,
        string $bookingMotifId,
        string $practitionerUserId,
        \DateTimeImmutable $startsAtUtc,
        \DateTimeImmutable $createdSince,
    ): ?AppointmentId {
        foreach ($this->appointments as $appointment) {
            $same = $appointment->clinicId()->equals($clinicId)
                && $appointment->ownerId() === $ownerId
                && $appointment->animalId() === $animalId
                && $appointment->bookingMotifId() === $bookingMotifId
                && $appointment->practitionerAssignee()->userId()->toString() === $practitionerUserId
                && $appointment->timeSlot()->startsAtUtc()->getTimestamp() === $startsAtUtc->getTimestamp();

            if ($same && $appointment->createdAt() >= $createdSince && $appointment->status()->occupiesSlot()) {
                return $appointment->id();
            }
        }

        return null;
    }

    public function hasOtherAppointmentForAnimal(ClinicId $clinicId, string $animalId, AppointmentId $except): bool
    {
        foreach ($this->appointments as $appointment) {
            $other = $appointment->clinicId()->equals($clinicId) && $appointment->animalId() === $animalId && !$appointment->id()->equals($except);

            if ($other && !$appointment->status()->isWithdrawn()) {
                return true;
            }
        }

        return false;
    }
}
