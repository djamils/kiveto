<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Auth\Account;

use App\Presentation\Clinic\ViewModel\Auth\PublicAccountPage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\ActivateInvitedUser\ActivateInvitedUser;
use App\System\IdentityAccess\Application\Command\ActivateInvitedUser\ActivationResult;
use App\System\IdentityAccess\Application\Exception\InvalidAccountTokenException;
use App\System\IdentityAccess\Application\Exception\UnacceptablePasswordException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/** The invited member chooses their password (public); then signs in. */
#[Route(path: '/invitation', name: 'clinic_invitation_submit', methods: ['POST'])]
final readonly class SubmitInvitationController
{
    public function __construct(
        private PublicAccountPage $page,
        private CsrfTokenManagerInterface $csrf,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $secret = PublicAccountPage::secret($request, PublicAccountPage::INVITATION_SESSION_KEY);

        if (!$this->csrf->isTokenValid(new CsrfToken(ShowInvitationController::CSRF_ID, $request->request->getString('_csrf_token')))) {
            return $this->page->render('security/invitation.html.twig', ['state' => null !== $secret ? 'form' : 'invalid', 'error' => PublicAccountPage::SESSION_EXPIRED], Response::HTTP_FORBIDDEN);
        }

        if (null === $secret) {
            return $this->page->render('security/invitation.html.twig', ['state' => 'invalid']);
        }

        $password = $request->request->getString('password');

        if ($password !== $request->request->getString('confirmation')) {
            return $this->page->render('security/invitation.html.twig', ['state' => 'form', 'error' => PublicAccountPage::PASSWORDS_DIFFER], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $this->commandBus->dispatch(new ActivateInvitedUser($secret, $password));
        } catch (UnacceptablePasswordException $exception) {
            return $this->page->render('security/invitation.html.twig', ['state' => 'form', 'error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidAccountTokenException) {
            $request->getSession()->remove(PublicAccountPage::INVITATION_SESSION_KEY);

            return $this->page->render('security/invitation.html.twig', ['state' => 'invalid']);
        }

        $session = $request->getSession();
        $session->remove(PublicAccountPage::INVITATION_SESSION_KEY);

        if (ActivationResult::ALREADY_ACTIVE === $result) {
            return $this->page->render('security/invitation.html.twig', ['state' => 'active']);
        }

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add(PublicAccountPage::LOGIN_NOTICE, 'Votre compte est activé. Connectez-vous avec votre mot de passe.');
        }

        return $this->page->redirect('clinic_login');
    }
}
