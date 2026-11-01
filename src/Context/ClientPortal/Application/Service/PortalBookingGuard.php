<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\OwnerAppointmentsInterface;
use App\Context\ClientPortal\Application\Port\PortalPet;
use App\Context\ClientPortal\Domain\Exception\AppointmentNotFoundException;
use App\Context\ClientPortal\Domain\Exception\PetNotBookableException;
use App\Context\ClientPortal\Domain\Exception\PetNotFoundException;

/**
 * What an owner account may book or change (S4b-F4): a pet it sees (active,
 * not transferred, of one of its confirmed-linked clients) that is alive,
 * and appointments of its client files or of the pets it sees.
 */
final readonly class PortalBookingGuard
{
    public function __construct(
        private OwnerPets $pets,
        private OwnerAppointmentsInterface $appointments,
    ) {
    }

    /**
     * @throws PetNotFoundException    when the account does not see the pet
     * @throws PetNotBookableException when it is deceased or missing
     */
    public function bookablePet(string $userId, string $clinicId, string $petId): PortalPet
    {
        $pet = $this->pets->get($userId, $clinicId, $petId);

        if (!$pet->isAlive()) {
            throw new PetNotBookableException($petId, $pet->lifeStatus);
        }

        return $pet;
    }

    /**
     * @throws AppointmentNotFoundException when the appointment is not one of the account's
     */
    public function assertOwnsAppointment(string $userId, string $clinicId, string $appointmentId): void
    {
        $appointment = $this->appointments->find($clinicId, $appointmentId);
        $owned       = null !== $appointment
            && PortalAppointments::belongsTo($appointment, $this->pets->clientIds($userId, $clinicId), $this->pets->visible($userId, $clinicId));

        if (!$owned) {
            throw new AppointmentNotFoundException($appointmentId);
        }
    }
}
