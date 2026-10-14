<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Act\CreateAct\CreateAct;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** « Ajouter au catalogue » on a new act's sheet. */
#[Route(path: '/catalog/acts', name: 'clinic_catalogue_act_create', methods: ['POST'])]
final readonly class CreateActController
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

        $net = CatalogEndpoint::cents($request, 'net');

        if (null === $net) {
            return $this->endpoint->refuse('Le tarif saisi est invalide.');
        }

        try {
            $id = $this->commandBus->dispatch(new CreateAct(
                clinicId: $this->endpoint->clinicId(),
                name: mb_trim($request->request->getString('name')),
                code: mb_strtoupper(mb_trim($request->request->getString('code'))),
                description: CatalogEndpoint::text($request, 'description'),
                category: $request->request->getString('category'),
                taxCategoryCode: $request->request->getString('tax_category'),
                basePriceMinorUnits: $net,
                basePriceCurrency: 'EUR',
                estimatedDurationMinutes: $request->request->getInt('duration', 20),
                requiresAnesthesia: $request->request->getBoolean('requires_anesthesia'),
            ));
        } catch (\ValueError $exception) {
            return $this->endpoint->refuse($exception);
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok(['id' => $id]);
    }
}
