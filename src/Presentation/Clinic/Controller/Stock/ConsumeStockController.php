<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Command\ConsumeStock\ConsumeStock;
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
 * Stock leaves the shelf. The lot drawn down is decided by the domain, which
 * takes the one expiring first.
 */
#[Route(path: '/stock/items/{stockItemId}/withdrawals', name: 'clinic_stock_consume', methods: ['POST'])]
final class ConsumeStockController extends AbstractController
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

        $quantity = QuantityParser::normalize($request->request->getString('quantity'));

        if (null === $quantity || '0.000000' === $quantity) {
            return $this->refuse('La quantité sortie est invalide.');
        }

        $note      = mb_trim($request->request->getString('note'));
        $reference = mb_trim($request->request->getString('reference'));
        $user      = $this->getUser();

        try {
            $this->commandBus->dispatch(new ConsumeStock(
                stockItemId: $stockItemId,
                clinicId: $currentClinicId->toString(),
                quantityAmount: $quantity,
                quantityUnit: $this->unitOf($stockItemId, $currentClinicId->toString()),
                occurredAt: null,
                reference: '' !== $reference ? $reference : null,
                performedBy: $user instanceof SecurityUser ? $user->id() : null,
                note: '' !== $note ? $note : null,
                reason: $request->request->getString('reason', 'CONSUMPTION_CONSULTATION'),
            ));
        } catch (\ValueError $exception) {
            return $this->refuse(StockErrorMessage::of($exception));
        } catch (\LogicException $exception) {
            return $this->refuse(StockErrorMessage::of($exception));
        }

        $this->addFlash('success', 'Sortie de stock enregistrée.');

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
