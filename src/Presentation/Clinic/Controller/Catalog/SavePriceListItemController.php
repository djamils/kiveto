<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Pricing\AddPriceListItem\AddPriceListItem;
use App\Context\Catalog\Application\Command\Pricing\UpdatePriceListItem\UpdatePriceListItem;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Tarifer une prestation » and the price typed in a list's table: adding an
 * override and correcting one differ only by whether the entry exists.
 */
#[Route(path: '/catalog/price-lists/{priceListId}/items', name: 'clinic_catalogue_price_item_save', methods: ['POST'])]
final readonly class SavePriceListItemController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private CatalogEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request, string $priceListId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $price     = CatalogEndpoint::cents($request, 'price');
        $priceMode = CatalogEndpoint::priceMode($request);

        if (null === $price) {
            return $this->endpoint->refuse('Le prix saisi est invalide.');
        }

        if (null === $priceMode) {
            return $this->endpoint->refuse(CatalogEndpoint::INVALID_PRICE_MODE);
        }

        $clinicId = $this->endpoint->clinicId();
        $entryId  = CatalogEndpoint::text($request, 'entry_id');
        $itemId   = CatalogEndpoint::text($request, 'item_id');

        try {
            if (null !== $entryId) {
                $this->commandBus->dispatch(new UpdatePriceListItem(
                    priceListId: $priceListId,
                    priceListItemId: $entryId,
                    clinicId: $clinicId,
                    priceMinorUnits: $price,
                    priceCurrency: 'EUR',
                    priceMode: $priceMode,
                    taxCategoryOverride: null,
                ));
            } elseif (null !== $itemId) {
                $this->commandBus->dispatch(new AddPriceListItem(
                    priceListId: $priceListId,
                    clinicId: $clinicId,
                    itemType: $request->request->getString('item_type', 'ACT'),
                    itemId: $itemId,
                    priceMinorUnits: $price,
                    priceCurrency: 'EUR',
                    priceMode: $priceMode,
                    taxCategoryOverride: null,
                ));
            } else {
                return $this->endpoint->refuse('Choisissez la prestation à tarifer.');
            }
        } catch (\ValueError $exception) {
            return $this->endpoint->refuse($exception);
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
