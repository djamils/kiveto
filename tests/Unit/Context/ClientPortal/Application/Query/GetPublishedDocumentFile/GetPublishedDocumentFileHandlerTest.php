<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query\GetPublishedDocumentFile;

use App\Context\ClientPortal\Application\Port\InvoicePortalState;
use App\Context\ClientPortal\Application\Port\ListedDocument;
use App\Context\ClientPortal\Application\Query\GetPublishedDocumentFile\GetPublishedDocumentFile;
use App\Context\ClientPortal\Application\Query\GetPublishedDocumentFile\GetPublishedDocumentFileHandler;
use App\Context\ClientPortal\Application\Service\ListableSources;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use App\Tests\Unit\Context\ClientPortal\Support\FakeInvoiceDocuments;
use App\Tests\Unit\Context\ClientPortal\Support\FakePrescriptionDocuments;
use App\Tests\Unit\Context\ClientPortal\Support\FakePublishedDocumentReadModel;
use PHPUnit\Framework\TestCase;

final class GetPublishedDocumentFileHandlerTest extends TestCase
{
    public function testTheServedFileOfAListableDocument(): void
    {
        $model               = new FakePublishedDocumentReadModel();
        $model->byId[W::DOC] = new ListedDocument(
            W::DOC,
            'report',
            W::SOURCE,
            'Compte rendu',
            [],
            new \DateTimeImmutable('2026-10-20'),
            true,
            2,
            'client_portal/documents/d-v2.pdf',
            'Dr Durand',
        );
        $handler = new GetPublishedDocumentFileHandler($model, new ListableSources(new FakeInvoiceDocuments(), new FakePrescriptionDocuments()));

        $file = $handler(new GetPublishedDocumentFile(W::USER, W::CLINIC, W::DOC));

        self::assertNotNull($file);
        self::assertSame(
            [W::DOC, 2, 'client_portal/documents/d-v2.pdf'],
            [$file->documentId, $file->version, $file->storageKey],
        );
        self::assertSame(['find:' . W::USER . ':' . W::CLINIC . ':' . W::DOC], $model->calls);
    }

    public function testNothingForANonListableDocumentOrAMalformedId(): void
    {
        $model   = new FakePublishedDocumentReadModel();
        $handler = new GetPublishedDocumentFileHandler($model, new ListableSources(new FakeInvoiceDocuments(), new FakePrescriptionDocuments()));

        self::assertNull($handler(new GetPublishedDocumentFile(W::USER, W::CLINIC, W::DOC)));
        self::assertNull($handler(new GetPublishedDocumentFile(W::USER, W::CLINIC, 'not-a-uuid')));
        self::assertCount(1, $model->calls, 'a malformed id is never looked up');
    }

    public function testNothingForAnInvoiceCancelledBeforeItsWithdrawalWasConsumed(): void
    {
        $model               = new FakePublishedDocumentReadModel();
        $model->byId[W::DOC] = new ListedDocument(W::DOC, 'invoice', W::SOURCE, 'Facture', [], new \DateTimeImmutable('2026-10-20'), true, 1, 'billing/i.pdf', null);
        $invoices            = new FakeInvoiceDocuments();
        $key                 = static fn (): ?string => new GetPublishedDocumentFileHandler($model, new ListableSources($invoices, new FakePrescriptionDocuments()))(
            new GetPublishedDocumentFile(W::USER, W::CLINIC, W::DOC),
        )?->storageKey;

        self::assertNull($key(), 'unknown at the source');

        $invoices->states[W::SOURCE] = new InvoicePortalState(null, 1200, 'EUR');
        self::assertNull($key(), 'cancelled');

        $invoices->states[W::SOURCE] = new InvoicePortalState(InvoicePortalState::UNPAID, 1200, 'EUR');
        self::assertSame('billing/i.pdf', $key());
    }
}
