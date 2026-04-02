<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CatalogueController extends AbstractController
{
    #[Route(path: '/catalogue', name: 'clinic_catalogue', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('clinic/catalogue/index.html.twig');
    }
}
