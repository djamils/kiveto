<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LoginController extends AbstractController
{
    #[Route(path: '/login', name: 'clinic_login', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('security/login.html.twig', [
            'app' => 'clinic',
        ]);
    }
}

