<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListBookablePractitioners;

use App\Context\Scheduling\Application\Port\ClinicDirectoryInterface;
use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingMotifRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * A practitioner who left the clinic (or changed role) since the motif was
 * saved is left out until the clinic edits the motif.
 */
#[AsMessageHandler]
final readonly class ListBookablePractitionersHandler
{
    private const array PRACTITIONER_ROLES = ['VETERINARY', 'VETERINARY_ASSISTANT'];

    public function __construct(
        private BookingMotifRepositoryInterface $motifs,
        private MembershipEligibilityCheckerInterface $eligibility,
        private ClinicDirectoryInterface $clinics,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return ?list<BookablePractitioner>
     */
    public function __invoke(ListBookablePractitioners $query): ?array
    {
        if (!Uuid::isValid($query->motifId)) {
            return null;
        }

        $clinicId = ClinicId::fromString($query->clinicId);
        $motif    = $this->motifs->findInClinic(BookingMotifId::fromString($query->motifId), $clinicId);

        if (null === $motif || $motif->isArchived() || !$motif->isOnlineEnabled() || ($query->newClient && !$motif->newClientsAllowed())) {
            return null;
        }

        $now           = $this->clock->now();
        $practitioners = [];

        foreach ($motif->practitionerIds() as $userId) {
            if ($this->eligibility->isUserEligibleForClinicAt($userId, $clinicId, $now, self::PRACTITIONER_ROLES)) {
                $practitioners[] = new BookablePractitioner($userId->toString(), $this->clinics->printedName($query->clinicId, $userId->toString()));
            }
        }

        usort($practitioners, static fn (BookablePractitioner $a, BookablePractitioner $b): int => [null === $a->displayName, $a->displayName, $a->id] <=> [null === $b->displayName, $b->displayName, $b->id]);

        return $practitioners;
    }
}
