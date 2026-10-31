<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Application\Query;

use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Application\Query\ListInvoicePortalStatuses\InvoicePortalStatus;
use App\Context\Billing\Application\Query\ListInvoicePortalStatuses\ListInvoicePortalStatuses;
use App\Context\Billing\Application\Query\ListInvoicePortalStatuses\ListInvoicePortalStatusesHandler;
use App\Context\Billing\Application\Query\ListPortalPublishableInvoices\ListPortalPublishableInvoices;
use App\Context\Billing\Application\Query\ListPortalPublishableInvoices\ListPortalPublishableInvoicesHandler;
use App\Context\Billing\Application\Query\ListPortalPublishableInvoices\PortalPublishableInvoice;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use PHPUnit\Framework\TestCase;

final class PortalQueryHandlersTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-000000000b21';
    private const string INVOICE_A = '01960000-0000-7000-8000-000000000b22';
    private const string INVOICE_B = '01960000-0000-7000-8000-000000000b23';

    public function testStatusesAreReadInTheClinicScopeForTheGivenIds(): void
    {
        $rows = [new InvoicePortalStatus(self::INVOICE_A, 'ISSUED', 6000, 'EUR')];

        $repository = $this->createMock(InvoiceReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('portalStatuses')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (array $ids): bool => [self::INVOICE_A, self::INVOICE_B] === array_map(
                    static fn (InvoiceId $id): string => $id->toString(),
                    array_values(array_filter($ids, static fn (mixed $id): bool => $id instanceof InvoiceId)),
                )),
            )
            ->willReturn($rows)
        ;

        $result = (new ListInvoicePortalStatusesHandler($repository))(
            new ListInvoicePortalStatuses(self::CLINIC_ID, [self::INVOICE_A, self::INVOICE_B]),
        );

        self::assertSame($rows, $result);
    }

    public function testNoIdsReadNothing(): void
    {
        $repository = $this->createMock(InvoiceReadRepositoryInterface::class);
        $repository->expects(self::never())->method('portalStatuses');

        $handler = new ListInvoicePortalStatusesHandler($repository);

        self::assertSame([], $handler(new ListInvoicePortalStatuses(self::CLINIC_ID, [])));
    }

    public function testPublishableInvoicesArePagedAfterAnId(): void
    {
        $from = new \DateTimeImmutable('2024-10-31 00:00:00');
        $rows = [new PortalPublishableInvoice(self::INVOICE_B, self::CLINIC_ID)];

        $repository = $this->createMock(InvoiceReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('portalPublishable')
            ->with(
                $from,
                self::callback(static fn (?InvoiceId $id): bool => self::INVOICE_A === $id?->toString()),
                100,
            )
            ->willReturn($rows)
        ;

        self::assertSame($rows, (new ListPortalPublishableInvoicesHandler($repository))(
            new ListPortalPublishableInvoices($from, self::INVOICE_A, 100),
        ));
    }

    public function testTheFirstPageStartsFromNoId(): void
    {
        $from = new \DateTimeImmutable('2024-10-31 00:00:00');

        $repository = $this->createMock(InvoiceReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('portalPublishable')
            ->with($from, null, 50)
            ->willReturn([])
        ;

        self::assertSame([], (new ListPortalPublishableInvoicesHandler($repository))(
            new ListPortalPublishableInvoices($from, null, 50),
        ));
    }
}
