<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Presentation\Clinic\ViewModel\Catalog\CatalogEndpoint;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogPageBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The catalogue page opened on its « Articles » tab — the page itself switches
 * tabs in place and keeps the address in step.
 */
#[Route(path: '/catalog/articles', name: 'clinic_catalogue_articles', methods: ['GET'])]
final class ListArticlesController extends AbstractController
{
    public function __construct(
        private readonly CatalogPageBuilder $pages,
        private readonly CatalogEndpoint $endpoint,
    ) {
    }

    public function __invoke(): Response
    {
        return $this->render('clinic/catalog/index.html.twig', [
            'payload' => $this->pages->page($this->endpoint->clinicId(), 'articles'),
        ]);
    }
}
