<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\SearchCounterArticles;

use App\Context\Billing\Application\Port\CounterArticleDto;
use App\Context\Billing\Application\Port\CounterCatalogInterface;
use App\Context\Billing\Application\Port\TaxResolverInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SearchCounterArticlesHandler
{
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
            $tax = $this->taxResolver->resolve($article->unitNetMinorUnits, $article->currency, $article->taxCategoryCode, $query->clinicId);

            return new CounterArticleView(
                articleId: $article->articleId,
                label: $article->label,
                code: $article->code,
                unitNetMinorUnits: $article->unitNetMinorUnits,
                unitGrossMinorUnits: $tax->grossMinorUnits,
                taxRatePercent: $tax->ratePercent,
                currency: $article->currency,
            );
        }, $this->catalog->search($query->clinicId, mb_trim($query->term), max(1, min(50, $query->limit))));
    }
}
