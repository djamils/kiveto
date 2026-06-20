<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Query;

use App\Context\Procurement\Application\Port\SupplierReceiptReadRepositoryInterface;
use App\Context\Procurement\Application\Query\ListPendingReceipts\ListPendingReceipts;
use App\Context\Procurement\Application\Query\ListPendingReceipts\ListPendingReceiptsHandler;
use PHPUnit\Framework\TestCase;

final class ListPendingReceiptsHandlerTest extends TestCase
{
    private const string CLINIC_UUID = '01932b00-0000-7000-8000-000000000010';

    public function testItReturnsPendingReceipts(): void
    {
        $readRepository = $this->createStub(SupplierReceiptReadRepositoryInterface::class);
        $readRepository->method('findPending')->with(self::CLINIC_UUID)
            ->willReturn([['id' => 'receipt-1', 'status' => 'PENDING_REVIEW']])
        ;

        $handler = new ListPendingReceiptsHandler($readRepository);
        $result  = $handler(new ListPendingReceipts(self::CLINIC_UUID));

        self::assertCount(1, $result);
        self::assertSame('PENDING_REVIEW', $result[0]['status']);
    }

    public function testItReturnsEmptyArrayWhenNoPendingReceipts(): void
    {
        $readRepository = $this->createStub(SupplierReceiptReadRepositoryInterface::class);
        $readRepository->method('findPending')->willReturn([]);

        $handler = new ListPendingReceiptsHandler($readRepository);
        $result  = $handler(new ListPendingReceipts(self::CLINIC_UUID));

        self::assertSame([], $result);
    }
}
