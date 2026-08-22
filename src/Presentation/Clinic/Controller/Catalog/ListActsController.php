<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogPage;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListActs;
use App\Context\Catalog\Domain\Act\ValueObject\ActCategory;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogPageContext;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/catalogue', name: 'clinic_catalogue', methods: ['GET'])]
final class ListActsController extends AbstractController
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

        $clinicId   = $currentClinicId->toString();
        $categories = array_map(static fn (ActCategory $c): string => $c->value, ActCategory::cases());
        $criteria   = $this->context->criteria($request, $categories);

        $page = $this->queryBus->ask(new ListActs($clinicId, $criteria));
        \assert($page instanceof CatalogPage);

        return $this->render('clinic/catalog/acts.html.twig', [
            'acts'          => $page->items,
            'page'          => $page,
            'criteria'      => $criteria,
            'counters'      => $this->context->counters($clinicId),
            'categories'    => $categories,
            'taxCategories' => $this->context->taxCategories(),
            'pageSizes'     => CatalogPageContext::PAGE_SIZES,
            'queryState'    => $this->context->queryState($request),
        ]);
    }
}
