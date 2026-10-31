<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListPublishedDocuments;

use App\Context\ClientPortal\Application\Port\ClinicNamesInterface;
use App\Context\ClientPortal\Application\Port\DocumentPosition;
use App\Context\ClientPortal\Application\Port\InvoiceDocumentsInterface;
use App\Context\ClientPortal\Application\Port\ListedDocument;
use App\Context\ClientPortal\Application\Port\PrescriptionDocumentsInterface;
use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\CursorPage;
use App\Shared\Application\Pagination\InvalidCursorException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * The payment status of an invoice and the state of a prescription are
 * read live, one batch per page. A row whose source is no longer listable
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
        private ClinicNamesInterface $clinics,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return CursorPage<PortalDocument>
     */
    public function __invoke(ListPublishedDocuments $query): CursorPage
    {
        $limit = Cursor::clampLimit($query->limit);
        $after = null === $query->cursor ? null : self::position($query->cursor);
        $rows  = $this->documents->listable($query->userId, $query->clinicId, $query->type, $query->petId, $after, $limit + 1);

        $next = null;
        if (\count($rows) > $limit) {
            $rows = \array_slice($rows, 0, $limit);
            $last = $rows[$limit - 1];
            $next = Cursor::fromPosition(['at' => $last->issuedAt->format('Y-m-d H:i:s'), 'id' => $last->id])->encode();
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
        $prescriptions = $this->prescriptions->states($clinicId, self::sources($rows, DocumentType::PRESCRIPTION));
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

                $prescription = new PortalPrescriptionSummary($row->sourceRef, $state->validUntil, $state->renewable);
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

    private static function position(string $cursor): DocumentPosition
    {
        $position = Cursor::decode($cursor)->position();
        $at       = $position['at'] ?? null;
        $id       = $position['id'] ?? null;

        if (!\is_string($at) || !\is_string($id) || !Uuid::isValid($id)) {
            throw new InvalidCursorException();
        }

        $issuedAt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $at, new \DateTimeZone('UTC'));

        if (false === $issuedAt || $issuedAt->format('Y-m-d H:i:s') !== $at) {
            throw new InvalidCursorException();
        }

        return new DocumentPosition($issuedAt, $id);
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
