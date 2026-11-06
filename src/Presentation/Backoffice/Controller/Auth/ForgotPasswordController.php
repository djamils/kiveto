<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Auth;

use App\System\IdentityAccess\Application\Command\RequestPasswordReset\RequestPasswordReset;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Mot de passe oublié ? » of the console. Same answer whatever the
 * address; rate limited like the clinic page; the link (30 minutes) opens
 * the console's reset page, and the second factor is still asked after it.
 */
final class ForgotPasswordController extends AbstractController
{
    public const string CSRF_ID = 'backoffice_forgot_password';

    private const string SENT_FLASH = 'console_forgot_sent';

    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private readonly MessageBusInterface $commandBus,
        #[Autowire(service: 'limiter.forgot_password_ip')]
        private readonly RateLimiterFactoryInterface $ipLimiter,
        #[Autowire(service: 'limiter.forgot_password_email')]
        private readonly RateLimiterFactoryInterface $emailLimiter,
    ) {
    }

    #[Route(path: '/mot-de-passe-oublie', name: 'backoffice_forgot_password', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('GET')) {
            $session  = $request->getSession();
            $response = $this->render('backoffice/console/auth/forgot_password.html.twig', [
                'sent'  => $session instanceof FlashBagAwareSessionInterface && [] !== $session->getFlashBag()->get(self::SENT_FLASH),
                'error' => null,
            ]);
            $response->headers->set('Referrer-Policy', 'no-referrer');

            return $response;
        }

        $email = mb_strtolower(trim($request->request->getString('email')));

        if (!$this->isCsrfTokenValid(self::CSRF_ID, $request->request->getString('_csrf_token'))) {
            return $this->render('backoffice/console/auth/forgot_password.html.twig', ['sent' => false, 'error' => 'Ta session a expiré. Recommence.'], new Response(status: Response::HTTP_FORBIDDEN));
        }

        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $this->render('backoffice/console/auth/forgot_password.html.twig', ['sent' => false, 'error' => 'Saisis ton adresse e-mail.'], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $accepted = $this->ipLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()
            && $this->emailLimiter->create(hash('sha256', 'backoffice:' . $email))->consume()->isAccepted();

        if ($accepted) {
            $this->commandBus->dispatch(new RequestPasswordReset($email, backoffice: true));
        }

        $this->addFlash(self::SENT_FLASH, '1');

        return $this->redirectToRoute('backoffice_forgot_password', status: Response::HTTP_SEE_OTHER);
    }
}
