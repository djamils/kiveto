<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Application\Query;

use App\Context\Billing\Application\Port\CounterCatalogInterface;
use App\Context\Billing\Application\Port\PrescriptionOnlyArticleDto;
use App\Context\Billing\Application\Query\SearchPrescriptionOnlyArticles\SearchPrescriptionOnlyArticles;
use App\Context\Billing\Application\Query\SearchPrescriptionOnlyArticles\SearchPrescriptionOnlyArticlesHandler;
use PHPUnit\Framework\TestCase;

/**
 * The medicines the counter points to the ordonnances for.
 */
final class SearchPrescriptionOnlyArticlesHandlerTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-00000000c901';

    public function testATypedSearchFindsThem(): void
    {
        $found   = [new PrescriptionOnlyArticleDto('01960000-0000-7000-8000-00000000c902', 'Synulox 250 mg')];
        $catalog = $this->createMock(CounterCatalogInterface::class);
        $catalog->expects(self::once())->method('prescriptionOnly')->with(self::CLINIC_ID, 'syn', 20)->willReturn($found);

        self::assertSame($found, new SearchPrescriptionOnlyArticlesHandler($catalog)(new SearchPrescriptionOnlyArticles(self::CLINIC_ID, ' syn ', 99)));
    }

    public function testTooShortASearchFindsNone(): void
    {
        $catalog = $this->createMock(CounterCatalogInterface::class);
        $catalog->expects(self::never())->method('prescriptionOnly');

        self::assertSame([], new SearchPrescriptionOnlyArticlesHandler($catalog)(new SearchPrescriptionOnlyArticles(self::CLINIC_ID, ' s ')));
    }
}
