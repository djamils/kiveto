<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Act\ChangeActBasePrice\ChangeActBasePrice;
use App\Context\Catalog\Application\Command\Act\ChangeActTaxCategory\ChangeActTaxCategory;
use App\Context\Catalog\Application\Command\Act\RenameAct\RenameAct;
use App\Context\Catalog\Application\Command\Act\ReviseActDetails\ReviseActDetails;
use App\Context\Catalog\Application\Query\Act\GetActDetail\ActDetailView;
use App\Context\Catalog\Application\Query\Act\GetActDetail\GetActDetail;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Enregistrer » on an act's sheet, and the bulk bar's price revision and
 * family change. Every field is optional: the domain changes a name, a price,
 * a tax category and the practical details one at a time, so only what was
 * sent and actually moved is dispatched.
 */
#[Route(path: '/catalog/acts/{actId}', name: 'clinic_catalogue_act_update', methods: ['POST'])]
final readonly class UpdateActController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private CatalogEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request, string $actId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $clinicId = $this->endpoint->clinicId();
        $form     = $request->request;

        try {
            $act = $this->queryBus->ask(new GetActDetail($actId, $clinicId));
            \assert($act instanceof ActDetailView);

            $name = CatalogEndpoint::text($request, 'name');

            if (null !== $name && $name !== $act->name) {
                $this->commandBus->dispatch(new RenameAct($actId, $clinicId, $name));
            }

            if ($form->has('net')) {
                $net = CatalogEndpoint::cents($request, 'net');

                if (null === $net) {
                    return $this->endpoint->refuse('Le tarif saisi est invalide.');
                }

                if ($net !== $act->basePriceMinorUnits) {
                    $this->commandBus->dispatch(new ChangeActBasePrice($actId, $clinicId, $net, $act->basePriceCurrency));
                }
            }

            $tax = CatalogEndpoint::text($request, 'tax_category');

            if (null !== $tax && $tax !== $act->taxCategoryCode) {
                $this->commandBus->dispatch(new ChangeActTaxCategory($actId, $clinicId, $tax));
            }

            if ($form->has('category')) {
                $this->commandBus->dispatch(new ReviseActDetails(
                    actId: $actId,
                    clinicId: $clinicId,
                    description: CatalogEndpoint::text($request, 'description'),
                    category: $form->getString('category'),
                    estimatedDurationMinutes: $form->getInt('duration', $act->estimatedDurationMinutes),
                    requiresAnesthesia: $form->getBoolean('requires_anesthesia'),
                ));
            }
        } catch (\ValueError $exception) {
            return $this->endpoint->refuse($exception);
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
