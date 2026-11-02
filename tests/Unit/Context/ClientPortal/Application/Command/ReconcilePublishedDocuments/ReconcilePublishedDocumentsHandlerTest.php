<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\ReconcilePublishedDocuments;

use App\Context\ClientPortal\Application\Command\ReconcilePublishedDocuments\ReconcilePublishedDocuments;
use App\Context\ClientPortal\Application\Command\ReconcilePublishedDocuments\ReconcilePublishedDocumentsHandler;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use PHPUnit\Framework\TestCase;

final class ReconcilePublishedDocumentsHandlerTest extends TestCase
{
    private const string STALE = '01960000-0000-7000-8000-0000000f0002';
    private const string FRESH = '01960000-0000-7000-8000-0000000f0003';

    public function testTheFilesExpectedForMoreThanTenMinutesAreAskedAgainOnce(): void
    {
        $world = new W();
        $now   = $world->clock->now();
        $world->document(
            DocumentType::REPORT,
            PublicationStatus::PENDING_FILE,
            1,
            2,
            id: self::STALE,
            sourceRef: self::STALE,
            updatedAt: $now->modify('-11 minutes'),
        );
        $world->document(
            DocumentType::REPORT,
            PublicationStatus::PENDING_FILE,
            0,
            1,
            id: self::FRESH,
            sourceRef: self::FRESH,
            updatedAt: $now->modify('-9 minutes'),
        );
        $handler = new ReconcilePublishedDocumentsHandler($world->documents, $world->sources, $world->clock);

        self::assertSame(1, $handler(new ReconcilePublishedDocuments()));
        self::assertSame(['store:' . self::STALE . ':2'], $world->sources->jobs);
        self::assertSame('2026-10-29 08:50', $world->documents->staleBefore[0]->format('Y-m-d H:i'));
        self::assertSame($now, $world->documents->documents[self::STALE]->updatedAt());

        self::assertSame(0, $handler(new ReconcilePublishedDocuments()), 'touched: out of the window');
    }

    public function testADocumentStillAwaitingALinkWhoseClientIsLinkedIsReleased(): void
    {
        $world = new W();
        $now   = $world->clock->now();
        $world->document(DocumentType::INVOICE, PublicationStatus::AWAITING_LINK, 0, id: self::STALE, sourceRef: self::STALE, updatedAt: $now->modify('-11 minutes'));
        $world->document(DocumentType::INVOICE, PublicationStatus::AWAITING_LINK, 0, id: self::FRESH, sourceRef: self::FRESH, updatedAt: $now->modify('-9 minutes'));
        $handler = new ReconcilePublishedDocumentsHandler($world->documents, $world->sources, $world->clock);

        self::assertSame(0, $handler(new ReconcilePublishedDocuments()), 'no confirmed link: it keeps waiting');

        $world->documents->confirmedClients = [[W::CLINIC, W::CLIENT]];
        self::assertSame(1, $handler(new ReconcilePublishedDocuments()));
        self::assertSame(['store:' . self::STALE . ':1'], $world->sources->jobs);
        self::assertSame(
            [PublicationStatus::PENDING_FILE, PublicationStatus::AWAITING_LINK],
            [$world->documents->documents[self::STALE]->status(), $world->documents->documents[self::FRESH]->status()],
        );
    }

    public function testARowNoLongerPendingIsSavedButNotAskedAgain(): void
    {
        $world    = new W();
        $document = $world->document(DocumentType::REPORT, PublicationStatus::AVAILABLE, 1);
        $stale    = $this->createMock(PublishedDocumentRepositoryInterface::class);
        $stale->expects(self::once())->method('findStalePendingFilesForUpdate')->with(
            self::callback(static fn (\DateTimeImmutable $at): bool => '08:50' === $at->format('H:i')),
            100,
        )->willReturn([$document]);
        $stale->expects(self::once())->method('save')->with(self::identicalTo($document));

        $handler = new ReconcilePublishedDocumentsHandler($stale, $world->sources, $world->clock);

        self::assertSame(0, $handler(new ReconcilePublishedDocuments()));
        self::assertSame([], $world->sources->jobs);
    }
}
