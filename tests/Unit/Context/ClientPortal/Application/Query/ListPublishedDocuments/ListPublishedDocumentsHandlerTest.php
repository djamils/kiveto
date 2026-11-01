<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query\ListPublishedDocuments;

use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Application\Port\InvoicePortalState;
use App\Context\ClientPortal\Application\Port\ListedDocument;
use App\Context\ClientPortal\Application\Port\PrescriptionPortalState;
use App\Context\ClientPortal\Application\Query\ListPublishedDocuments\ListPublishedDocuments;
use App\Context\ClientPortal\Application\Query\ListPublishedDocuments\ListPublishedDocumentsHandler;
use App\Context\ClientPortal\Application\Query\ListPublishedDocuments\PortalDocument;
use App\Context\ClientPortal\Application\Service\RenewalStatuses;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\InvalidCursorException;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use App\Tests\Unit\Context\ClientPortal\Support\FakePublishedDocumentReadModel;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryRenewalRequests;
use App\Tests\Unit\Context\ClientPortal\Support\Requests;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\BufferingLogger;

final class ListPublishedDocumentsHandlerTest extends TestCase
{
    private const string D1 = '01960000-0000-7000-8000-0000000f0001';
    private const string D2 = '01960000-0000-7000-8000-0000000f0002';
    private const string D3 = '01960000-0000-7000-8000-0000000f0003';
    private const string S1 = '01960000-0000-7000-8000-0000000f1001';
    private const string S2 = '01960000-0000-7000-8000-0000000f1002';
    private const string S3 = '01960000-0000-7000-8000-0000000f1003';

    private W $world;
    private FakePublishedDocumentReadModel $model;
    private BufferingLogger $logger;
    private InMemoryRenewalRequests $renewals;

    protected function setUp(): void
    {
        $this->world    = new W();
        $this->model    = new FakePublishedDocumentReadModel();
        $this->logger   = new BufferingLogger();
        $this->renewals = new InMemoryRenewalRequests();
    }

    public function testAFirstPageAsksOneRowMoreThanTheDefaultLimitAndHasNoNextCursor(): void
    {
        $this->model->rows = [$this->row(self::D1, 'report', self::S1, '2026-10-20 10:00:00')];

        $page = $this->handler()(new ListPublishedDocuments(W::USER, W::CLINIC, 'report', W::ANIMAL));

        self::assertSame([[W::USER, W::CLINIC, 'report', W::ANIMAL, null, 21]], $this->model->listings);
        self::assertNull($page->nextCursor);
        self::assertCount(1, $page->items);
    }

    /**
     * @return iterable<string, array{?int, int}>
     */
    public static function provideTheLimitIsClampedCases(): iterable
    {
        yield 'above the maximum' => [100, 51];
        yield 'zero' => [0, 2];
        yield 'negative' => [-3, 2];
    }

    #[DataProvider('provideTheLimitIsClampedCases')]
    public function testTheLimitIsClamped(?int $limit, int $asked): void
    {
        $this->handler()(new ListPublishedDocuments(W::USER, W::CLINIC, limit: $limit));

        self::assertSame($asked, $this->model->listings[0][5]);
    }

