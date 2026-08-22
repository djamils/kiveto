<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Pricing\AddPriceListItem\AddPriceListItem;
use App\Context\Catalog\Application\Command\Pricing\UpdatePriceListItem\UpdatePriceListItem;
use App\Presentation\Clinic\Form\Shared\AmountParser;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * One form for both: adding an override and correcting one differ only by
 * whether the entry already exists.
 */
#[Route(path: '/catalogue/tarifs/{priceListId}/lignes', name: 'clinic_catalogue_price_item_save', methods: ['POST'])]
final class SavePriceListItemController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request, string $priceListId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $price    = AmountParser::toMinorUnits($request->request->getString('net_price'));
        $itemId   = mb_trim($request->request->getString('item_id'));
        $entryId  = mb_trim($request->request->getString('entry_id'));
        $override = mb_trim($request->request->getString('tax_category_override'));

        if (null === $price) {
            $this->addFlash('error', 'Le tarif saisi est invalide.');

            return $this->redirectToRoute('clinic_catalogue_price_lists', ['list' => $priceListId]);
        }

        try {
            if ('' !== $entryId) {
                $this->commandBus->dispatch(new UpdatePriceListItem(
                    priceListId: $priceListId,
                    priceListItemId: $entryId,
                    clinicId: $clinicId,
                    netPriceMinorUnits: $price,
                    netPriceCurrency: 'EUR',
                    taxCategoryOverride: '' !== $override ? $override : null,
                ));
            } else {
                if ('' === $itemId) {
                    $this->addFlash('error', 'Choisissez la prestation à tarifer.');

                    return $this->redirectToRoute('clinic_catalogue_price_lists', ['list' => $priceListId]);
                }

                $this->commandBus->dispatch(new AddPriceListItem(
                    priceListId: $priceListId,
                    clinicId: $clinicId,
                    itemType: $request->request->getString('item_type', 'ACT'),
                    itemId: $itemId,
                    netPriceMinorUnits: $price,
                    netPriceCurrency: 'EUR',
                    taxCategoryOverride: '' !== $override ? $override : null,
                ));
            }
        } catch (\ValueError $exception) {
            return $this->refuse($exception, $priceListId);
        } catch (\LogicException $exception) {
            return $this->refuse($exception, $priceListId);
        }

        $this->addFlash('success', 'Tarif enregistré.');

        return $this->redirectToRoute('clinic_catalogue_price_lists', ['list' => $priceListId]);
    }

    /**
     * The domain refused the change, or the form carried a value no select
     * offers. Either way the counter is told in French and stays where it was.
     */
    private function refuse(\Throwable $exception, string $priceListId): Response
    {
        $this->addFlash('error', CatalogErrorMessage::of($exception));

        return $this->redirectToRoute('clinic_catalogue_price_lists', ['list' => $priceListId]);
    }
}
