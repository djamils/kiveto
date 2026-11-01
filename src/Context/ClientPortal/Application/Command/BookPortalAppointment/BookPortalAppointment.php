<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\BookPortalAppointment;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The owner books one of their pets on an offered slot. Answers the
 * appointment id.
 */
final readonly class BookPortalAppointment implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
        public string $petId,
        public string $motifId,
        public string $practitionerId,
        public \DateTimeImmutable $startsAt,
    ) {
    }
}
