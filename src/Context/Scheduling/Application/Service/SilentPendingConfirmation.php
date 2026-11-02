<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Service;

use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;

/**
 * The one path of a silent confirmation (the client is on site: check-in,
 * consultation started, appointment honoured). A pending request is
 * confirmed under the practitioner-day lock and its row lock, like a
 * « Confirmer » click, so a concurrent decline, clinic cancel or expiry and
 * this confirmation never both win; `AppointmentConfirmed{silent: true}` is
 * recorded (no message, the notification state marks the slot as told, the
 * integration bridge announces a portal booking confirmed). Anything else
 * is returned as read, unlocked and unchanged; the caller saves and
 * publishes.
 */
final readonly class SilentPendingConfirmation
{
    public function __construct(
        private AppointmentRepositoryInterface $appointments,
        private PendingAppointmentLoader $loader,
    ) {
    }

    /**
     * Null when the appointment does not exist, or belongs to another clinic
     * than $clinicId (when given).
     */
    public function load(AppointmentId $appointmentId, ?ClinicId $clinicId = null): ?Appointment
    {
        $appointment = $this->appointments->findById($appointmentId);

        if (null === $appointment || (null !== $clinicId && !$appointment->clinicId()->equals($clinicId))) {
            return null;
        }

        if (!$appointment->status()->isPending()) {
            return $appointment;
        }

        $current = $this->loader->lockAndReload($appointment);

        // Declined, withdrawn or expired meanwhile: left as the winner made it
        if (null !== $current && $current->status()->isPending()) {
            $current->confirm(silent: true);
        }

        return $current;
    }
}
