<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Query;

use App\Context\Procurement\Application\Port\SupplierReadRepositoryInterface;
use App\Context\Procurement\Application\Query\GetSupplierDetail\GetSupplierDetail;
use App\Context\Procurement\Application\Query\GetSupplierDetail\GetSupplierDetailHandler;
use App\Context\Procurement\Domain\Supplier\Exception\SupplierNotFoundException;
use PHPUnit\Framework\TestCase;

final class GetSupplierDetailHandlerTest extends TestCase
{
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';

    public function testItReturnsSupplierData(): void
    {
        $readRepository = $this->createStub(SupplierReadRepositoryInterface::class);
        $readRepository->method('findById')->with(self::SUPPLIER_UUID)
            ->willReturn(['id' => self::SUPPLIER_UUID, 'name' => 'Centravet'])
        ;

        $handler = new GetSupplierDetailHandler($readRepository);
        $result  = $handler(new GetSupplierDetail(self::SUPPLIER_UUID));

        self::assertSame(self::SUPPLIER_UUID, $result['id']);
    }

    public function testItThrowsWhenNotFound(): void
    {
        $readRepository = $this->createStub(SupplierReadRepositoryInterface::class);
        $readRepository->method('findById')->willReturn(null);

        $handler = new GetSupplierDetailHandler($readRepository);

        $this->expectException(SupplierNotFoundException::class);
        $handler(new GetSupplierDetail(self::SUPPLIER_UUID));
    }
}
