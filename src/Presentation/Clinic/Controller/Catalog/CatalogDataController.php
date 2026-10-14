<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Presentation\Clinic\ViewModel\Catalog\CatalogEndpoint;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogPageBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** The whole catalogue again, as the page redraws it after a change. */
#[Route(path: '/catalog/data', name: 'clinic_catalogue_data', methods: ['GET'])]
final readonly class CatalogDataController
{
    public function __construct(
        private CatalogPageBuilder $pages,
        private CatalogEndpoint $endpoint,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return new JsonResponse($this->pages->data($this->endpoint->clinicId()));
    }
}
