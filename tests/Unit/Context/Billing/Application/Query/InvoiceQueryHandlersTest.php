<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Application\Query;

use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Application\Port\ResolvedTaxDto;
use App\Context\Billing\Application\Query\GetBillingStats\BillingStats;
use App\Context\Billing\Application\Query\GetBillingStats\GetBillingStats;
use App\Context\Billing\Application\Query\GetBillingStats\GetBillingStatsHandler;
use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetailsHandler;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListItemView;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListRow;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoices;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesCriteria;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesHandler;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use PHPUnit\Framework\TestCase;

final class InvoiceQueryHandlersTest extends TestCase
{
    private const string CLINIC_ID  = '01960000-0000-7000-8000-000000000701';
    private const string INVOICE_ID = '01960000-0000-7000-8000-000000000702';

    public function testGettingAnInvoiceForwardsTheClinicScope(): void
    {
        $details = $this->details();

        $repository = $this->createMock(InvoiceReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('details')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (InvoiceId $id): bool => self::INVOICE_ID === $id->toString()),
            )
            ->willReturn($details)
        ;

        $result = (new GetInvoiceDetailsHandler($repository))(
            new GetInvoiceDetails(self::CLINIC_ID, self::INVOICE_ID),
        );

        self::assertSame($details, $result);
    }

    public function testSearchingWrapsTheRowsWithTheirPager(): void
    {
        $rows = [$this->row(), $this->row()];

        $repository = $this->createMock(InvoiceReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('search')
            ->willReturn(['items' => $rows, 'total' => 42])
        ;

        $view = (new SearchInvoicesHandler($repository))(new SearchInvoices(
            self::CLINIC_ID,
            new SearchInvoicesCriteria(page: 2, perPage: 25),
        ));

        self::assertInstanceOf(InvoiceListItemView::class, $view);
        self::assertSame($rows, $view->items);
        self::assertSame(42, $view->total);
        self::assertSame(2, $view->page);
        self::assertSame(25, $view->perPage);
        self::assertSame(2, $view->pageCount());
    }

    public function testStatsAreForwardedWithTheirWindow(): void
    {
        $from  = new \DateTimeImmutable('2026-08-22 00:00:00');
        $to    = new \DateTimeImmutable('2026-08-22 23:59:59');
        $stats = new BillingStats(12000, 4500, 3, 1, 'EUR');

        $repository = $this->createMock(InvoiceReadRepositoryInterface::class);
        $repository->expects(self::once())->method('stats')->with(
            self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
            $from,
            $to,
        )->willReturn($stats);

        $result = (new GetBillingStatsHandler($repository))(new GetBillingStats(self::CLINIC_ID, $from, $to));

        self::assertSame($stats, $result);
    }

    public function testTheCriteriaComputesItsOffset(): void
    {
        self::assertSame(0, (new SearchInvoicesCriteria(page: 1, perPage: 25))->offset());
        self::assertSame(50, (new SearchInvoicesCriteria(page: 3, perPage: 25))->offset());
        // A page below 1 cannot produce a negative offset.
        self::assertSame(0, (new SearchInvoicesCriteria(page: 0, perPage: 25))->offset());
    }

    public function testAReversedDateRangeCanMatchNothing(): void
    {
        $impossible = new SearchInvoicesCriteria(
            issuedFrom: new \DateTimeImmutable('2026-08-22'),
            issuedTo: new \DateTimeImmutable('2026-08-01'),
        );

        self::assertTrue($impossible->isImpossible());
        self::assertFalse((new SearchInvoicesCriteria())->isImpossible());
        self::assertFalse(
            (new SearchInvoicesCriteria(issuedFrom: new \DateTimeImmutable('2026-08-01')))->isImpossible(),
        );
    }

    public function testAnEmptyPageStillCountsAsOne(): void
    {
        $view = new InvoiceListItemView([], 0, 1, 25);

        self::assertSame(1, $view->pageCount());
    }

    public function testAnUnpricedLineIsBilledUntaxed(): void
    {
        $tax = ResolvedTaxDto::untaxed(4500);

        self::assertSame(4500, $tax->netMinorUnits);
        self::assertSame(0, $tax->taxMinorUnits);
        self::assertSame(4500, $tax->grossMinorUnits);
        self::assertSame('0', $tax->ratePercent);
    }

    private function details(): InvoiceDetails
    {
        return new InvoiceDetails(
            id: self::INVOICE_ID,
            clinicId: self::CLINIC_ID,
            number: 'FAC-2026-000001',
            status: 'PAID',
            statusLabel: 'Payée',
            admissionId: null,
            payerClientId: null,
            payerName: 'Sophie Dupont',
            payerAddressLines: [],
            lines: [],
            payments: [],
            taxBreakdown: [],
            currency: 'EUR',
            totalNetBeforeDiscountMinorUnits: 5000,
            discountMinorUnits: 0,
            discountKind: null,
            discountValue: null,
            discountReason: null,
            totalNetMinorUnits: 5000,
            totalTaxMinorUnits: 1000,
            totalGrossMinorUnits: 6000,
            amountPaidMinorUnits: 6000,
            balanceDueMinorUnits: 0,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-08-22 10:00:00'),
            issuedAt: new \DateTimeImmutable('2026-08-22 10:00:00'),
            paidAt: new \DateTimeImmutable('2026-08-22 10:05:00'),
            cancelledAt: null,
            cancellationReason: null,
        );
    }

    private function row(): InvoiceListRow
    {
        return new InvoiceListRow(
            id: self::INVOICE_ID,
            number: 'FAC-2026-000001',
            status: 'PAID',
            payerClientId: null,
            payerName: 'Sophie Dupont',
            currency: 'EUR',
            totalGrossMinorUnits: 6000,
            amountPaidMinorUnits: 6000,
            balanceDueMinorUnits: 0,
            createdAt: new \DateTimeImmutable('2026-08-22 10:00:00'),
            issuedAt: new \DateTimeImmutable('2026-08-22 10:00:00'),
            admissionId: null,
        );
    }
}