    public function testAFullPageEndsWithTheCursorOfItsLastRowWhichTheNextPageStartsAfter(): void
    {
        $this->model->rows = [
            $this->row(self::D3, 'report', self::S3, '2026-10-22 10:00:00'),
            $this->row(self::D2, 'report', self::S2, '2026-10-21 10:00:00'),
            $this->row(self::D1, 'report', self::S1, '2026-10-20 10:00:00'),
        ];

        $page = $this->handler()(new ListPublishedDocuments(W::USER, W::CLINIC, limit: 2));

        $ids = array_map(static fn (PortalDocument $d): string => $d->id, $page->items);
        self::assertSame([self::D3, self::D2], $ids);
        self::assertNotNull($page->nextCursor);
        $position = Cursor::decode($page->nextCursor)->position();
        self::assertSame(['at' => '2026-10-21 10:00:00', 'id' => self::D2], $position);

        $this->handler()(new ListPublishedDocuments(W::USER, W::CLINIC, cursor: $page->nextCursor, limit: 2));

        $after = $this->model->listings[1][4];
        self::assertNotNull($after);
        self::assertSame(['2026-10-21 10:00:00', 'UTC', self::D2], [
            $after->issuedAt->format('Y-m-d H:i:s'),
            $after->issuedAt->getTimezone()->getName(),
            $after->id,
        ]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideACursorOfAnotherShapeIsRejectedCases(): iterable
    {
        $at = '2026-10-21 10:00:00';

        yield 'not base64url' => ['not a cursor!'];
        yield 'missing id' => [Cursor::fromPosition(['at' => $at])->encode()];
        yield 'missing at' => [Cursor::fromPosition(['id' => self::D1])->encode()];
        yield 'integer at' => [Cursor::fromPosition(['at' => 1761040800, 'id' => self::D1])->encode()];
        yield 'integer id' => [Cursor::fromPosition(['at' => $at, 'id' => 12])->encode()];
        yield 'not a date' => [Cursor::fromPosition(['at' => 'yesterday', 'id' => self::D1])->encode()];
        yield 'overflowing date' => [Cursor::fromPosition(['at' => '2026-02-30 10:00:00', 'id' => self::D1])->encode()];
        yield 'another format' => [Cursor::fromPosition(['at' => '2026-10-21T10:00:00', 'id' => self::D1])->encode()];
        yield 'not a uuid' => [Cursor::fromPosition(['at' => $at, 'id' => 'doc-1'])->encode()];
    }

    #[DataProvider('provideACursorOfAnotherShapeIsRejectedCases')]
    public function testACursorOfAnotherShapeIsRejected(string $cursor): void
    {
        $this->expectException(InvalidCursorException::class);

        try {
            $this->handler()(new ListPublishedDocuments(W::USER, W::CLINIC, cursor: $cursor));
        } finally {
            self::assertSame([], $this->model->listings);
        }
    }

    public function testInvoicesAndPrescriptionsCarryTheirLiveStateInTheClinicTimeZone(): void
    {
        $this->world->fakes->clinics[W::CLINIC] = new ClinicLabel('Clinique', 'Paris', 'Europe/Paris');
        $this->model->rows                      = [
            $this->row(self::D3, 'invoice', self::S3, '2026-10-22 23:30:00', unread: true),
            $this->row(self::D2, 'prescription', self::S2, '2026-10-21 10:00:00'),
            $this->row(self::D1, 'report', self::S1, '2026-10-20 10:00:00'),
        ];
        $paid                                         = InvoicePortalState::PARTIALLY_PAID;
        $this->world->invoices->states[self::S3]      = new InvoicePortalState($paid, 12500, 'EUR');
        $validUntil                                   = new \DateTimeImmutable('2027-10-21');
        $this->world->prescriptions->states[self::S2] = new PrescriptionPortalState(true, $validUntil, true);
        $this->renewals->add(Requests::renewal('01960000-0000-7000-8000-0000000f9001', self::S2, at: '2026-10-22 08:00:00'));
        $this->renewals->add(Requests::renewal('01960000-0000-7000-8000-0000000f9002', self::S1, at: '2026-10-22 08:00:00'));

        $items = $this->handler()(new ListPublishedDocuments(W::USER, W::CLINIC))->items;

        self::assertSame([[self::S3]], $this->world->invoices->stateRequests);
        [$invoice, $prescription, $report] = $items;
        self::assertSame(
            [self::D3, 'invoice', 'Document ' . self::D3, ['pet-1'], '2026-10-23 01:30', 'Europe/Paris', true],
            [
                $invoice->id, $invoice->type, $invoice->title, $invoice->petIds,
                $invoice->issuedAt->format('Y-m-d H:i'), $invoice->issuedAt->getTimezone()->getName(), $invoice->unread,
            ],
        );
        self::assertNotNull($invoice->invoice);
        self::assertSame([12500, 'EUR', 'partially_paid'], [
            $invoice->invoice->totalMinorUnits,
            $invoice->invoice->currency,
            $invoice->invoice->paymentStatus,
        ]);
        self::assertNull($invoice->prescription);

        self::assertNull($prescription->invoice);
        self::assertNotNull($prescription->prescription);
        self::assertSame([self::S2, $validUntil, true], [
            $prescription->prescription->prescriptionId,
            $prescription->prescription->validUntil,
            $prescription->prescription->renewable,
        ]);
        // The latest renewal request of the prescription (AC 68)
        self::assertSame(['submitted', '01960000-0000-7000-8000-0000000f9001', null], [
            $prescription->prescription->renewalStatus?->status,
            $prescription->prescription->renewalStatus?->requestId,
            $prescription->prescription->renewalStatus?->resultingPrescriptionId,
        ]);

        self::assertSame([null, null, false], [$report->invoice, $report->prescription, $report->unread]);
        self::assertSame([], $this->logger->cleanLogs());
    }

    public function testRowsWhoseSourceIsNoLongerListableAreLeftOutWithAWarning(): void
    {
        $missing           = '01960000-0000-7000-8000-0000000f0004';
        $this->model->rows = [
            $this->row(self::D3, 'invoice', self::S3, '2026-10-22 10:00:00'),
            $this->row($missing, 'invoice', $missing, '2026-10-22 09:00:00'),
            $this->row(self::D2, 'prescription', self::S2, '2026-10-21 10:00:00'),
            $this->row(self::D1, 'prescription', self::S1, '2026-10-20 10:00:00'),
        ];
        $this->world->invoices->states[self::S3]      = new InvoicePortalState(null, 100, 'EUR');
        $this->world->prescriptions->states[self::S2] = new PrescriptionPortalState(false, null, false);

        $page = $this->handler()(new ListPublishedDocuments(W::USER, W::CLINIC));

        self::assertSame([], $page->items);
        $skipped = [];
        foreach ($this->logger->cleanLogs() as $log) {
            \assert(\is_array($log) && \is_array($log[2]));
            self::assertSame('warning', $log[0]);
            self::assertSame('A published document whose source is no longer listable was left out.', $log[1]);
            self::assertSame(W::CLINIC, $log[2]['clinicId']);
            $skipped[] = [$log[2]['documentId'], $log[2]['type']];
        }
        self::assertSame(
            [
                [self::D3, 'invoice'],
                [$missing, 'invoice'],
                [self::D2, 'prescription'],
                [self::D1, 'prescription'],
            ],
            $skipped,
        );
    }

    private function row(
        string $id,
        string $type,
        string $source,
        string $issuedAt,
        bool $unread = false,
    ): ListedDocument {
        return new ListedDocument(
            $id,
            $type,
            $source,
            'Document ' . $id,
            ['pet-1'],
            new \DateTimeImmutable($issuedAt, new \DateTimeZone('UTC')),
            $unread,
            1,
            'client_portal/documents/' . $id . '-v1.pdf',
            null,
        );
    }

    private function handler(): ListPublishedDocumentsHandler
    {
        return new ListPublishedDocumentsHandler(
            $this->model,
            $this->world->invoices,
            $this->world->prescriptions,
            $this->world->fakes,
            new RenewalStatuses($this->renewals),
            $this->logger,
        );
    }
}
