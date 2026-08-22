<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Package\ChangePackageFixedPrice\ChangePackageFixedPrice;
use App\Presentation\Clinic\Form\Shared\AmountParser;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Only the fixed price of a package can be changed: its name, its code and its
 * mode are what the invoices already issued refer to.
 */
#[Route(path: '/catalogue/forfaits/{packageId}', name: 'clinic_catalogue_package_update', methods: ['POST'])]
final class UpdatePackageController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request, string $packageId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $price = AmountParser::toMinorUnits($request->request->getString('fixed_price'));

        if (null === $price) {
            $this->addFlash('error', 'Le tarif forfaitaire saisi est invalide.');

            return $this->redirectToRoute('clinic_catalogue_packages');
        }

        try {
            $this->commandBus->dispatch(new ChangePackageFixedPrice(
                packageId: $packageId,
                clinicId: $currentClinicId->toString(),
                fixedPriceMinorUnits: $price,
                fixedPriceCurrency: 'EUR',
            ));
        } catch (\LogicException $exception) {
            $this->addFlash('error', CatalogErrorMessage::of($exception));

            return $this->redirectToRoute('clinic_catalogue_packages');
        }

        $this->addFlash('success', 'Tarif du forfait mis à jour.');

        return $this->redirectToRoute('clinic_catalogue_packages');
    }
}
