<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\BookPortalAppointmentWithNewPet;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The owner books for an animal the clinic does not know yet (D1): it is
 * registered, then booked, in one transaction. Answers the appointment id.
 * `requestKey` is the request's Idempotency-Key: a retry of that very
 * request reuses the animal it registered (S4B-01).
 */
final readonly class BookPortalAppointmentWithNewPet implements CommandInterface
{
    /**
     * @param string $petSpecies dog|cat|nac|other
     */
    public function __construct(
        public string $userId,
        public string $clinicId,
        public string $petName,
        public string $petSpecies,
        public string $motifId,
        public string $practitionerId,
        public \DateTimeImmutable $startsAt,
        public ?string $requestKey = null,
    ) {
    }
}
