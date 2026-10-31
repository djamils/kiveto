<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Context\ClientPortal\Application\Query\ListPetTimeline\TimelineDocument;
use App\Context\ClientPortal\Application\Query\ListPetTimeline\TimelineEntry;
use App\Presentation\Api\Portal\Support\ApiDate;

/** A visit whose report was shared with the caller. */
final readonly class TimelineEntryView
{
    /**
     * @param list<TimelineDocumentView> $documents
     */
    public function __construct(
        public string $visitId,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $date,
        public ?string $vet,
        public string $title,
        public array $documents,
    ) {
    }

    public static function of(TimelineEntry $entry): self
    {
        return new self(
            $entry->visitId,
            ApiDate::formatLocal($entry->date),
            $entry->vet,
            $entry->title,
            array_map(static fn (TimelineDocument $document): TimelineDocumentView => new TimelineDocumentView($document->id, $document->type), $entry->documents),
        );
    }
}
