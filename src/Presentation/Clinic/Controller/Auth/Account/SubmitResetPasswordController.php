<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Auth\Account;

use App\Presentation\Clinic\ViewModel\Auth\PublicAccountPage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\ResetPassword\ResetPassword;
use App\System\IdentityAccess\Application\Exception\InvalidAccountTokenException;
use App\System\IdentityAccess\Application\Exception\UnacceptablePasswordException;
use App\System\IdentityAccess\Application\Query\CheckAccountToken\CheckAccountToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The new password (public). Every open session of the user ends with the
 * change (password fingerprint); the member signs in again.
 */
#[Route(path: '/reinitialiser', name: 'clinic_reset_password_submit', methods: ['POST'])]
final readonly class SubmitResetPasswordController
{
    public function __construct(
        private PublicAccountPage $page,
        private CsrfTokenManagerInterface $csrf,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $secret = $this->page->secret($request, PublicAccountPage::RESET_SESSION_KEY);
        $email  = $this->page->accountEmail($secret, CheckAccountToken::PASSWORD_RESET);

        if (!$this->csrf->isTokenValid(new CsrfToken(ShowResetPasswordController::CSRF_ID, $request->request->getString('_csrf_token')))) {
            return $this->page->render('security/reset_password.html.twig', ['state' => null !== $secret ? 'form' : 'invalid', 'email' => $email, 'error' => PublicAccountPage::SESSION_EXPIRED], Response::HTTP_FORBIDDEN);
        }

        if (null === $secret) {
            return $this->page->render('security/reset_password.html.twig', ['state' => 'invalid']);
        }

        $password = $request->request->getString('password');

        if ($password !== $request->request->getString('confirmation')) {
            return $this->page->render('security/reset_password.html.twig', ['state' => 'form', 'email' => $email, 'error' => PublicAccountPage::PASSWORDS_DIFFER], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->commandBus->dispatch(new ResetPassword($secret, $password));
        } catch (UnacceptablePasswordException $exception) {
            return $this->page->render('security/reset_password.html.twig', ['state' => 'form', 'email' => $email, 'error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidAccountTokenException) {
            $request->getSession()->remove(PublicAccountPage::RESET_SESSION_KEY);

            return $this->page->render('security/reset_password.html.twig', ['state' => 'invalid']);
        }

        $session = $request->getSession();
        $session->remove(PublicAccountPage::RESET_SESSION_KEY);

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add(PublicAccountPage::LOGIN_NOTICE, 'Votre mot de passe a été modifié. Connectez-vous avec le nouveau.');
        }

        return $this->page->redirect('clinic_login');
    }
}
