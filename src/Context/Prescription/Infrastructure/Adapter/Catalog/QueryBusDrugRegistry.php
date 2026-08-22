<?php

declare(strict_types=1);

namespace App\Context\Prescription\Infrastructure\Adapter\Catalog;

use App\Context\Catalog\Application\Query\Article\GetArticleDetail\ArticleDetailView;
use App\Context\Catalog\Application\Query\Article\GetArticleDetail\GetArticleDetail;
use App\Context\Prescription\Application\Port\DrugDetailsDto;
use App\Context\Prescription\Application\Port\DrugRegistryInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationDetail\GetMarketingAuthorizationDetail;
use App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationDetail\MarketingAuthorizationDetailView;

/**
 * What has to be printed about a medicine, gathered in two hops: the clinic's
 * catalog says how the article is classified and which marketing authorization
 * it refers to, the registry says what the medicine actually is.
 *
 * Neither hop is allowed to hold up the ordonnance: an article that has been
 * archived, or an authorization that has since been withdrawn, leaves the line
 * with what the practitioner typed and nothing more.
 */
final readonly class QueryBusDrugRegistry implements DrugRegistryInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function detailsOf(string $articleId, string $clinicId): DrugDetailsDto
    {
        $article = $this->article($articleId, $clinicId);

        if (null === $article) {
            return DrugDetailsDto::unknown();
        }

        $authorization = null !== $article->authorizationRef
            ? $this->authorization($article->authorizationRef)
            : null;

        return new DrugDetailsDto(
            pharmaceuticalForm: $authorization?->pharmaceuticalForm,
            activeSubstances: null !== $authorization ? $this->substancesOf($authorization) : [],
            prescriptionClass: $article->prescriptionClass ?? 'NONE',
        );
    }

    private function article(string $articleId, string $clinicId): ?ArticleDetailView
    {
        try {
            $result = $this->queryBus->ask(new GetArticleDetail($articleId, $clinicId));
        } catch (\Throwable) {
            return null;
        }

        return $result instanceof ArticleDetailView ? $result : null;
    }

    private function authorization(string $authorizationId): ?MarketingAuthorizationDetailView
    {
        try {
            $result = $this->queryBus->ask(new GetMarketingAuthorizationDetail($authorizationId));
        } catch (\Throwable) {
            return null;
        }

        return $result instanceof MarketingAuthorizationDetailView ? $result : null;
    }

    /**
     * @return list<string>
     */
    private function substancesOf(MarketingAuthorizationDetailView $authorization): array
    {
        $substances = [];

        // The registry hands them over as plain labels.
        foreach ($authorization->activeSubstances as $substance) {
            if (\is_string($substance) && '' !== mb_trim($substance)) {
                $substances[] = mb_trim($substance);
            }
        }

        return $substances;
    }
}
