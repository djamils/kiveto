<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Auth;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Command\ResetPassword\ResetPassword;
use App\System\IdentityAccess\Application\Exception\InvalidAccountTokenException;
use App\System\IdentityAccess\Application\Exception\UnacceptablePasswordException;
use App\System\IdentityAccess\Application\Query\CheckAccountToken\AccountTokenCheck;
use App\System\IdentityAccess\Application\Query\CheckAccountToken\CheckAccountToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The reset link of the console: the secret is moved from the address to
 * the session (30 minutes at most) before the form is shown.
 */
final class ResetPasswordController extends AbstractController
{
    public const string CSRF_ID = 'backoffice_reset_password';

    private const string SECRET_KEY = 'console.reset_secret';

    private const int SECRET_SECONDS = 1800;

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route(path: '/reinitialiser/{token}', name: 'backoffice_reset_password', defaults: ['token' => null], methods: ['GET', 'POST'])]
    public function __invoke(Request $request, ?string $token = null): Response
    {
        $session = $request->getSession();

        if (null !== $token) {
            $session->set(self::SECRET_KEY, ['secret' => $token, 'at' => $this->clock->now()->getTimestamp()]);

            return $this->page($this->redirectToRoute('backoffice_reset_password', status: Response::HTTP_SEE_OTHER));
        }

        $secret = $this->secret($request);
        $check  = null !== $secret ? $this->queryBus->ask(new CheckAccountToken($secret, CheckAccountToken::PASSWORD_RESET)) : null;

        if (!$check instanceof AccountTokenCheck || !$check->isValid()) {
            return $this->view('invalid');
        }

        if ($request->isMethod('GET')) {
            return $this->view('form', $check->email);
        }

        if (!$this->isCsrfTokenValid(self::CSRF_ID, $request->request->getString('_csrf_token'))) {
            return $this->view('form', $check->email, 'Ta session a expiré. Recommence.', Response::HTTP_FORBIDDEN);
        }

        $password = $request->request->getString('password');

        if ($password !== $request->request->getString('confirmation')) {
            return $this->view('form', $check->email, 'Les deux mots de passe ne correspondent pas.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->commandBus->dispatch(new ResetPassword((string) $secret, $password));
        } catch (UnacceptablePasswordException $exception) {
            return $this->view('form', $check->email, $exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidAccountTokenException) {
            $session->remove(self::SECRET_KEY);

            return $this->view('invalid');
        }

        $session->remove(self::SECRET_KEY);
        $this->addFlash('success', 'Ton mot de passe a été modifié. Connecte-toi avec le nouveau.');

        return $this->redirectToRoute('backoffice_login', status: Response::HTTP_SEE_OTHER);
    }

    private function secret(Request $request): ?string
    {
        $kept   = $request->getSession()->get(self::SECRET_KEY);
        $secret = \is_array($kept) ? ($kept['secret'] ?? null) : null;
        $at     = \is_array($kept) ? ($kept['at'] ?? null) : null;

        if (!\is_string($secret) || !\is_int($at) || $this->clock->now()->getTimestamp() - $at > self::SECRET_SECONDS) {
            return null;
        }

        return $secret;
    }

    private function view(string $state, ?string $email = null, ?string $error = null, int $status = Response::HTTP_OK): Response
    {
        return $this->page($this->render('backoffice/console/auth/reset_password.html.twig', [
            'state' => $state,
            'email' => $email,
            'error' => $error,
        ], new Response(status: $status)));
    }

    private function page(Response $response): Response
    {
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
