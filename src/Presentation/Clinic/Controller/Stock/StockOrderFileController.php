<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Presentation\Clinic\ViewModel\Stock\StockOrders;
use App\Shared\Domain\Storage\FileStorageInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The order file of a supplier that takes its orders by file (CSV): what
 * « Envoyer à … » exported, to hand over to the supplier.
 */
#[Route(path: '/stock/orders/{purchaseOrderId}/file', name: 'clinic_stock_order_file', methods: ['GET'])]
final readonly class StockOrderFileController
{
    public function __construct(
        private StockEndpoint $endpoint,
        private StockOrders $orders,
        private FileStorageInterface $files,
    ) {
    }

    public function __invoke(string $purchaseOrderId): Response
    {
        $order = $this->orders->open($this->endpoint->clinicId(), $purchaseOrderId);

        if (null === $order || null === $order->exportFile) {
            throw new NotFoundHttpException('Aucun bon de commande à télécharger.');
        }

        try {
            $content = $this->files->read($order->exportFile);
        } catch (\RuntimeException) {
            throw new NotFoundHttpException('Le bon de commande est introuvable.');
        }

        return new Response($content, Response::HTTP_OK, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => \sprintf('attachment; filename="%s.csv"', $order->orderNumber),
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
