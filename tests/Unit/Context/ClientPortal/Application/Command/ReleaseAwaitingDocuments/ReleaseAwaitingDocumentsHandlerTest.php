<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\ReleaseAwaitingDocuments;

use App\Context\ClientPortal\Application\Command\ReleaseAwaitingDocuments\ReleaseAwaitingDocuments;
use App\Context\ClientPortal\Application\Command\ReleaseAwaitingDocuments\ReleaseAwaitingDocumentsHandler;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use PHPUnit\Framework\TestCase;

final class ReleaseAwaitingDocumentsHandlerTest extends TestCase
{
    public function testEveryWaitingDocumentGetsItsFirstFileInBatchesOfAHundred(): void
    {
        $world = new W();
        $this->waiting($world, 150);
        $world->document(DocumentType::INVOICE, PublicationStatus::AVAILABLE, 1, id: $this->id(999), sourceRef: 'x');

        $this->handler($world)(new ReleaseAwaitingDocuments(W::CLINIC, W::CLIENT));

        self::assertSame([100, 100], $world->documents->awaitingLimits);
        self::assertCount(150, $world->sources->jobs);
        self::assertSame('store:' . $this->id(1) . ':1', $world->sources->jobs[0]);
        self::assertSame(150, $world->documents->saves);
        self::assertSame(PublicationStatus::PENDING_FILE, $world->documents->documents[$this->id(150)]->status());
    }

    public function testAFullLastBatchAsksOnceMore(): void
    {
        $world = new W();
        $this->waiting($world, 100);

        $this->handler($world)(new ReleaseAwaitingDocuments(W::CLINIC, W::CLIENT));

        self::assertSame([100, 100], $world->documents->awaitingLimits);
        self::assertCount(100, $world->sources->jobs);
    }

    public function testAnotherClientsDocumentsStay(): void
    {
        $world = new W();
        $world->document(DocumentType::INVOICE, PublicationStatus::AWAITING_LINK, 0);

        $this->handler($world)(new ReleaseAwaitingDocuments(W::CLINIC, W::CLIENT2));

        self::assertSame([100], $world->documents->awaitingLimits);
        self::assertSame(PublicationStatus::AWAITING_LINK, $world->documents->only()->status());
        self::assertSame([], $world->sources->jobs);
    }

    private function waiting(W $world, int $count): void
    {
        for ($i = 1; $i <= $count; ++$i) {
            $id = $this->id($i);
            $world->document(DocumentType::INVOICE, PublicationStatus::AWAITING_LINK, 0, id: $id, sourceRef: $id);
        }
    }

    private function id(int $n): string
    {
        return \sprintf('01960000-0000-7000-8000-0000000f%04d', $n);
    }

    private function handler(W $world): ReleaseAwaitingDocumentsHandler
    {
        return new ReleaseAwaitingDocumentsHandler($world->documents, $world->sources, $world->clock);
    }
}
