<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Query\Catalog\SearchCatalogItems;

use App\Context\Catalog\Application\Port\CatalogSearchRepositoryInterface;
use App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems\CatalogSearchResult;
use App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems\SearchCatalogItems;
use App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems\SearchCatalogItemsHandler;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class SearchCatalogItemsHandlerTest extends TestCase
{
    private const string CLINIC_ID = '01950000-0000-7000-0000-000000000002';

    public function testHandleForwardsCriteriaAndReturnsResultsVerbatim(): void
    {
        $expected = [
            new CatalogSearchResult(
                id: '01950000-0000-7000-0000-000000000010',
                type: 'ACT',
                name: 'Consultation standard',
                code: 'CONS-STD',
                status: 'ACTIVE',
                basePriceMinorUnits: 5000,
                basePriceCurrency: 'EUR',
                taxCategoryCode: 'veterinary.act.consultation',
                requiresPrescription: false,
            ),
            new CatalogSearchResult(
                id: '01950000-0000-7000-0000-000000000011',
                type: 'ARTICLE',
                name: 'Amoxicilline 500mg',
                code: 'ART-AMX',
                status: 'ACTIVE',
                basePriceMinorUnits: 1250,
                basePriceCurrency: 'EUR',
                taxCategoryCode: 'veterinary.drug.prescription',
                requiresPrescription: true,
            ),
        ];

        $repository = $this->createMock(CatalogSearchRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('search')
            ->with(
                'amox',
                self::callback(static fn (ClinicId $id) => self::CLINIC_ID === $id->toString()),
                7,
            )
            ->willReturn($expected)
        ;

        $handler = new SearchCatalogItemsHandler($repository);
        $results = $handler(new SearchCatalogItems(self::CLINIC_ID, 'amox', 7));

        self::assertSame($expected, $results);
    }

    public function testHandleForwardsTheDefaultLimit(): void
    {
        $repository = $this->createMock(CatalogSearchRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('search')
            ->with('vac', self::isInstanceOf(ClinicId::class), 20)
            ->willReturn([])
        ;

        $handler = new SearchCatalogItemsHandler($repository);

        self::assertSame([], $handler(new SearchCatalogItems(self::CLINIC_ID, 'vac')));
    }
}
