<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Package\RemovePackageComponent\RemovePackageComponent;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/catalog/packages/{packageId}/components/{componentId}/remove',
    name: 'clinic_catalogue_package_component_remove',
    methods: ['POST'],
)]
final class RemovePackageComponentController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(string $packageId, string $componentId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $this->commandBus->dispatch(new RemovePackageComponent(
                packageId: $packageId,
                componentId: $componentId,
                clinicId: $currentClinicId->toString(),
            ));
        } catch (\LogicException $exception) {
            $this->addFlash('error', CatalogErrorMessage::of($exception));

            return $this->redirectToRoute('clinic_catalogue_packages');
        }

        $this->addFlash('success', 'Prestation retirée du forfait.');

        return $this->redirectToRoute('clinic_catalogue_packages');
    }
}
