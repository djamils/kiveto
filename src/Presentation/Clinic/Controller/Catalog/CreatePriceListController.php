<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Pricing\CreatePriceList\CreatePriceList;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Nouvelle grille tarifaire ». A new list never takes the default's place:
 * it is chosen on the invoice, the only way a non-default list applies today.
 */
#[Route(path: '/catalog/price-lists', name: 'clinic_catalogue_price_list_create', methods: ['POST'])]
final readonly class CreatePriceListController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private CatalogEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        try {
            $id = $this->commandBus->dispatch(new CreatePriceList(
                clinicId: $this->endpoint->clinicId(),
                name: mb_trim($request->request->getString('name')),
                isDefault: false,
            ));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok(['id' => $id]);
    }
}
