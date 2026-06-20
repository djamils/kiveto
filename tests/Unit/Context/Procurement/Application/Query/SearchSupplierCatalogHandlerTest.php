<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Query;

use App\Context\Procurement\Application\Port\SupplierCatalogReadRepositoryInterface;
use App\Context\Procurement\Application\Query\SearchSupplierCatalog\SearchSupplierCatalog;
use App\Context\Procurement\Application\Query\SearchSupplierCatalog\SearchSupplierCatalogHandler;
use PHPUnit\Framework\TestCase;

final class SearchSupplierCatalogHandlerTest extends TestCase
{
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';

    public function testItSearchesCatalog(): void
    {
        $readRepository = $this->createStub(SupplierCatalogReadRepositoryInterface::class);
        $readRepository->method('search')->with('amoxicilline', self::SUPPLIER_UUID)
            ->willReturn([['id' => 'entry-1', 'name' => 'Amoxicilline 500mg']])
        ;

        $handler = new SearchSupplierCatalogHandler($readRepository);
        $result  = $handler(new SearchSupplierCatalog(term: 'amoxicilline', supplierId: self::SUPPLIER_UUID));

        self::assertCount(1, $result);
        self::assertSame('Amoxicilline 500mg', $result[0]['name']);
    }

    public function testItReturnsEmptyArrayWhenNoMatches(): void
    {
        $readRepository = $this->createStub(SupplierCatalogReadRepositoryInterface::class);
        $readRepository->method('search')->willReturn([]);

        $handler = new SearchSupplierCatalogHandler($readRepository);
        $result  = $handler(new SearchSupplierCatalog(term: 'unknown', supplierId: self::SUPPLIER_UUID));

        self::assertSame([], $result);
    }
}
