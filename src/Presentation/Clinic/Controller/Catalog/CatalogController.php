<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CatalogController extends AbstractController
{
    #[Route(path: '/catalogue', name: 'clinic_catalogue', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('clinic/catalog/index.html.twig');
    }
}
