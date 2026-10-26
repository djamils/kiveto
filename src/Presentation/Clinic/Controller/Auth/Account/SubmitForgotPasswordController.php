<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Auth\Account;

use App\Presentation\Clinic\ViewModel\Auth\PublicAccountPage;
use App\System\IdentityAccess\Application\Command\RequestPasswordReset\RequestPasswordReset;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * A forgot-password request. Whatever happens — known address or not,
 * limit reached or not — the page answers the same message, and the e-mail
 * is prepared by the worker (RequestPasswordReset is routed to `async`), so
 * neither the answer nor its time tells whether an account exists.
 * Limits (sliding windows): 5 requests per IP per 15 minutes, 3 per address
 * per hour; beyond, nothing is sent.
 */
#[Route(path: '/mot-de-passe-oublie', name: 'clinic_forgot_password_submit', methods: ['POST'])]
final readonly class SubmitForgotPasswordController
{
    public const string CSRF_ID = 'forgot_password';

    public function __construct(
        private PublicAccountPage $page,
        private CsrfTokenManagerInterface $csrf,
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        #[Autowire(service: 'limiter.forgot_password_ip')]
        private RateLimiterFactoryInterface $ipLimiter,
        #[Autowire(service: 'limiter.forgot_password_email')]
        private RateLimiterFactoryInterface $emailLimiter,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $email = mb_strtolower(mb_trim($request->request->getString('email')));

        if (!$this->csrf->isTokenValid(new CsrfToken(self::CSRF_ID, $request->request->getString('_csrf_token')))) {
            return $this->page->render('security/forgot_password.html.twig', ['sent' => false, 'email' => $email, 'error' => PublicAccountPage::SESSION_EXPIRED], Response::HTTP_FORBIDDEN);
        }

        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $this->page->render('security/forgot_password.html.twig', ['sent' => false, 'email' => $email, 'error' => 'Saisissez votre adresse e-mail.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $accepted = $this->ipLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()
            && $this->emailLimiter->create(hash('sha256', $email))->consume()->isAccepted();

        if ($accepted) {
            $this->commandBus->dispatch(new RequestPasswordReset($email));
        }

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add(ShowForgotPasswordController::SENT_FLASH, '1');
        }

        return $this->page->redirect('clinic_forgot_password');
    }
}
