<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\SearchCounterArticles;

use App\Context\Billing\Application\Port\CounterArticleDto;
use App\Context\Billing\Application\Port\CounterCatalogInterface;
use App\Context\Billing\Application\Port\TaxResolverInterface;
use App\Context\Billing\Domain\Service\InvoiceAmountCalculator;
use App\Context\Billing\Domain\ValueObject\CalculationLine;
use App\Context\Billing\Domain\ValueObject\CalculationMode;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\CurrencyDecimals;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SearchCounterArticlesHandler
{
    private const int CURRENCY_DECIMALS = 2;

    public function __construct(
        private CounterCatalogInterface $catalog,
        private TaxResolverInterface $taxResolver,
    ) {
    }

    /**
     * @return list<CounterArticleView>
     */
    public function __invoke(SearchCounterArticles $query): array
    {
        return array_map(function (CounterArticleDto $article) use ($query): CounterArticleView {
            $rate    = $this->taxResolver->resolve($query->clinicId, $article->taxCategoryCode, $article->currency)->ratePercent ?? '0';
            $amounts = new InvoiceAmountCalculator()->calculate(
                CalculationMode::INCLUDING_TAX_FIRST,
                [new CalculationLine(
                    Money::fromMinorUnits($article->unitPriceMinorUnits, CurrencyCode::fromString($article->currency)),
                    PriceMode::from($article->unitPriceMode),
                    '1',
                    $rate,
                )],
                null,
                CurrencyCode::fromString($article->currency),
                CurrencyDecimals::of(self::CURRENCY_DECIMALS),
            );

            // What one unit costs a private client at the desk.
            return new CounterArticleView(
                articleId: $article->articleId,
                label: $article->label,
                code: $article->code,
                unitNetMinorUnits: $amounts->taxBasis()->minorUnits(),
                unitGrossMinorUnits: $amounts->totalGross()->minorUnits(),
                taxRatePercent: $rate,
                currency: $article->currency,
            );
        }, $this->catalog->search($query->clinicId, mb_trim($query->term), max(1, min(50, $query->limit))));
    }
}
