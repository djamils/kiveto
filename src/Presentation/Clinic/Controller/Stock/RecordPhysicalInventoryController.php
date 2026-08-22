<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Command\RecordPhysicalInventory\RecordPhysicalInventory;
use App\Context\Inventory\Application\Query\GetStockItem\GetStockItem;
use App\Context\Inventory\Application\Query\GetStockItem\StockItemView;
use App\Presentation\Clinic\Form\Shared\QuantityParser;
use App\Presentation\Clinic\ViewModel\Stock\StockErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A lot was counted: what is on the shelf wins, and the difference is recorded
 * as an adjustment rather than silently absorbed.
 */
#[Route(path: '/stock/items/{stockItemId}/counts', name: 'clinic_stock_count', methods: ['POST'])]
final class RecordPhysicalInventoryController extends AbstractController
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

        $counted = QuantityParser::normalize($request->request->getString('quantity'));
        $lotId   = mb_trim($request->request->getString('lot_id'));

        if (null === $counted) {
            return $this->refuse('La quantité comptée est invalide.');
        }

        if ('' === $lotId) {
            return $this->refuse('Choisissez le lot compté.');
        }

        $user = $this->getUser();

        try {
            $this->commandBus->dispatch(new RecordPhysicalInventory(
                stockItemId: $stockItemId,
                clinicId: $currentClinicId->toString(),
                lotId: $lotId,
                newLotQuantityAmount: $counted,
                newLotQuantityUnit: $this->unitOf($stockItemId, $currentClinicId->toString()),
                reason: 'PHYSICAL_INVENTORY',
                occurredAt: null,
                performedBy: $user instanceof SecurityUser ? $user->id() : null,
            ));
        } catch (\ValueError $exception) {
            return $this->refuse(StockErrorMessage::of($exception));
        } catch (\LogicException $exception) {
            return $this->refuse(StockErrorMessage::of($exception));
        }

        $this->addFlash('success', 'Inventaire enregistré.');

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
