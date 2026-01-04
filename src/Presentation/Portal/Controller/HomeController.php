<?php

declare(strict_types=1);

namespace App\Presentation\Portal\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'portal_home')]
    public function index(): Response
    {
        return $this->render('portal/index.html.twig');
    }
}
