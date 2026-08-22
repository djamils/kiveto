<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Package\CreatePackage\CreatePackage;
use App\Presentation\Clinic\Form\Shared\AmountParser;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/catalog/packages', name: 'clinic_catalogue_package_create', methods: ['POST'])]
final class CreatePackageController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $mode = $request->request->getString('pricing_mode', 'SUM_OF_COMPONENTS');

        // A fixed-price package needs its price up front; a sum-of-components
        // one refuses to carry any.
        $price = 'FIXED_PRICE' === $mode
            ? AmountParser::toMinorUnits($request->request->getString('fixed_price'))
            : null;

        if ('FIXED_PRICE' === $mode && null === $price) {
            $this->addFlash('error', 'Le tarif forfaitaire saisi est invalide.');

            return $this->redirectToRoute('clinic_catalogue_packages');
        }

        try {
            $this->commandBus->dispatch(new CreatePackage(
                clinicId: $currentClinicId->toString(),
                name: mb_trim($request->request->getString('name')),
                code: mb_strtoupper(mb_trim($request->request->getString('code'))),
                taxCategoryCode: $request->request->getString('tax_category'),
                pricingMode: $mode,
                fixedPriceMinorUnits: $price,
                fixedPriceCurrency: null !== $price ? 'EUR' : null,
            ));
        } catch (\ValueError $exception) {
            return $this->refuse($exception);
        } catch (\LogicException $exception) {
            return $this->refuse($exception);
        }

        $this->addFlash('success', 'Forfait créé. Ajoutez-lui ses prestations.');

        return $this->redirectToRoute('clinic_catalogue_packages');
    }

    /**
     * The domain refused the change, or the form carried a value no select
     * offers. Either way the counter is told in French and stays where it was.
     */
    private function refuse(\Throwable $exception): Response
    {
        $this->addFlash('error', CatalogErrorMessage::of($exception));

        return $this->redirectToRoute('clinic_catalogue_packages');
    }
}
