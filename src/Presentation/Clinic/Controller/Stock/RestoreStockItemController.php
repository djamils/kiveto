<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Command\RestoreStockItem\RestoreStockItem;
use App\Presentation\Clinic\ViewModel\Stock\StockErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/stock/items/{stockItemId}/restore', name: 'clinic_stock_restore', methods: ['POST'])]
final class RestoreStockItemController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(string $stockItemId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $this->commandBus->dispatch(new RestoreStockItem(
                stockItemId: $stockItemId,
                clinicId: $currentClinicId->toString(),
            ));
        } catch (\LogicException $exception) {
            $this->addFlash('error', StockErrorMessage::of($exception));

            return $this->redirectToRoute('clinic_stock');
        }

        $this->addFlash('success', 'Suivi du stock rétabli.');

        return $this->redirectToRoute('clinic_stock');
    }
}
