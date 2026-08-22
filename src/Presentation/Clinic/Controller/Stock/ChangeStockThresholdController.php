<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Command\ChangeStockThreshold\ChangeStockThreshold;
use App\Context\Inventory\Application\Query\GetStockItem\GetStockItem;
use App\Context\Inventory\Application\Query\GetStockItem\StockItemView;
use App\Presentation\Clinic\Form\Shared\QuantityParser;
use App\Presentation\Clinic\ViewModel\Stock\StockErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The quantity below which the clinic wants to be warned.
 */
#[Route(path: '/stock/items/{stockItemId}/threshold', name: 'clinic_stock_threshold', methods: ['POST'])]
final class ChangeStockThresholdController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request, string $stockItemId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $threshold = QuantityParser::normalize($request->request->getString('threshold'));

        if (null === $threshold) {
            return $this->refuse('Le seuil saisi est invalide.');
        }

        try {
            $this->commandBus->dispatch(new ChangeStockThreshold(
                stockItemId: $stockItemId,
                clinicId: $currentClinicId->toString(),
                thresholdAmount: $threshold,
                thresholdUnit: $this->unitOf($stockItemId, $currentClinicId->toString()),
                thresholdType: 'ABSOLUTE',
            ));
        } catch (\ValueError $exception) {
            return $this->refuse(StockErrorMessage::of($exception));
        } catch (\LogicException $exception) {
            return $this->refuse(StockErrorMessage::of($exception));
        }

        $this->addFlash('success', 'Seuil d’alerte mis à jour.');

        return $this->redirectToRoute('clinic_stock');
    }

    /**
     * The unit is a property of the stock item, never of the request: a form
     * that posted the wrong one would record a quantity in a unit the shelf
     * does not use.
     */
    private function unitOf(string $stockItemId, string $clinicId): string
    {
        $item = $this->queryBus->ask(new GetStockItem($stockItemId, $clinicId));

        \assert($item instanceof StockItemView);

        return $item->totalOnHandUnit;
    }

    private function refuse(string $message): Response
    {
        $this->addFlash('error', $message);

        return $this->redirectToRoute('clinic_stock');
    }
}
