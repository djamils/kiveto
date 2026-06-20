<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Query;

use App\Context\Procurement\Application\Port\SupplierReceiptReadRepositoryInterface;
use App\Context\Procurement\Application\Query\SearchUnmatchedDeliveries\SearchUnmatchedDeliveries;
use App\Context\Procurement\Application\Query\SearchUnmatchedDeliveries\SearchUnmatchedDeliveriesHandler;
use PHPUnit\Framework\TestCase;

final class SearchUnmatchedDeliveriesHandlerTest extends TestCase
{
    private const string CLINIC_UUID = '01932b00-0000-7000-8000-000000000010';

    public function testItReturnsUnmatchedDeliveries(): void
    {
        $readRepository = $this->createStub(SupplierReceiptReadRepositoryInterface::class);
        $readRepository->method('findUnmatched')->with(self::CLINIC_UUID)
            ->willReturn([['id' => 'delivery-1', 'resolved' => false]])
        ;

        $handler = new SearchUnmatchedDeliveriesHandler($readRepository);
        $result  = $handler(new SearchUnmatchedDeliveries(self::CLINIC_UUID));

        self::assertCount(1, $result);
        self::assertFalse($result[0]['resolved']);
    }
}
