<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LoginController extends AbstractController
{
    #[Route(path: '/login', name: 'clinic_login', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('clinic_home');
        }

        // `deco` is set by the logout redirect (security.yaml, clinic firewall).
        return $this->render('security/login.html.twig', [
            'context'   => 'clinic',
            'loggedOut' => $request->query->has('deco'),
        ]);
    }
}
