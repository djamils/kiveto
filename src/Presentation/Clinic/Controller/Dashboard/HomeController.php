<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Dashboard;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'clinic_home')]
    public function __invoke(): Response
    {
        return $this->redirectToRoute('clinic_dashboard');
    }
}
