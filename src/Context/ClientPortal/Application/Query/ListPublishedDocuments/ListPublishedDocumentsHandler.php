<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListPublishedDocuments;

use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\InvoiceDocumentsInterface;
use App\Context\ClientPortal\Application\Port\ListedDocument;
use App\Context\ClientPortal\Application\Port\PrescriptionDocumentsInterface;
use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;
use App\Context\ClientPortal\Application\Service\DocumentCursor;
use App\Context\ClientPortal\Application\Service\RenewalStatuses;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\CursorPage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The payment status of an invoice and the state of a prescription are
 * read live, one batch per page, as is the latest renewal request of each
 * prescription (`renewalStatus`). A row whose source is no longer listable
 * (cancelled before its withdrawal was consumed) is left out with a
 * warning, so a page can hold fewer items than the limit.
 */
#[AsMessageHandler]
final readonly class ListPublishedDocumentsHandler
{
    public function __construct(
        private PublishedDocumentReadModelInterface $documents,
        private InvoiceDocumentsInterface $invoices,
        private PrescriptionDocumentsInterface $prescriptions,
        private ClinicProfileInterface $clinics,
        private RenewalStatuses $renewals,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return CursorPage<PortalDocument>
     */
    public function __invoke(ListPublishedDocuments $query): CursorPage
    {
        $limit = Cursor::clampLimit($query->limit);
        $after = null === $query->cursor ? null : DocumentCursor::position($query->cursor);
        $rows  = $this->documents->listable($query->userId, $query->clinicId, $query->type, $query->petId, $after, $limit + 1);

        $next = null;
        if (\count($rows) > $limit) {
            $rows = \array_slice($rows, 0, $limit);
            $next = DocumentCursor::after($rows[$limit - 1]);
        }

        return new CursorPage($this->documents($query->clinicId, $rows), $next);
    }

    /**
     * Shared with the home's latest documents: the same live states and exclusions.
     *
     * @param list<ListedDocument> $rows
     *
     * @return list<PortalDocument>
     */
    public function documents(string $clinicId, array $rows): array
    {
        $timeZone      = new \DateTimeZone($this->clinics->clinicOf($clinicId)->timeZone);
        $invoices      = $this->invoices->states($clinicId, self::sources($rows, DocumentType::INVOICE));
        $prescribed    = self::sources($rows, DocumentType::PRESCRIPTION);
        $prescriptions = $this->prescriptions->states($clinicId, $prescribed);
        $renewals      = [] === $prescribed ? [] : $this->renewals->of($clinicId, $prescribed);
        $documents     = [];

        foreach ($rows as $row) {
            $invoice      = null;
            $prescription = null;

            if (DocumentType::INVOICE->value === $row->type) {
                $state = $invoices[$row->sourceRef] ?? null;

                if (null === $state?->paymentStatus) {
                    $this->skip($clinicId, $row);
                    continue;
                }

                $invoice = new PortalInvoiceSummary($state->totalMinorUnits, $state->currency, $state->paymentStatus);
            }

            if (DocumentType::PRESCRIPTION->value === $row->type) {
                $state = $prescriptions[$row->sourceRef] ?? null;

                if (null === $state || !$state->issued) {
                    $this->skip($clinicId, $row);
                    continue;
                }

                $prescription = new PortalPrescriptionSummary($row->sourceRef, $state->validUntil, $state->renewable, $renewals[$row->sourceRef] ?? null);
            }

            $documents[] = new PortalDocument(
                $row->id,
                $row->type,
                $row->title,
                $row->petIds,
                $row->issuedAt->setTimezone($timeZone),
                $row->unread,
                $invoice,
                $prescription,
            );
        }

        return $documents;
    }

    /**
     * @param list<ListedDocument> $rows
     *
     * @return list<string>
     */
    private static function sources(array $rows, DocumentType $type): array
    {
        return array_values(array_map(
            static fn (ListedDocument $row): string => $row->sourceRef,
            array_filter($rows, static fn (ListedDocument $row): bool => $type->value === $row->type),
        ));
    }

    private function skip(string $clinicId, ListedDocument $row): void
    {
        $this->logger->warning('A published document whose source is no longer listable was left out.', [
            'clinicId'   => $clinicId,
            'documentId' => $row->id,
            'type'       => $row->type,
        ]);
    }
}
