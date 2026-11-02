<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\ConsultationStartsInterface;
use App\Context\ClientPortal\Application\Port\OwnerAppointment;
use App\Context\ClientPortal\Application\Port\OwnerAppointmentsInterface;
use App\Context\ClientPortal\Domain\Exception\AppointmentNotFoundException;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaire;
use App\Shared\Domain\Time\ClockInterface;

/**
 * Who may answer a pre-visit questionnaire, and until when (S6b): an
 * appointment of the account's client files or of the pets it sees, not
 * cancelled nor declined (else not found); editable while it holds its slot,
 * has not started and no consultation began from it — and the questionnaire
 * was never locked.
 */
final readonly class QuestionnaireAccess
{
    /** Statuses whose questionnaire is not found: the visit does not take place. */
    private const array GONE = ['cancelled_by_client', 'cancelled_by_clinic', 'declined'];

    public function __construct(
        private OwnerPets $pets,
        private OwnerAppointmentsInterface $appointments,
        private ConsultationStartsInterface $consultations,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws AppointmentNotFoundException when not the account's, cancelled or declined
     */
    public function ownedAppointment(string $userId, string $clinicId, string $appointmentId): OwnedAppointment
    {
        $appointment = $this->appointments->find($clinicId, $appointmentId);
        $clientIds   = $this->pets->clientIds($userId, $clinicId);

        if (null === $appointment || \in_array($appointment->status, self::GONE, true)) {
            throw new AppointmentNotFoundException($appointmentId);
        }

        if (\in_array($appointment->ownerId, $clientIds, true)) {
            return new OwnedAppointment($appointment, (string) $appointment->ownerId);
        }

        foreach ($this->pets->visible($userId, $clinicId) as $pet) {
            if ($pet->id === $appointment->animalId) {
                return new OwnedAppointment($appointment, $pet->primaryOwnerClientId);
            }
        }

        throw new AppointmentNotFoundException($appointmentId);
    }

    /** The appointment of the clinic, whoever it belongs to (clinic side), or null. */
    public function appointment(string $clinicId, string $appointmentId): ?OwnerAppointment
    {
        return $this->appointments->find($clinicId, $appointmentId);
    }

    /** Whether the owner can still write the questionnaire of the appointment. */
    public function isOpen(string $clinicId, OwnerAppointment $appointment, ?PreVisitQuestionnaire $questionnaire): bool
    {
        if (null !== $questionnaire && !$questionnaire->isEditable()) {
            return false;
        }

        return $appointment->occupiesSlot && !$this->isFrozen($clinicId, $appointment);
    }

    /**
     * Whether the appointment froze its questionnaire for good: it started,
     * or a consultation began from it. The lock is then recorded, so a later
     * reschedule never reopens it.
     */
    public function isFrozen(string $clinicId, OwnerAppointment $appointment): bool
    {
        return $appointment->startsAtUtc <= $this->clock->now()
            || [] !== $this->consultations->startedFrom($clinicId, [$appointment->id]);
    }

    /**
     * The appointments of the clinic, among those listed, that froze their
     * questionnaire (one consultation lookup for the clinic).
     *
     * @param list<OwnerAppointment> $appointments
     *
     * @return list<string> their ids
     */
    public function frozen(string $clinicId, array $appointments): array
    {
        $now      = $this->clock->now();
        $frozen   = [];
        $upcoming = [];

        foreach ($appointments as $appointment) {
            if ($appointment->startsAtUtc <= $now) {
                $frozen[] = $appointment->id;
            } else {
                $upcoming[] = $appointment->id;
            }
        }

        return [] === $upcoming ? $frozen : [...$frozen, ...$this->consultations->startedFrom($clinicId, $upcoming)];
    }
}
