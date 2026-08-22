<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogPage;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListPackageComponents;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListPackages;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\PackageListRow;
use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogPageContext;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/catalogue/forfaits', name: 'clinic_catalogue_packages', methods: ['GET'])]
final class ListPackagesController extends AbstractController
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
        $modes    = array_map(static fn (PackagePricingMode $m): string => $m->value, PackagePricingMode::cases());
        $criteria = $this->context->criteria($request, $modes);

        $page = $this->queryBus->ask(new ListPackages($clinicId, $criteria));
        \assert($page instanceof CatalogPage);

        // The composition is what makes a package readable, so it is loaded for
        // the page being shown rather than behind a second click.
        $components = [];

        foreach ($page->items as $package) {
            \assert($package instanceof PackageListRow);
            $rows                     = $this->queryBus->ask(new ListPackageComponents($clinicId, $package->id));
            $components[$package->id] = \is_array($rows) ? $rows : [];
        }

        return $this->render('clinic/catalog/packages.html.twig', [
            'packages'      => $page->items,
            'components'    => $components,
            'page'          => $page,
            'criteria'      => $criteria,
            'counters'      => $this->context->counters($clinicId),
            'modes'         => $modes,
            'taxCategories' => $this->context->taxCategories(),
            'pageSizes'     => CatalogPageContext::PAGE_SIZES,
            'queryState'    => $this->context->queryState($request),
        ]);
    }
}
