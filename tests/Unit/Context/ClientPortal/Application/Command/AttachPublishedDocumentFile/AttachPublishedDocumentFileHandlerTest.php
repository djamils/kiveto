<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\AttachPublishedDocumentFile;

use App\Context\ClientPortal\Application\Command\AttachPublishedDocumentFile\AttachPublishedDocumentFile;
use App\Context\ClientPortal\Application\Command\AttachPublishedDocumentFile\AttachPublishedDocumentFileHandler;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use PHPUnit\Framework\TestCase;

final class AttachPublishedDocumentFileHandlerTest extends TestCase
{
    public function testTheFirstFileIsServedWithoutResettingReads(): void
    {
        $world = new W();
        $world->document(DocumentType::PRESCRIPTION, PublicationStatus::PENDING_FILE, 0, 1);
        $key = 'client_portal/documents/' . W::DOC . '-v1.pdf';

        $this->handler($world)(new AttachPublishedDocumentFile(W::DOC, 1, $key));

        $document = $world->documents->only();
        self::assertSame(
            [PublicationStatus::AVAILABLE, 1, $key, null, '2026-10-29 09:00'],
            [
                $document->status(), $document->fileVersion(), $document->storageKey(),
                $document->pendingFileVersion(), $document->updatedAt()->format('Y-m-d H:i'),
            ],
        );
        self::assertSame(1, $world->documents->saves);
        self::assertSame([], $world->sources->reads);
        self::assertSame([], $world->sources->jobs);
    }

    public function testANewVersionIsUnreadAgainAndThePreviousPortalFileDeletedLater(): void
    {
        $world = new W();
        $world->document(DocumentType::REPORT, PublicationStatus::PENDING_FILE, 1, 2);
        $key = 'client_portal/documents/' . W::DOC . '-v2.pdf';

        $this->handler($world)(new AttachPublishedDocumentFile(W::DOC, 2, $key));

        self::assertSame($key, $world->documents->only()->storageKey());
        self::assertSame(['forget:' . W::DOC], $world->sources->reads);
        self::assertSame(['delete:client_portal/documents/' . W::DOC . '-v1.pdf'], $world->sources->jobs);
    }

    public function testAReplacedFileOutsideThePortalStorageIsNeverDeleted(): void
    {
        $world = new W();
        $world->document(DocumentType::INVOICE, PublicationStatus::PENDING_FILE, 1, 2, 'billing/invoices/old.pdf');

        $this->handler($world)(new AttachPublishedDocumentFile(W::DOC, 2, 'billing/invoices/new.pdf'));

        self::assertSame('billing/invoices/new.pdf', $world->documents->only()->storageKey());
        self::assertSame(['forget:' . W::DOC], $world->sources->reads);
        self::assertSame([], $world->sources->jobs);
    }

    public function testALateOrUnknownFileIsIgnored(): void
    {
        $world = new W();
        $this->handler($world)(new AttachPublishedDocumentFile(W::DOC, 1, 'client_portal/documents/x-v1.pdf'));

        $world->document(DocumentType::REPORT, PublicationStatus::PENDING_FILE, 1, 3);
        $this->handler($world)(new AttachPublishedDocumentFile(W::DOC, 2, 'client_portal/documents/x-v2.pdf'));

        $world->document(DocumentType::REPORT, PublicationStatus::WITHDRAWN, 1, null);
        $this->handler($world)(new AttachPublishedDocumentFile(W::DOC, 2, 'client_portal/documents/x-v2.pdf'));

        self::assertSame(0, $world->documents->saves);
        self::assertSame([1, 'client_portal/documents/' . W::DOC . '-v1.pdf'], [
            $world->documents->only()->fileVersion(),
            $world->documents->only()->storageKey(),
        ]);
        self::assertSame([], $world->sources->reads);
        // A superseded file of ours is never served: deleted later, once per late render.
        self::assertSame(['delete:client_portal/documents/x-v2.pdf', 'delete:client_portal/documents/x-v2.pdf'], $world->sources->jobs);
    }

    public function testASecondAttachOfTheServedVersionChangesNothing(): void
    {
        $world = new W();
        $world->document(DocumentType::REPORT, PublicationStatus::AVAILABLE, 2, null);
        $served = $world->documents->only()->storageKey();
        \assert(null !== $served);

        $this->handler($world)(new AttachPublishedDocumentFile(W::DOC, 2, $served));

        self::assertSame(0, $world->documents->saves);
        self::assertSame([], $world->sources->reads);
        self::assertSame([], $world->sources->jobs);
    }

    private function handler(W $world): AttachPublishedDocumentFileHandler
    {
        $sources = $world->sources;

        return new AttachPublishedDocumentFileHandler($world->documents, $sources, $sources, $world->clock);
    }
}
