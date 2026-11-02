<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query\CountUnreadDocumentsPerClinic;

use App\Context\ClientPortal\Application\Port\InvoicePortalState;
use App\Context\ClientPortal\Application\Port\ListedDocument;
use App\Context\ClientPortal\Application\Port\PrescriptionPortalState;
use App\Context\ClientPortal\Application\Query\CountUnreadDocumentsPerClinic\CountUnreadDocumentsPerClinic;
use App\Context\ClientPortal\Application\Query\CountUnreadDocumentsPerClinic\CountUnreadDocumentsPerClinicHandler;
use App\Context\ClientPortal\Application\Service\ListableSources;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use App\Tests\Unit\Context\ClientPortal\Support\FakeInvoiceDocuments;
use App\Tests\Unit\Context\ClientPortal\Support\FakePrescriptionDocuments;
use App\Tests\Unit\Context\ClientPortal\Support\FakePublishedDocumentReadModel;
use PHPUnit\Framework\TestCase;

final class CountUnreadDocumentsPerClinicHandlerTest extends TestCase
{
    private const string OTHER_CLINIC = '01960000-0000-7000-8000-0000000f9001';

    public function testTheBadgeCountsOnlyWhatTheListShows(): void
    {
        $model         = new FakePublishedDocumentReadModel();
        $model->unread = [
            W::CLINIC => [
                self::row('report', 'r1'),
                self::row('invoice', 'paid'),
                self::row('invoice', 'cancelled'),
                self::row('prescription', 'issued'),
                self::row('prescription', 'revoked'),
            ],
            self::OTHER_CLINIC => [self::row('invoice', 'unknown')],
        ];
        $invoices                         = new FakeInvoiceDocuments();
        $invoices->states['paid']         = new InvoicePortalState(InvoicePortalState::PAID, 1000, 'EUR');
        $invoices->states['cancelled']    = new InvoicePortalState(null, 1000, 'EUR');
        $prescriptions                    = new FakePrescriptionDocuments();
        $prescriptions->states['issued']  = new PrescriptionPortalState(true, null, true);
        $prescriptions->states['revoked'] = new PrescriptionPortalState(false, null, false);

        $counts = new CountUnreadDocumentsPerClinicHandler($model, new ListableSources($invoices, $prescriptions))(
            new CountUnreadDocumentsPerClinic(W::USER),
        );

        self::assertSame([W::CLINIC => 3], $counts, 'a clinic whose unread documents are all hidden has no badge');
        self::assertSame(['unreadPerClinic:' . W::USER], $model->calls);
        self::assertSame([['paid', 'cancelled'], ['unknown']], $invoices->stateRequests, 'one batch per clinic');
    }

    private static function row(string $type, string $source): ListedDocument
    {
        return new ListedDocument($type . '-' . $source, $type, $source, 'Document', [], new \DateTimeImmutable('2026-10-20'), true, 1, 'key', null);
    }
}
