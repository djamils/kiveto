<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Catalog\Application\Query\Article\GetArticleDetail\ArticleDetailView;
use App\Context\Catalog\Application\Query\Article\GetArticleDetail\GetArticleDetail;
use App\Context\Inventory\Application\Command\OpenStockItem\OpenStockItem;
use App\Context\Inventory\Application\Command\ReceiveStock\ReceiveStock;
use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotForArticles;
use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotRow;
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
 * A delivery arrives: a lot, its quantity and its expiry date.
 *
 * The stock item is opened on the way in when the clinic has never received this
 * article — the alternative is a separate "start tracking" step that exists only
 * to satisfy the model.
 */
#[Route(path: '/stock/articles/{articleId}/receipts', name: 'clinic_stock_receive', methods: ['POST'])]
final class ReceiveStockController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request, string $articleId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $quantity = QuantityParser::normalize($request->request->getString('quantity'));
        $lot      = mb_trim($request->request->getString('lot_number'));
        $expiry   = mb_trim($request->request->getString('expiry_date'));

        if (null === $quantity || '0.000000' === $quantity) {
            return $this->refuse('La quantité reçue est invalide.');
        }

        if ('' === $lot || '' === $expiry) {
            return $this->refuse('Un lot et sa date de péremption sont nécessaires pour enregistrer une réception.');
        }

        $expiryDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $expiry);

        if (false === $expiryDate) {
            return $this->refuse('La date de péremption saisie est invalide.');
        }

        // A lot that arrives already out of date is a typo far more often than a
        // delivery — the year is the field people get wrong.
        if ($expiryDate < new \DateTimeImmutable('today')) {
            return $this->refuse('La date de péremption est déjà passée : vérifiez la date saisie.');
        }

        try {
            $article = $this->queryBus->ask(new GetArticleDetail($articleId, $clinicId));
        } catch (\DomainException) {
            throw $this->createNotFoundException('Article introuvable.');
        }

        \assert($article instanceof ArticleDetailView);

        $reference = mb_trim($request->request->getString('reference'));
        $user      = $this->getUser();

        try {
            $stockItemId = $this->stockItemId($clinicId, $article);

            $this->commandBus->dispatch(new ReceiveStock(
                stockItemId: $stockItemId,
                clinicId: $clinicId,
                lotNumber: $lot,
                quantityAmount: $quantity,
                quantityUnit: $article->unitOfMeasure,
                expiryDate: $expiry,
                sourceSupplierId: null,
                occurredAt: null,
                reference: '' !== $reference ? $reference : null,
                performedBy: $user instanceof SecurityUser ? $user->id() : null,
            ));
        } catch (\ValueError $exception) {
            return $this->refuse(StockErrorMessage::of($exception));
        } catch (\LogicException $exception) {
            return $this->refuse(StockErrorMessage::of($exception));
        }

        $this->addFlash('success', \sprintf('Réception enregistrée : %s.', $article->name));

        return $this->redirectToRoute('clinic_stock');
    }

    /**
     * The stock item of this article, opened on the spot when there is none.
     */
    private function stockItemId(string $clinicId, ArticleDetailView $article): string
    {
        $snapshots = $this->queryBus->ask(new StockSnapshotForArticles($clinicId, [$article->id]));
        $existing  = \is_array($snapshots) ? ($snapshots[$article->id] ?? null) : null;

        if ($existing instanceof StockSnapshotRow) {
            return $existing->stockItemId;
        }

        $opened = $this->commandBus->dispatch(new OpenStockItem(
            clinicId: $clinicId,
            articleId: $article->id,
            unit: $article->unitOfMeasure,
            thresholdAmount: '0.000000',
            thresholdUnit: $article->unitOfMeasure,
            thresholdType: 'ABSOLUTE',
            trackStock: true,
        ));

        \assert(\is_string($opened));

        return $opened;
    }

    private function refuse(string $message): Response
    {
        $this->addFlash('error', $message);

        return $this->redirectToRoute('clinic_stock');
    }
}
