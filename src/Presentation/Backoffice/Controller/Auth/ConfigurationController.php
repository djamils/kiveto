<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Auth;

use App\Presentation\Backoffice\Security\BackofficeAuthenticator;
use App\Presentation\Backoffice\Security\PendingSignIn;
use App\Presentation\Backoffice\ViewModel\QrCode;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Query\PrepareSecondFactor\PrepareSecondFactor;
use App\System\IdentityAccess\Application\Query\PrepareSecondFactor\SecondFactorEnrollment;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * First sign-in of an admin without a second factor: scan the key, type a
 * code. The key is kept in the session until a code of it is accepted.
 */
final class ConfigurationController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route(path: '/login/configuration', name: 'backoffice_login_configuration', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $session = $request->getSession();
        $pending = PendingSignIn::from($session, $this->clock->now()->getTimestamp());

        if (null === $pending) {
            return $this->redirectToRoute('backoffice_login');
        }

        $secret = $session->get(BackofficeAuthenticator::ENROLLMENT_SECRET);
        $setup  = \is_string($secret) ? new SecondFactorEnrollment($secret, $pending->email) : $this->queryBus->ask(new PrepareSecondFactor($pending->email));
        \assert($setup instanceof SecondFactorEnrollment);
        $session->set(BackofficeAuthenticator::ENROLLMENT_SECRET, $setup->secret);

        return $this->render('backoffice/console/auth/configuration.html.twig', [
            'email'  => $pending->email,
            'setup'  => $setup,
            'qr'     => QrCode::svg($setup->uri()),
            'error'  => $authenticationUtils->getLastAuthenticationError(),
            'action' => $this->generateUrl('backoffice_login_configuration'),
        ]);
    }
}
