<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Auth;

use App\Presentation\Backoffice\Security\PendingSignIn;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * A2 — « Double authentification »: the six digits of the app, or a backup
 * code (`?mode=secours`).
 */
final class VerificationController extends AbstractController
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route(path: '/login/verification', name: 'backoffice_login_verification', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $pending = PendingSignIn::from($request->getSession(), $this->clock->now()->getTimestamp());

        if (null === $pending) {
            return $this->redirectToRoute('backoffice_login');
        }

        return $this->render('backoffice/console/auth/verification.html.twig', [
            'email'  => $pending->email,
            'backup' => 'secours' === $request->query->getString('mode'),
            'error'  => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }
}
