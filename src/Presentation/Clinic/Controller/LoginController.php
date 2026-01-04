<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LoginController extends AbstractController
{
    #[Route(path: '/login', name: 'clinic_login', methods: ['GET', 'POST'])]
    public function __invoke(): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('clinic_home');
        }

        return $this->render('security/login.html.twig', [
            'app' => 'clinic',
        ]);
    }
}
