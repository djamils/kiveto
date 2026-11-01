<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\BookOnlineAppointment;

use App\Shared\Application\Bus\CommandInterface;

/**
 * A client books one of the slots offered online (Portal, HARD rule).
 * Answers the appointment id.
 *
 * New-client mode (`newClient`, slice 7): a booking through the clinic
 * directory by an owner the clinic does not know yet. No owner nor animal
 * (both null), always pending, with the manual lead time; the clinic must
 * take new clients online on that motif.
 */
final readonly class BookOnlineAppointment implements CommandInterface
{
    /**
     * @param \DateTimeImmutable $startsAt     any time zone; stored in UTC
     * @param ?string            $ownerId      the client the appointment is for (the pet's primary owner); null in new-client mode
     * @param ?string            $animalId     null in new-client mode
     * @param ?string            $createdPetId the animal registered for this booking (with-new-pet)
     */
    public function __construct(
        public string $clinicId,
        public string $bookingMotifId,
        public string $practitionerUserId,
        public \DateTimeImmutable $startsAt,
        public ?string $ownerId,
        public ?string $animalId,
        public ?string $createdPetId = null,
        public bool $newClient = false,
    ) {
    }
}
