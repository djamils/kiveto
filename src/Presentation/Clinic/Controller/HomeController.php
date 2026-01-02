<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'clinic_home')]
    public function index(): Response
    {
        return $this->render('clinic/index.html.twig', [
            'translations' => [
                'hello' => 'hello', // already defined fr_FR
                'clinic.home.title' => 'clinic.home.title',
                'clinic.home.subtitle' => 'clinic.home.subtitle',
                'clinic.home.cta' => 'clinic.home.cta',
            ],
        ]);
    }

    #[Route('/dashboard-layout14', name: 'clinic_dashboard_layout14')]
    public function dashboardLayout14(): Response
    {
        return $this->render('clinic/dashboard-layout14.html.twig');
    }
}
