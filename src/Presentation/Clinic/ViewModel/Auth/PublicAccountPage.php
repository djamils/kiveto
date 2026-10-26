<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Auth;

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
 */
final readonly class PublicAccountPage
{
    public const string RESET_SESSION_KEY      = 'account.reset_token';
    public const string INVITATION_SESSION_KEY = 'account.invitation_token';
    public const string LOGIN_NOTICE           = 'auth_notice';

    public const string FORGOT_SENT      = 'Si un compte existe pour cette adresse, un e-mail vient d’être envoyé.';
    public const string LINK_INVALID     = 'Ce lien n’est plus valide';
    public const string ALREADY_ACTIVE   = 'Votre compte est déjà actif — connectez-vous';
    public const string SESSION_EXPIRED  = 'Session expirée : rechargez la page et recommencez.';
    public const string PASSWORDS_DIFFER = 'Les deux mots de passe ne correspondent pas.';

    public function __construct(
        private Environment $twig,
        private UrlGeneratorInterface $urls,
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
        $request->getSession()->set($sessionKey, $secret);

        return $this->redirect($route);
    }

    public static function secret(Request $request, string $sessionKey): ?string
    {
        $secret = $request->getSession()->get($sessionKey);

        return \is_string($secret) && '' !== $secret ? $secret : null;
    }

    public static function private(Response $response): Response
    {
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
