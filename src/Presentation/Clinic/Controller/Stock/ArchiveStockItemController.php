<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Command\ArchiveStockItem\ArchiveStockItem;
use App\Presentation\Clinic\ViewModel\Stock\StockErrorMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The clinic stops following this article. The domain refuses while stock
 * remains — an article cannot leave the shelves by being forgotten.
 */
#[Route(path: '/stock/items/{stockItemId}/archive', name: 'clinic_stock_archive', methods: ['POST'])]
final class ArchiveStockItemController extends AbstractController
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
            $this->commandBus->dispatch(new ArchiveStockItem(
                stockItemId: $stockItemId,
                clinicId: $currentClinicId->toString(),
            ));
        } catch (\LogicException $exception) {
            $this->addFlash('error', StockErrorMessage::of($exception));

            return $this->redirectToRoute('clinic_stock');
        }

        $this->addFlash('success', 'Suivi du stock archivé.');

        return $this->redirectToRoute('clinic_stock');
    }
}
