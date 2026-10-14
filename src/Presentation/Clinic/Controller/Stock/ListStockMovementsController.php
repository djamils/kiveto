<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Presentation\Clinic\ViewModel\Stock\StockPageBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The trail of the stock — the stock page opened on « Mouvements ». The page switches tabs in place and keeps the address in step.
 */
#[Route(path: '/stock/movements', name: 'clinic_stock_movements', methods: ['GET'])]
final class ListStockMovementsController extends AbstractController
{
    public function __construct(
        private readonly StockPageBuilder $pages,
        private readonly StockEndpoint $endpoint,
    ) {
    }

    public function __invoke(): Response
    {
        return $this->render('clinic/stock/index.html.twig', [
            'payload' => $this->pages->page($this->endpoint->clinicId(), 'mvts'),
        ]);
    }
}
