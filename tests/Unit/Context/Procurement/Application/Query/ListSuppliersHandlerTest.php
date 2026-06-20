<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Query;

use App\Context\Procurement\Application\Port\SupplierReadRepositoryInterface;
use App\Context\Procurement\Application\Query\ListSuppliers\ListSuppliers;
use App\Context\Procurement\Application\Query\ListSuppliers\ListSuppliersHandler;
use PHPUnit\Framework\TestCase;

final class ListSuppliersHandlerTest extends TestCase
{
    public function testItReturnsAllSuppliers(): void
    {
        $readRepository = $this->createStub(SupplierReadRepositoryInterface::class);
        $readRepository->method('findAll')->willReturn([
            ['id' => 'abc', 'name' => 'Centravet'],
            ['id' => 'def', 'name' => 'Merial'],
        ]);

        $handler = new ListSuppliersHandler($readRepository);
        $result  = $handler(new ListSuppliers());

        self::assertCount(2, $result);
        self::assertSame('Centravet', $result[0]['name']);
    }
}
