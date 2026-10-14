<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Adapter;

use App\Context\Billing\Infrastructure\Adapter\Catalog\QueryBusCounterCatalog;
use App\Context\Catalog\Application\Query\Article\GetArticleDetail\ArticleDetailView;
use App\Context\Catalog\Application\Query\Article\GetArticleDetail\GetArticleDetail;
use App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems\CatalogSearchResult;
use App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems\SearchCatalogItems;
use App\Context\Catalog\Application\Query\Pricing\ResolvePrice\ResolvePrice;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\Pricing\ValueObject\ResolvedPrice;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * The counter sells active articles that need no prescription, at the
 * clinic's price.
 */
final class QueryBusCounterCatalogTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-00000000b801';
    private const string FREE      = '01960000-0000-7000-8000-00000000b802';
    private const string RX        = '01960000-0000-7000-8000-00000000b803';
    private const string OLD       = '01960000-0000-7000-8000-00000000b804';
    private const string ACT       = '01960000-0000-7000-8000-00000000b805';
    private const string FOOD      = '01960000-0000-7000-8000-00000000b806';

    public function testTheSearchKeepsWhatTheDeskMaySellSortedByName(): void
    {
        $bus = $this->bus(function (object $query): mixed {
            if ($query instanceof SearchCatalogItems) {
                self::assertSame('front', $query->term);
                self::assertSame(40, $query->limit);

                return [
                    $this->hit(self::ACT, 'ACT', 'Consultation', 'ACTIVE', false),
                    $this->hit(self::RX, 'ARTICLE', 'Synulox', 'ACTIVE', true),
                    $this->hit(self::OLD, 'ARTICLE', 'Ancienne collerette', 'ARCHIVED', false),
                    $this->hit(self::FREE, 'ARTICLE', 'Frontline', 'ACTIVE', false),
                    $this->hit(self::FOOD, 'ARTICLE', 'Croquettes', 'ACTIVE', false),
                    'not a result',
                ];
            }

            \assert($query instanceof ResolvePrice);

            // The clinic's price list prices the food; the rest is at base price.
            return self::FOOD === $query->itemId ? $this->price(4200) : null;
        });

        $articles = new QueryBusCounterCatalog($bus)->search(self::CLINIC_ID, 'front', 10);

        self::assertSame(['Croquettes', 'Frontline'], array_map(static fn ($a): string => $a->label, $articles));
        self::assertSame(4200, $articles[0]->unitNetMinorUnits);
        self::assertSame(1890, $articles[1]->unitNetMinorUnits);
    }

    public function testASearchTheCatalogueRefusesIsEmpty(): void
    {
        $bus = $this->bus(static fn (): never => throw new \RuntimeException('down'));

        self::assertSame([], new QueryBusCounterCatalog($bus)->search(self::CLINIC_ID, '', 5));
    }

    public function testAnArticleIsLookedUpWithItsPrice(): void
    {
        $bus = $this->bus(fn (object $query): mixed => $query instanceof GetArticleDetail
            ? $this->detail(self::FREE, 'ACTIVE', false)
            : $this->price(1700));

        $article = new QueryBusCounterCatalog($bus)->article(self::CLINIC_ID, self::FREE);

        self::assertNotNull($article);
        self::assertSame(self::FREE, $article->articleId);
        self::assertSame('FRONTLINE-CB', $article->code);
        self::assertSame(1700, $article->unitNetMinorUnits);
        self::assertSame('EUR', $article->currency);
        self::assertSame('veterinary.drug.companion', $article->taxCategoryCode);
    }

    public function testPrescriptionOnlyArchivedAndUnknownArticlesAreNotSold(): void
    {
        $rx       = $this->bus(fn (object $query): mixed => $this->detail(self::RX, 'ACTIVE', true));
        $archived = $this->bus(fn (object $query): mixed => $this->detail(self::OLD, 'ARCHIVED', false));
        $unknown  = $this->bus(static fn (): never => throw new \DomainException('not found'));

        self::assertNull(new QueryBusCounterCatalog($rx)->article(self::CLINIC_ID, self::RX));
        self::assertNull(new QueryBusCounterCatalog($archived)->article(self::CLINIC_ID, self::OLD));
        self::assertNull(new QueryBusCounterCatalog($unknown)->article(self::CLINIC_ID, self::FREE));
    }

    /**
     * @param callable(object): mixed $answer
     */
    private function bus(callable $answer): QueryBusInterface
    {
        $bus = $this->createStub(QueryBusInterface::class);
        $bus->method('ask')->willReturnCallback($answer);

        return $bus;
    }

    private function hit(string $id, string $type, string $name, string $status, bool $rx): CatalogSearchResult
    {
        return new CatalogSearchResult($id, $type, $name, 'CODE', $status, 1890, 'EUR', 'veterinary.drug.companion', $rx, 'ARTICLE' === $type ? 'DRUG' : null);
    }

    private function detail(string $id, string $status, bool $rx): ArticleDetailView
    {
        return new ArticleDetailView(
            id: $id,
            clinicId: self::CLINIC_ID,
            name: 'Frontline Combo Chien pipettes',
            code: 'FRONTLINE-CB',
            kind: 'DRUG',
            gtin: null,
            taxCategoryCode: 'veterinary.drug.companion',
            basePriceMinorUnits: 1890,
            basePriceCurrency: 'EUR',
            unitOfMeasure: 'unit',
            trackStock: true,
            status: $status,
            authorizationRef: null,
            requiresPrescription: $rx,
            prescriptionClass: null,
            isControlledSubstance: false,
            createdAt: '2026-01-01',
            updatedAt: '2026-01-01',
        );
    }

    private function price(int $net): ResolvedPrice
    {
        $eur = CurrencyCode::fromString('EUR');

        return new ResolvedPrice(
            Money::fromMinorUnits($net, $eur),
            Money::fromMinorUnits($net, $eur),
            [],
            PriceListId::fromString('01960000-0000-7000-8000-00000000b8ff'),
            null,
            new \DateTimeImmutable(),
        );
    }
}
