<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use PHPUnit\Framework\TestCase;

final class DocumentPublisherTest extends TestCase
{
    public function testOnlyAConfirmedLinkOfTheClientCounts(): void
    {
        $world = new W();
        self::assertFalse($world->publisher()->hasConfirmedLink(W::CLINIC, W::CLIENT), 'no link');

        $world->link(W::CLIENT, ClientLinkStatus::REVOKED);
        $world->link(W::CLIENT, ClientLinkStatus::PENDING);
        $world->link(W::CLIENT2);
        self::assertFalse(
            $world->publisher()->hasConfirmedLink(W::CLINIC, W::CLIENT),
            'revoked, pending, another client',
        );

        $world->link(W::CLIENT);
        self::assertTrue($world->publisher()->hasConfirmedLink(W::CLINIC, W::CLIENT));
    }

    public function testALinkedInsertRequestsTheFirstFileAndPublishesItsEvent(): void
    {
        $world    = new W();
        $document = $this->publish($world, linked: true);

        self::assertTrue($world->publisher()->insert($document));

        self::assertSame($document, $world->documents->only());
        self::assertSame(['store:' . W::DOC . ':1'], $world->sources->jobs);
        self::assertSame(['client_portal.document.published.v1'], $world->fakes->eventNames());
    }

    public function testAnUnlinkedInsertWaitsWithoutFile(): void
    {
        $world = new W();

        self::assertTrue($world->publisher()->insert($this->publish($world, linked: false)));

        self::assertSame([], $world->sources->jobs);
        self::assertSame(['client_portal.document.published.v1'], $world->fakes->eventNames());
    }

    public function testASecondDeliveryOfTheSameSourceDoesNothing(): void
    {
        $world = new W();
        $world->document(DocumentType::INVOICE, id: '01960000-0000-7000-8000-0000000f0009');

        self::assertFalse($world->publisher()->insert($this->publish($world, linked: true)));

        self::assertSame('01960000-0000-7000-8000-0000000f0009', $world->documents->only()->id()->toString());
        self::assertSame([], $world->sources->jobs);
        self::assertSame([], $world->fakes->events);
    }

    public function testSaveRequestsTheGivenVersionOnly(): void
    {
        $world    = new W();
        $document = $this->publish($world, linked: true);

        $world->publisher()->save($document, null);
        self::assertSame(1, $world->documents->saves);
        self::assertSame([], $world->sources->jobs);
        self::assertSame(['client_portal.document.published.v1'], $world->fakes->eventNames());

        $world->publisher()->save($document, 3);
        self::assertSame(['store:' . W::DOC . ':3'], $world->sources->jobs);
        self::assertCount(1, $world->fakes->events, 'events are pulled once');
    }

    private function publish(W $world, bool $linked): PublishedDocument
    {
        return PublishedDocument::publish(
            PublishedDocumentId::fromString(W::DOC),
            W::CLINIC,
            W::CLIENT,
            DocumentType::INVOICE,
            W::SOURCE,
            null,
            null,
            [],
            'Facture F-1',
            $world->clock->now(),
            null,
            $linked,
            $world->clock->now(),
        );
    }
}
