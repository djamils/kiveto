<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Service\DocumentPublisher;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Shared\Application\Event\DomainEventPublisher;

/**
 * The published documents side of the context, on in-memory doubles, at a
 * settable time (2026-10-29 09:00 UTC).
 */
final class DocumentWorld
{
    public const string CLINIC  = ClientPortalWorld::CLINIC;
    public const string CLIENT  = ClientPortalWorld::CLIENT;
    public const string CLIENT2 = ClientPortalWorld::CLIENT2;
    public const string USER    = ClientPortalFakes::USER;
    public const string DOC     = '01960000-0000-7000-8000-0000000f0001';
    public const string SOURCE  = '01960000-0000-7000-8000-0000000f1001';
    public const string PATIENT = '01960000-0000-7000-8000-0000000f2001';
    public const string ANIMAL  = '01960000-0000-7000-8000-0000000f3001';

    public InMemoryPublishedDocuments $documents;
    public InMemoryClientLinks $links;
    public DocumentSourceFakes $sources;
    public FakeInvoiceDocuments $invoices;
    public FakePrescriptionDocuments $prescriptions;
    public ClientPortalFakes $fakes;
    public SettableClock $clock;

    public function __construct()
    {
        $this->documents     = new InMemoryPublishedDocuments();
        $this->links         = new InMemoryClientLinks();
        $this->sources       = new DocumentSourceFakes();
        $this->invoices      = new FakeInvoiceDocuments();
        $this->prescriptions = new FakePrescriptionDocuments();
        $this->fakes         = new ClientPortalFakes();
        $this->clock         = new SettableClock(
            new \DateTimeImmutable('2026-10-29 09:00:00', new \DateTimeZone('UTC')),
        );
    }

    public function publisher(): DocumentPublisher
    {
        $events = new DomainEventPublisher($this->fakes);

        return new DocumentPublisher($this->documents, $this->links, $this->sources, $events);
    }

    /** A link of the client to the account, in the given status. */
    public function link(
        string $clientId = self::CLIENT,
        ClientLinkStatus $status = ClientLinkStatus::CONFIRMED,
        string $clinicId = self::CLINIC,
    ): void {
        $id = ClientLinkId::fromString($this->fakes->generate());
        $this->links->save(ClientLink::reconstitute($id, self::USER, $clinicId, $clientId, $status, null, null));
    }

    /**
     * A registered document, its events already pulled.
     *
     * @param list<string> $petIds
     */
    public function document(
        DocumentType $type = DocumentType::REPORT,
        PublicationStatus $status = PublicationStatus::AVAILABLE,
        int $fileVersion = 1,
        ?int $pendingFileVersion = null,
        ?string $storageKey = null,
        string $id = self::DOC,
        string $sourceRef = self::SOURCE,
        array $petIds = [],
        int $renderAttempts = 0,
        ?\DateTimeImmutable $updatedAt = null,
        ?string $patientId = self::PATIENT,
    ): PublishedDocument {
        $at = new \DateTimeImmutable('2026-10-01 08:00:00', new \DateTimeZone('UTC'));

        return $this->documents->add(PublishedDocument::reconstitute(
            PublishedDocumentId::fromString($id),
            self::CLINIC,
            self::CLIENT,
            $type,
            $sourceRef,
            null,
            $patientId,
            $petIds,
            'Document',
            $at,
            null,
            $storageKey ?? ($fileVersion >= 1 ? 'client_portal/documents/' . $id . '-v' . $fileVersion . '.pdf' : null),
            $fileVersion,
            $pendingFileVersion,
            $renderAttempts,
            $status,
            $at,
            $updatedAt ?? $at,
        ));
    }
}
