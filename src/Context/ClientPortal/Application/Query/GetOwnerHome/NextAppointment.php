<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerHome;

/** The next appointment of an alive pet, never its free-text reason or notes. */
final readonly class NextAppointment
{
    /**
     * @param \DateTimeImmutable $startAt      in the clinic's time zone
     * @param ?string            $practitioner the practitioner's display name, when known
     * @param string             $motifLabel   a fixed French label of the appointment category
     */
    public function __construct(
        public string $id,
        public \DateTimeImmutable $startAt,
        public string $petId,
        public string $petName,
        public ?string $practitioner,
        public string $motifLabel,
    ) {
    }
}
