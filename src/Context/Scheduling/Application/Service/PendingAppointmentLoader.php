<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Service;

use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\PractitionerDayLockInterface;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\Service\LockKeys;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;

/**
 * Loads an appointment to confirm or decline, the way every agenda write
 * does (S4d): a plain read gives the days its slot touches, the
 * practitioner-day lock is taken on them, then the row is read again
 * `FOR UPDATE` — a current read, so a concurrent confirm, decline or expiry
 * committed meanwhile is seen and exactly one of them wins (AC 144).
 */
final readonly class PendingAppointmentLoader
{
    public function __construct(
        private AppointmentRepositoryInterface $appointments,
        private ClinicTimezoneResolverInterface $timezoneResolver,
        private PractitionerDayLockInterface $dayLock,
    ) {
    }

    /**
     * Null when the appointment does not exist, or belongs to another clinic
     * than $clinicId (when given).
     */
    public function lockAndLoad(AppointmentId $appointmentId, ?ClinicId $clinicId = null): ?Appointment
    {
        $appointment = $this->appointments->findById($appointmentId);

        if (null === $appointment || (null !== $clinicId && !$appointment->clinicId()->equals($clinicId))) {
            return null;
        }

        return $this->lockAndReload($appointment);
    }

    /**
     * Takes the practitioner-day lock on the days of an appointment read
     * plainly, then reads it again `FOR UPDATE` (current). Null when it is
     * gone meanwhile.
     */
    public function lockAndReload(Appointment $appointment): ?Appointment
    {
        $slot = $appointment->timeSlot();

        $this->dayLock->lock($appointment->clinicId(), LockKeys::forSlot(
            $appointment->practitionerAssignee()->userId(),
            $slot->startsAtUtc(),
            $slot->endsAtUtc(),
            $this->timezoneResolver->resolveTimezone($appointment->clinicId()),
        ));

        return $this->appointments->findByIdForUpdate($appointment->id());
    }
}
