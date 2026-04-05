<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\ClinicalCare;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListConsultationsController extends AbstractController
{
    #[Route(path: '/consultations', name: 'clinic_consultations', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('clinic/consultation/index.html.twig');
    }
}
