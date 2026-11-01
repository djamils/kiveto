<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;

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
}
