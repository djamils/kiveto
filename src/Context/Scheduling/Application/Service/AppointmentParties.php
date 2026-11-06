<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Service;

use App\Context\Scheduling\Application\Port\AnimalDirectoryInterface;
use App\Context\Scheduling\Application\Port\ClientContactInterface;
use App\Context\Scheduling\Domain\Exception\AppointmentPartyNotFoundException;

/**
 * The client and the animal an appointment names are the clinic's own: an id
 * of another clinic would put its client's name and phone in this agenda.
 * Both ports answer null outside the clinic.
 */
final readonly class AppointmentParties
{
    public function __construct(
        private ClientContactInterface $clients,
        private AnimalDirectoryInterface $animals,
    ) {
    }

    /**
     * @throws AppointmentPartyNotFoundException
     */
    public function assertOfClinic(string $clinicId, ?string $ownerId, ?string $animalId): void
    {
        if (null !== $ownerId && null === $this->clients->contactOf($clinicId, $ownerId)) {
            throw AppointmentPartyNotFoundException::create();
        }

        if (null !== $animalId && null === $this->animals->nameOf($clinicId, $animalId)) {
            throw AppointmentPartyNotFoundException::create();
        }
    }
}
