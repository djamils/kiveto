<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Query\SearchCounterArticles\CounterArticleView;
use App\Context\Billing\Application\Query\SearchCounterArticles\SearchCounterArticles;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The articles the « Vente au comptoir » window offers, with their price all
 * taxes included, as JSON.
 */
#[Route(path: '/billing/counter-articles', name: 'clinic_counter_articles', methods: ['GET'])]
final class CounterArticlesController extends AbstractController
{
    private const int LIMIT = 30;

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $articles = $this->queryBus->ask(new SearchCounterArticles(
            clinicId: $currentClinicId->toString(),
            term: mb_substr(mb_trim($request->query->getString('q')), 0, 100),
            limit: self::LIMIT,
        ));

        $items = [];

        foreach (\is_array($articles) ? $articles : [] as $article) {
            if ($article instanceof CounterArticleView) {
                $items[] = ['k' => $article->articleId, 'l' => $article->label, 'code' => $article->code, 'p' => $article->unitGrossMinorUnits];
            }
        }

        return new JsonResponse(['items' => $items]);
    }
}
