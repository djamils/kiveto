<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\StorePublishedDocumentFile;

use App\Context\ClientPortal\Application\Command\AttachPublishedDocumentFile\AttachPublishedDocumentFile;
use App\Context\ClientPortal\Application\Command\RecordDocumentRenderFailure\RecordDocumentRenderFailure;
use App\Context\ClientPortal\Application\Command\StorePublishedDocumentFile\StorePublishedDocumentFile;
use App\Context\ClientPortal\Application\Command\StorePublishedDocumentFile\StorePublishedDocumentFileHandler;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Tests\Unit\Context\ClientPortal\Support\AnsweringCommandBus;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StorePublishedDocumentFileHandlerTest extends TestCase
{
    public function testNothingIsRenderedForAnUnknownOrWithdrawnDocumentOrAnotherVersion(): void
    {
        $world = new W();
        $bus   = $this->bus();
        $this->handler($world, $bus)(new StorePublishedDocumentFile(W::DOC, 1));

        $world->document(DocumentType::PRESCRIPTION, PublicationStatus::PENDING_FILE, 1, 2);
        $this->handler($world, $bus)(new StorePublishedDocumentFile(W::DOC, 3));
        $this->handler($world, $bus)(new StorePublishedDocumentFile(W::DOC, 1));

        $world->document(DocumentType::PRESCRIPTION, PublicationStatus::WITHDRAWN, 1, 2);
        $this->handler($world, $bus)(new StorePublishedDocumentFile(W::DOC, 2));

        self::assertSame([], $bus->commands);
        self::assertSame([], $world->prescriptions->rendered);
    }

    public function testAnInvoiceServesBillingsDurableFile(): void
    {
        $world = new W();
        $bus   = $this->bus();
        $world->document(DocumentType::INVOICE, PublicationStatus::PENDING_FILE, 0, 1);

        $this->handler($world, $bus)(new StorePublishedDocumentFile(W::DOC, 1));

        [$attach] = $bus->commands;
        self::assertInstanceOf(AttachPublishedDocumentFile::class, $attach);
        self::assertSame(
            [W::DOC, 1, 'billing/invoices/' . W::CLINIC . '/' . W::SOURCE . '.pdf'],
            [$attach->documentId, $attach->version, $attach->storageKey],
        );
    }

    public function testAPrescriptionIsRenderedUnderItsVersionedKey(): void
    {
        $world = new W();
        $bus   = $this->bus();
        $world->document(DocumentType::PRESCRIPTION, PublicationStatus::PENDING_FILE, 0, 1);

        $this->handler($world, $bus)(new StorePublishedDocumentFile(W::DOC, 1));

        $key = 'client_portal/documents/' . W::DOC . '-v1.pdf';
        self::assertSame([W::CLINIC . ':' . W::SOURCE . ':' . $key], $world->prescriptions->rendered);
        [$attach] = $bus->commands;
        self::assertInstanceOf(AttachPublishedDocumentFile::class, $attach);
        self::assertSame($key, $attach->storageKey);
    }

    public function testAReportIsRenderedAsItIsNowUnderItsVersionedKey(): void
    {
        $world = new W();
        $bus   = $this->bus();
        $world->document(DocumentType::REPORT, PublicationStatus::PENDING_FILE, 2, 3);

        $this->handler($world, $bus)(new StorePublishedDocumentFile(W::DOC, 3));

        $key = 'client_portal/documents/' . W::DOC . '-v3.pdf';
        self::assertSame($key, StorePublishedDocumentFileHandler::storageKey(W::DOC, 3));
        self::assertSame(['report:' . W::CLINIC . ':' . W::SOURCE . ':' . $key], $world->sources->rendered);
        [$attach] = $bus->commands;
        self::assertInstanceOf(AttachPublishedDocumentFile::class, $attach);
        self::assertSame([3, $key], [$attach->version, $attach->storageKey]);
    }

    /**
     * @return iterable<string, array{DocumentType}>
     */
    public static function provideAReservedTypeHasNoProducerAndCountsAsAFailureCases(): iterable
    {
        yield 'lab result' => [DocumentType::LAB_RESULT];
        yield 'certificate' => [DocumentType::CERTIFICATE];
    }

    #[DataProvider('provideAReservedTypeHasNoProducerAndCountsAsAFailureCases')]
    public function testAReservedTypeHasNoProducerAndCountsAsAFailure(DocumentType $type): void
    {
        $world = new W();
        $bus   = $this->bus();
        $world->document($type, PublicationStatus::PENDING_FILE, 0, 1);

        try {
            $this->handler($world, $bus)(new StorePublishedDocumentFile(W::DOC, 1));
            self::fail('Expected a LogicException.');
        } catch (\LogicException $e) {
            self::assertSame(\sprintf('No file producer for "%s" documents.', $type->value), $e->getMessage());
        }

        [$failure] = $bus->commands;
        self::assertInstanceOf(RecordDocumentRenderFailure::class, $failure);
    }

    public function testAFailedRenderIsRecordedThenRethrownForMessengerToRetry(): void
    {
        $world = new W();
        $bus   = $this->bus();
        $world->document(DocumentType::REPORT, PublicationStatus::PENDING_FILE, 0, 1);
        $world->sources->renderFailure = $failure = new \RuntimeException('Gotenberg is down');

        try {
            $this->handler($world, $bus)(new StorePublishedDocumentFile(W::DOC, 1));
            self::fail('Expected the render failure.');
        } catch (\RuntimeException $e) {
            self::assertSame($failure, $e);
        }

        self::assertCount(1, $bus->commands);
        self::assertInstanceOf(RecordDocumentRenderFailure::class, $bus->commands[0]);
        self::assertSame(W::DOC, $bus->commands[0]->documentId);
    }

    private function bus(): AnsweringCommandBus
    {
        return new AnsweringCommandBus(static fn (object $command): null => null);
    }

    private function handler(W $world, AnsweringCommandBus $bus): StorePublishedDocumentFileHandler
    {
        return new StorePublishedDocumentFileHandler(
            $world->documents,
            $world->invoices,
            $world->prescriptions,
            $world->sources,
            $bus,
        );
    }
}
