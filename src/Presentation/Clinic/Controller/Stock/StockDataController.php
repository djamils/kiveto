<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Presentation\Clinic\ViewModel\Stock\StockPageBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** The whole stock again, as the page redraws it after a change. */
#[Route(path: '/stock/data', name: 'clinic_stock_data', methods: ['GET'])]
final readonly class StockDataController
{
    public function __construct(
        private StockPageBuilder $pages,
        private StockEndpoint $endpoint,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return new JsonResponse($this->pages->data($this->endpoint->clinicId()));
    }
}
