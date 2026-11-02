<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\BookPortalAppointmentWithNewPet;

use App\Context\ClientPortal\Application\Port\OnlineBookingInterface;
use App\Context\ClientPortal\Application\Port\PetRegistrationInterface;
use App\Context\ClientPortal\Application\Service\OwnerPets;
use App\Context\ClientPortal\Domain\Booking\BookedPetMarkerRepositoryInterface;
use App\Context\ClientPortal\Domain\Exception\ClinicNotLinkedException;
use App\Context\ClientPortal\Domain\Exception\InvalidNewPetException;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * S4g, D1: the animal is registered for the client of the account's
 * earliest confirmed link in the clinic, then booked, both inside this
 * command's transaction — a refused booking (`slot_taken`…) leaves no
 * animal. A retry of the same request (same account and Idempotency-Key)
 * within 24 h reuses the animal its first attempt registered, found by the
 * request's marker, while it is still active, alive, not transferred, of
 * that owner, same name and species (S4B-01): an animal the clinic created
 * is never reused, so never archived. The appointment remembers the animal
 * this request registered, archived if the request is declined or expires.
 *
 * Refusals: `InvalidNewPetException`, `ClinicNotLinkedException`, then
 * Scheduling's.
 */
#[AsMessageHandler]
final readonly class BookPortalAppointmentWithNewPetHandler
{
    public const array SPECIES         = ['dog', 'cat', 'nac', 'other'];
    public const int   NAME_MAX_LENGTH = 80;

    /** How long a registered animal is reused by a retry. */
    private const string REUSE_WINDOW = 'PT24H';

    public function __construct(
        private OwnerPets $pets,
        private PetRegistrationInterface $registration,
        private OnlineBookingInterface $booking,
        private BookedPetMarkerRepositoryInterface $markers,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(BookPortalAppointmentWithNewPet $command): string
    {
        $name = trim($command->petName);

        if ('' === $name || mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw InvalidNewPetException::name(self::NAME_MAX_LENGTH);
        }

        if (!\in_array($command->petSpecies, self::SPECIES, true)) {
            throw InvalidNewPetException::species();
        }

        // Oldest confirmed link first
        $ownerId = $this->pets->clientIds($command->userId, $command->clinicId)[0] ?? throw new ClinicNotLinkedException($command->clinicId);
        $now     = $this->clock->now();
        $since   = $now->sub(new \DateInterval(self::REUSE_WINDOW));
        $marker  = null === $command->requestKey ? null : hash('sha256', $command->userId . "\n" . $command->requestKey);
        $known   = null === $marker ? null : $this->markers->animalFor($marker, $since);
        $petId   = null === $known ? null : $this->registration->recentPetOf($command->clinicId, $known, $ownerId, $name, $command->petSpecies, $since);

        if (null === $petId) {
            $petId = $this->registration->register($command->clinicId, $ownerId, $name, $command->petSpecies);

            if (null !== $marker) {
                $this->markers->remember($marker, $petId, $now, $since);
            }
        }

        return $this->booking->book(
            $command->clinicId,
            $command->motifId,
            $command->practitionerId,
            $command->startsAt,
            $ownerId,
            $petId,
            $petId,
        );
    }
}
