<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\BookPortalAppointmentWithNewPet;

use App\Context\ClientPortal\Application\Port\OnlineBookingInterface;
use App\Context\ClientPortal\Application\Port\PetRegistrationInterface;
use App\Context\ClientPortal\Application\Service\OwnerPets;
use App\Context\ClientPortal\Domain\Exception\ClinicNotLinkedException;
use App\Context\ClientPortal\Domain\Exception\InvalidNewPetException;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * S4g, D1: the animal is registered for the client of the account's
 * earliest confirmed link in the clinic, then booked, both inside this
 * command's transaction — a refused booking (`slot_taken`…) leaves no
 * animal. A retry within 24 h reuses the animal registered by the first
 * attempt (same owner, name regardless of case, species). The appointment
 * remembers the animal, archived if the request is declined or expires.
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
        $since   = $this->clock->now()->sub(new \DateInterval(self::REUSE_WINDOW));
        $petId   = $this->registration->recentPetOf($command->clinicId, $ownerId, $name, $command->petSpecies, $since)
            ?? $this->registration->register($command->clinicId, $ownerId, $name, $command->petSpecies);

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
