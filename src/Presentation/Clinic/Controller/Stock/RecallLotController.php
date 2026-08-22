<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Command\MarkLotAsRecalled\MarkLotAsRecalled;
use App\Presentation\Clinic\ViewModel\Stock\StockErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A lot is withdrawn from use. Its quantity leaves the stock and the reason is
 * kept: this is the kind of thing an inspection asks about.
 */
#[Route(
    path: '/stock/items/{stockItemId}/lots/{lotId}/recall',
    name: 'clinic_stock_lot_recall',
    methods: ['POST'],
)]
final class RecallLotController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request, string $stockItemId, string $lotId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $reason = mb_trim($request->request->getString('reason'));

        if ('' === $reason) {
            return $this->refuse('Indiquez le motif du rappel.');
        }

        try {
            $this->commandBus->dispatch(new MarkLotAsRecalled(
                stockItemId: $stockItemId,
                clinicId: $currentClinicId->toString(),
                lotId: $lotId,
                reason: $reason,
            ));
        } catch (\ValueError $exception) {
            return $this->refuse(StockErrorMessage::of($exception));
        } catch (\LogicException $exception) {
            return $this->refuse(StockErrorMessage::of($exception));
        }

        $this->addFlash('success', 'Lot rappelé et retiré du stock.');

        return $this->redirectToRoute('clinic_stock');
    }

    private function refuse(string $message): Response
    {
        $this->addFlash('error', $message);

        return $this->redirectToRoute('clinic_stock');
    }
}
