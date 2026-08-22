<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Package\AddPackageComponent\AddPackageComponent;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/catalog/packages/{packageId}/components',
    name: 'clinic_catalogue_package_component_add',
    methods: ['POST'],
)]
final class AddPackageComponentController extends AbstractController
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

        $itemId = mb_trim($request->request->getString('item_id'));

        if ('' === $itemId) {
            $this->addFlash('error', 'Choisissez une prestation à ajouter.');

            return $this->redirectToRoute('clinic_catalogue_packages');
        }

        try {
            $this->commandBus->dispatch(new AddPackageComponent(
                packageId: $packageId,
                clinicId: $currentClinicId->toString(),
                itemType: $request->request->getString('item_type', 'ACT'),
                itemId: $itemId,
                quantity: max(1, $request->request->getInt('quantity', 1)),
                weight: $request->request->getInt('weight'),
            ));
        } catch (\ValueError $exception) {
            return $this->refuse($exception);
        } catch (\LogicException $exception) {
            return $this->refuse($exception);
        }

        $this->addFlash('success', 'Prestation ajoutée au forfait.');

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
