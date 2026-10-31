<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListPetTimeline;

/** A visit whose report was shared with the owner account. */
final readonly class TimelineEntry
{
    /**
     * @param string                 $visitId   the consultation
     * @param \DateTimeImmutable     $date      the consultation start, in the clinic's time zone
     * @param ?string                $vet       the practitioner snapshotted when the report was shared
     * @param list<TimelineDocument> $documents the visit's documents the account may see
     */
    public function __construct(
        public string $visitId,
        public \DateTimeImmutable $date,
        public ?string $vet,
        public string $title,
        public array $documents,
    ) {
    }
}
