<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Pricing\RemovePriceListItem\RemovePriceListItem;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/catalogue/tarifs/{priceListId}/lignes/{entryId}/remove',
    name: 'clinic_catalogue_price_item_remove',
    methods: ['POST'],
)]
final class RemovePriceListItemController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(string $priceListId, string $entryId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $this->commandBus->dispatch(new RemovePriceListItem(
                priceListId: $priceListId,
                priceListItemId: $entryId,
                clinicId: $currentClinicId->toString(),
            ));
        } catch (\LogicException $exception) {
            $this->addFlash('error', CatalogErrorMessage::of($exception));

            return $this->redirectToRoute('clinic_catalogue_price_lists', ['list' => $priceListId]);
        }

        $this->addFlash('success', 'Tarif retiré. La prestation repasse à son tarif de base.');

        return $this->redirectToRoute('clinic_catalogue_price_lists', ['list' => $priceListId]);
    }
}
