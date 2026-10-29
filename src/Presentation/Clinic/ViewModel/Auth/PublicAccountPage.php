<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Auth;

use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Query\CheckAccountToken\AccountTokenCheck;
use App\System\IdentityAccess\Application\Query\CheckAccountToken\CheckAccountToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * What the public account pages of the clinic host share (« Mot de passe
 * oublié », reset, invitation): no referrer ever leaves them (the secret
 * of a link must not reach another site), the secret is kept in the
 * session rather than in the address bar, and the French wording.
 *
 * The secret kept in the session is bound to its page (one key per page)
 * and lives SECRET_LIFETIME at most — no longer than the shortest link (a
 * reset, 30 minutes); any sign-in and the logout drop it
 * (AccountSecretsCleaner): a shared workstation keeps no usable link.
 */
final readonly class PublicAccountPage
{
    public const string RESET_SESSION_KEY      = 'account.reset_token';
    public const string INVITATION_SESSION_KEY = 'account.invitation_token';
    public const string LOGIN_NOTICE           = 'auth_notice';
    public const array SESSION_KEYS            = [self::RESET_SESSION_KEY, self::INVITATION_SESSION_KEY];
    public const int SECRET_LIFETIME           = 1800;

    public const string FORGOT_SENT      = 'Si un compte existe pour cette adresse, un e-mail vient d’être envoyé.';
    public const string LINK_INVALID     = 'Ce lien n’est plus valide';
    public const string ALREADY_ACTIVE   = 'Votre compte est déjà actif — connectez-vous';
    public const string SESSION_EXPIRED  = 'Session expirée : rechargez la page et recommencez.';
    public const string PASSWORDS_DIFFER = 'Les deux mots de passe ne correspondent pas.';

    public function __construct(
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
        private QueryBusInterface $queryBus,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = [], int $status = Response::HTTP_OK): Response
    {
        return self::private(new Response($this->twig->render($template, ['context' => 'clinic'] + $context), $status));
    }

    /**
     * @param array<string, string> $parameters
     */
    public function redirect(string $route, array $parameters = []): RedirectResponse
    {
        $response = self::private(new RedirectResponse($this->urls->generate($route, $parameters), Response::HTTP_SEE_OTHER));
        \assert($response instanceof RedirectResponse);

        return $response;
    }

    /**
     * The link of an e-mail: its secret goes into the session and the page
     * reloads at an address without it (no secret in the history, the logs
     * of a proxy or a Referer).
     */
    public function keepSecret(Request $request, string $sessionKey, string $secret, string $route): RedirectResponse
    {
        $request->getSession()->set($sessionKey, ['secret' => $secret, 'issuedAt' => $this->clock->now()->getTimestamp()]);

        return $this->redirect($route);
    }

    /** The secret this page keeps, null (and forgotten) once older than SECRET_LIFETIME. */
    public function secret(Request $request, string $sessionKey): ?string
    {
        $session = $request->getSession();
        $kept    = $session->get($sessionKey);
        $secret  = \is_array($kept) ? ($kept['secret'] ?? null) : null;
        $issued  = \is_array($kept) ? ($kept['issuedAt'] ?? null) : null;

        if (\is_string($secret) && '' !== $secret && \is_int($issued) && $this->clock->now()->getTimestamp() - $issued < self::SECRET_LIFETIME) {
            return $secret;
        }

        $session->remove($sessionKey);

        return null;
    }

    /**
     * The account a still-usable link of the session belongs to, for the
     * « Pour le compte … » line of a re-rendered form.
     */
    public function accountEmail(?string $secret, string $purpose): ?string
    {
        $check = null !== $secret ? $this->queryBus->ask(new CheckAccountToken($secret, $purpose)) : null;

        return $check instanceof AccountTokenCheck && $check->isValid() ? $check->email : null;
    }

    public static function private(Response $response): Response
    {
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
