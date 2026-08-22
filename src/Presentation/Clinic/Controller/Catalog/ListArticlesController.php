<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogPage;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListArticles;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogPageContext;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/catalogue/articles', name: 'clinic_catalogue_articles', methods: ['GET'])]
final class ListArticlesController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CatalogPageContext $context,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $kinds    = array_map(static fn (ArticleKind $k): string => $k->value, ArticleKind::cases());
        $criteria = $this->context->criteria($request, $kinds);

        $page = $this->queryBus->ask(new ListArticles($clinicId, $criteria));
        \assert($page instanceof CatalogPage);

        return $this->render('clinic/catalog/articles.html.twig', [
            'articles'      => $page->items,
            'page'          => $page,
            'criteria'      => $criteria,
            'counters'      => $this->context->counters($clinicId),
            'kinds'         => $kinds,
            'taxCategories' => $this->context->taxCategories(),
            'pageSizes'     => CatalogPageContext::PAGE_SIZES,
            'queryState'    => $this->context->queryState($request),
        ]);
    }
}
