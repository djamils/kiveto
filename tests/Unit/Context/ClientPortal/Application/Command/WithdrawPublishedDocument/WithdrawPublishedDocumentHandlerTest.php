<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\WithdrawPublishedDocument;

use App\Context\ClientPortal\Application\Command\WithdrawPublishedDocument\WithdrawPublishedDocument;
use App\Context\ClientPortal\Application\Command\WithdrawPublishedDocument\WithdrawPublishedDocumentHandler;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use PHPUnit\Framework\TestCase;

final class WithdrawPublishedDocumentHandlerTest extends TestCase
{
    public function testAPublishedDocumentIsWithdrawnOnce(): void
    {
        $world = new W();
        $world->document(DocumentType::INVOICE, PublicationStatus::PENDING_FILE, 1, 2);
        $world->clock->advance('+1 hour');

        $this->handler($world)(new WithdrawPublishedDocument(W::CLINIC, DocumentType::INVOICE, W::SOURCE));
        $this->handler($world)(new WithdrawPublishedDocument(W::CLINIC, DocumentType::INVOICE, W::SOURCE));

        $document = $world->documents->only();
        self::assertSame(
            [PublicationStatus::WITHDRAWN, null, '2026-10-29 10:00'],
            [$document->status(), $document->pendingFileVersion(), $document->updatedAt()->format('Y-m-d H:i')],
        );
        self::assertSame(['client_portal.document.withdrawn.v1'], $world->fakes->eventNames());
    }

    public function testAWithdrawalBeforeThePublicationLeavesATombstone(): void
    {
        $world = new W();
        $world->document(DocumentType::INVOICE, sourceRef: '01960000-0000-7000-8000-0000000f1009');

        $this->handler($world)(new WithdrawPublishedDocument(W::CLINIC, DocumentType::PRESCRIPTION, W::SOURCE));

        $tombstone = $world->documents->findForUpdate(W::CLINIC, DocumentType::PRESCRIPTION, W::SOURCE);
        self::assertNotNull($tombstone);
        self::assertSame(
            ['01960000-0000-7000-8000-000000000001', PublicationStatus::WITHDRAWN, null],
            [$tombstone->id()->toString(), $tombstone->status(), $tombstone->clientId()],
        );
        self::assertSame(0, $world->documents->saves);
        self::assertSame([], $world->fakes->events);
    }

    public function testAPublicationThatWonTheRaceIsWithdrawn(): void
    {
        $world                          = new W();
        $world->documents->beforeInsert = static function (PublishedDocument $tombstone) use ($world): void {
            $world->document(DocumentType::INVOICE, PublicationStatus::AWAITING_LINK, 0);
        };

        $this->handler($world)(new WithdrawPublishedDocument(W::CLINIC, DocumentType::INVOICE, W::SOURCE));

        self::assertSame([W::DOC, PublicationStatus::WITHDRAWN], [
            $world->documents->only()->id()->toString(),
            $world->documents->only()->status(),
        ]);
        self::assertSame(['client_portal.document.withdrawn.v1'], $world->fakes->eventNames());
    }

    public function testARowThatVanishedIsALogicError(): void
    {
        $world                         = new W();
        $world->documents->loseInserts = true;

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The published document row vanished.');

        $this->handler($world)(new WithdrawPublishedDocument(W::CLINIC, DocumentType::INVOICE, W::SOURCE));
    }

    private function handler(W $world): WithdrawPublishedDocumentHandler
    {
        return new WithdrawPublishedDocumentHandler(
            $world->documents,
            $world->fakes,
            $world->clock,
            new DomainEventPublisher($world->fakes),
        );
    }
}
