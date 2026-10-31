<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListPetTimeline;

use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\ListedDocument;
use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;
use App\Context\ClientPortal\Application\Port\VisitSummariesInterface;
use App\Context\ClientPortal\Application\Service\DocumentCursor;
use App\Context\ClientPortal\Application\Service\OwnerPets;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\CursorPage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The timeline is the pet's listable report rows of the registry, scoped to
 * the account's confirmed clients (a re-homed pet shows each owner only the
 * reports addressed to them), paged on `(issued_at, id)`. Per page, one
 * Consultation call for the start and title of the visits, one registry
 * read for their documents.
 */
#[AsMessageHandler]
final readonly class ListPetTimelineHandler
{
    public function __construct(
        private OwnerPets $pets,
        private PublishedDocumentReadModelInterface $documents,
        private VisitSummariesInterface $visits,
        private ClinicProfileInterface $clinics,
    ) {
    }

    /**
     * @return CursorPage<TimelineEntry>
     */
    public function __invoke(ListPetTimeline $query): CursorPage
    {
        $pet   = $this->pets->get($query->userId, $query->clinicId, $query->petId);
        $limit = Cursor::clampLimit($query->limit);
        $after = null === $query->cursor ? null : DocumentCursor::position($query->cursor);
        $rows  = $this->documents->listable($query->userId, $query->clinicId, DocumentType::REPORT->value, $pet->id, $after, $limit + 1);

        $next = null;
        if (\count($rows) > $limit) {
            $rows = \array_slice($rows, 0, $limit);
            $next = DocumentCursor::after($rows[$limit - 1]);
        }

        $visitIds  = array_map(self::visitOf(...), $rows);
        $summaries = $this->visits->summariesOf($query->clinicId, $visitIds);
        $timeZone  = new \DateTimeZone($this->clinics->clinicOf($query->clinicId)->timeZone);
        $documents = [];

        foreach ($this->documents->listableOfVisits($query->userId, $query->clinicId, $visitIds) as $document) {
            $documents[$document->consultationId ?? ''][] = new TimelineDocument($document->id, $document->type);
        }

        $entries = [];

        foreach ($rows as $row) {
            $visitId = self::visitOf($row);
            // A consultation Consultation no longer answers for keeps the registry's own date and title.
            $summary = $summaries[$visitId] ?? null;

            $entries[] = new TimelineEntry(
                $visitId,
                ($summary->startedAtUtc ?? $row->issuedAt)->setTimezone($timeZone),
                $row->practitionerName,
                $summary->title ?? $row->title,
                $documents[$visitId] ?? [],
            );
        }

        return new CursorPage($entries, $next);
    }

    /** A report is published for its consultation (its source). */
    private static function visitOf(ListedDocument $report): string
    {
        return $report->consultationId ?? $report->sourceRef;
    }
}
