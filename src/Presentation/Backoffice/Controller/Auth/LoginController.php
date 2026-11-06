<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Auth;

use App\Presentation\Backoffice\Security\BackofficeAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * A1 — « Connexion administrateur ». The form posts to the same address,
 * handled by BackofficeAuthenticator.
 */
final class LoginController extends AbstractController
{
    #[Route(path: '/login', name: 'backoffice_login', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser() && \is_int($request->getSession()->get(BackofficeAuthenticator::VERIFIED_AT))) {
            return $this->redirectToRoute('backoffice_clinics');
        }

        $lastEmail = $request->getSession()->get(SecurityRequestAttributes::LAST_USERNAME);

        return $this->render('backoffice/console/auth/login.html.twig', [
            'error'      => $authenticationUtils->getLastAuthenticationError(),
            'last_email' => \is_string($lastEmail) ? $lastEmail : '',
        ]);
    }
}
