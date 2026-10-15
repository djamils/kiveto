<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Infrastructure\Adapter\Catalog;

use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ActListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogPage;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListActs;
use App\Context\Consultation\Infrastructure\Adapter\Catalog\QueryBusActCategoryProvider;
use App\Shared\Application\Bus\QueryBusInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class QueryBusActCategoryProviderTest extends TestCase
{
    private const string CLINIC = '22222222-2222-4222-8222-222222222222';

    public function testNoCategoryAsksNothing(): void
    {
        $bus = $this->createMock(QueryBusInterface::class);
        $bus->expects(self::never())->method('ask');

        self::assertSame([], new QueryBusActCategoryProvider($bus)->actCodesByCategory(self::CLINIC, []));
    }

    public function testTheActsOfTheCategoriesAreKeyedByCode(): void
    {
        $bus = $this->createMock(QueryBusInterface::class);
        $bus->expects(self::once())->method('ask')
            ->with(self::callback(static fn (ListActs $q): bool => self::CLINIC === $q->clinicId
                && ['SURGERY', 'LABORATORY'] === $q->criteria->kinds
                && null === $q->criteria->status
                && 500 === $q->criteria->perPage))
            ->willReturn(new CatalogPage([$this->act('STERIL', 'SURGERY'), $this->act('BILAN', 'LABORATORY')], 2, 1, 500))
        ;

        self::assertSame(
            ['STERIL' => 'SURGERY', 'BILAN' => 'LABORATORY'],
            new QueryBusActCategoryProvider($bus)->actCodesByCategory(self::CLINIC, ['SURGERY', 'LABORATORY']),
        );
    }

    public function testAnUnexpectedAnswerMeansNoCode(): void
    {
        $bus = $this->createMock(QueryBusInterface::class);
        $bus->method('ask')->willReturn(null);

        self::assertSame([], new QueryBusActCategoryProvider($bus)->actCodesByCategory(self::CLINIC, ['SURGERY']));
    }

    private function act(string $code, string $category): ActListRow
    {
        return new ActListRow('id-' . $code, $code, $code, null, $category, 'veterinary.act', 1000, 'EUR', 15, false, 'ACTIVE');
    }
}
