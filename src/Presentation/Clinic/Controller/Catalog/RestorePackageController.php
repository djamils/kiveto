<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Package\RestorePackage\RestorePackage;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/catalogue/forfaits/{packageId}/restore', name: 'clinic_catalogue_package_restore', methods: ['POST'])]
final class RestorePackageController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(string $packageId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $this->commandBus->dispatch(new RestorePackage($packageId, $currentClinicId->toString()));
        } catch (\LogicException $exception) {
            $this->addFlash('error', CatalogErrorMessage::of($exception));

            return $this->redirectToRoute('clinic_catalogue_packages', ['status' => 'ARCHIVED']);
        }

        $this->addFlash('success', 'Forfait remis au catalogue.');

        return $this->redirectToRoute('clinic_catalogue_packages', ['status' => 'ARCHIVED']);
    }
}
