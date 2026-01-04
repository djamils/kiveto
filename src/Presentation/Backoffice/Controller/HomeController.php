<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'backoffice_home')]
    public function index(): Response
    {
        return $this->render('backoffice/index.html.twig');
    }
}
