<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        if ('POST' === $request->getMethod()) {
            if (null === $this->getUser()) {
                return new JsonResponse(['message' => 'Authentication failed.'], JsonResponse::HTTP_UNAUTHORIZED);
            }

            return new JsonResponse(['message' => 'Authenticated'], JsonResponse::HTTP_OK);
        }

        return $this->render('security/login.html.twig');
    }

    #[Route(path: '/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): Response
    {
        // Intercepted by Symfony Security; this code is never executed.
        throw new \LogicException('Logout is handled by the firewall.');
    }
}

