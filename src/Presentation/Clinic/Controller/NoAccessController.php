<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '', host: 'clinic.kiveto.local')]
final class NoAccessController extends AbstractController
{
    #[Route(path: '/no-access', name: 'clinic_no_access', methods: ['GET'])]
    public function noAccess(): Response
    {
        return $this->render('clinic/no-clinic-access.html.twig');
    }
}
