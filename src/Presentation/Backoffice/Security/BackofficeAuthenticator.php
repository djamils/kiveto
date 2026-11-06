<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Security;

use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\ViewModel\UserAgentLabel;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\IdentityAccess\Application\Command\EnableSecondFactor\EnableSecondFactor;
use App\System\IdentityAccess\Application\Command\TrustDevice\TrustDevice;
use App\System\IdentityAccess\Application\Command\VerifySecondFactor\SecondFactorCheck;
use App\System\IdentityAccess\Application\Command\VerifySecondFactor\VerifySecondFactor;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\AuthenticateUserHandler;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\AuthenticateUserQuery;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\AuthenticationContext;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\Exception\AuthenticationDeniedException;
use App\System\IdentityAccess\Application\Query\GetSecondFactorStatus\GetSecondFactorStatus;
use App\System\IdentityAccess\Application\Query\GetSecondFactorStatus\SecondFactorStatus;
use App\System\IdentityAccess\Application\Query\IsDeviceTrusted\IsDeviceTrusted;
use App\System\IdentityAccess\Domain\SecondFactor\InvalidSecondFactorCodeException;
use App\System\IdentityAccess\Domain\SecondFactor\TrustedDevice;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * A1 / A2 — the console's sign-in, in two classic form posts: e-mail and
 * password (`/login`), then the code of the authenticator app or a backup
 * code (`/login/verification`). An admin without a second factor configures
 * one first (`/login/configuration`). A browser trusted 30 days skips the
 * code. Until the second step succeeds nothing is authenticated: the
 * pending sign-in lives in the session (PendingSignIn).
 */
final class BackofficeAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public const string CSRF_ID = 'backoffice_sign_in';

    public const string TRUST_COOKIE = 'kiveto_console_device';

    /** Timestamp of the second factor of the session; required by SecondFactorGuard. */
    public const string VERIFIED_AT = 'console.second_factor_verified_at';

    public const string ENROLLMENT_SECRET = 'console.enrollment_secret';

    public const string FRESH_BACKUP_CODES = 'console.fresh_backup_codes';

    /** How the second step was passed: shown in the audit entry of the sign-in. */
    public const string METHOD_ATTRIBUTE = '_console_sign_in_method';

    private const string TRUST_ATTRIBUTE = '_console_trust_device';

    private const string ENROLLED_ATTRIBUTE = '_console_enrolled';

    private const string EXPIRED = 'Ta connexion a expiré. Saisis à nouveau ton e-mail et ton mot de passe.';

    public function __construct(
        private readonly AuthenticateUserHandler $authenticate,
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
        private readonly UrlGeneratorInterface $urls,
        private readonly CsrfTokenManagerInterface $csrf,
        private readonly ClockInterface $clock,
        private readonly ConsoleAuditTrail $auditTrail,
        #[Autowire(service: 'app.login_rate_limiter')]
        private readonly RequestRateLimiterInterface $loginLimiter,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate('backoffice_login'));
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST') && \in_array($request->getPathInfo(), ['/login', '/login/verification', '/login/configuration'], true);
    }

    public function authenticate(Request $request): Passport
    {
        if (!$this->csrf->isTokenValid(new CsrfToken(self::CSRF_ID, $request->request->getString('_csrf_token')))) {
            throw new CustomUserMessageAuthenticationException('Ta session a expiré. Recommence.');
        }

        return match ($request->getPathInfo()) {
            '/login/verification'  => $this->secondFactor($request),
            '/login/configuration' => $this->enrollment($request),
            default                => $this->password($request),
        };
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        $this->loginLimiter->reset($request);

        $session = $request->getSession();
        PendingSignIn::clear($session);
        $session->set(self::VERIFIED_AT, $this->clock->now()->getTimestamp());

        $target = $session->has(self::FRESH_BACKUP_CODES)
            ? $this->urls->generate('backoffice_backup_codes')
            : ($this->getTargetPath($session, $firewallName) ?? $this->urls->generate('backoffice_clinics'));
        $this->removeTargetPath($session, $firewallName);

        $response = new RedirectResponse($target);

        if (true === $request->attributes->get(self::ENROLLED_ATTRIBUTE)) {
            $this->auditTrail->record('auth.2fa_enabled', AuditCategory::ACCESS, 'Double authentification activée', targetUserId: $this->userId($token));
        }

        if (true === $request->attributes->get(self::TRUST_ATTRIBUTE)) {
            $secret = $this->commandBus->dispatch(new TrustDevice($this->userId($token), UserAgentLabel::of($request->headers->get('User-Agent'))));
            \assert(\is_string($secret));

            $response->headers->setCookie(Cookie::create(
                name: self::TRUST_COOKIE,
                value: $secret,
                expire: $this->clock->now()->modify(\sprintf('+%d days', TrustedDevice::DAYS)),
                path: '/',
                secure: $request->isSecure(),
                httpOnly: true,
                sameSite: Cookie::SAMESITE_LAX,
            ));
        }

        return $response;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof SecondFactorRequiredException) {
            return new RedirectResponse($this->urls->generate($exception->enrolled ? 'backoffice_login_verification' : 'backoffice_login_configuration'));
        }

        // A plain exception with the message: it survives the session's
        // serialisation, which our exceptions' own fields would not.
        $session = $request->getSession();
        $session->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, new CustomUserMessageAuthenticationException($exception->getMessageKey(), $exception->getMessageData()));

        $restart = '/login' === $request->getPathInfo()
            || ($exception instanceof InvalidSecondFactorException && $exception->restart)
            || null === PendingSignIn::from($session, $this->clock->now()->getTimestamp());

        return new RedirectResponse($this->urls->generate(match (true) {
            $restart                                           => 'backoffice_login',
            '/login/configuration' === $request->getPathInfo() => 'backoffice_login_configuration',
            default                                            => 'backoffice_login_verification',
        }));
    }

    /**
     * The backup codes just made, shown once on the next page.
     */
    public static function keepFreshBackupCodes(SessionInterface $session, mixed $codes): void
    {
        \assert(\is_array($codes));
        $session->set(self::FRESH_BACKUP_CODES, array_values(array_filter($codes, is_string(...))));
    }

    private function password(Request $request): Passport
    {
        $email    = mb_strtolower(trim($request->request->getString('email')));
        $password = $request->request->getString('password');
        $request->attributes->set(SecurityRequestAttributes::LAST_USERNAME, 'backoffice:' . $email);
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        if ('' === $email || '' === $password) {
            throw new CustomUserMessageAuthenticationException('Saisis ton adresse e-mail et ton mot de passe.');
        }

        if (!$this->loginLimiter->consume($request)->isAccepted()) {
            throw new TooManyLoginAttemptsAuthenticationException();
        }

        try {
            $identity = ($this->authenticate)(new AuthenticateUserQuery($email, $password, AuthenticationContext::BACKOFFICE));
        } catch (AuthenticationDeniedException $e) {
            throw new CustomUserMessageAuthenticationException('Adresse e-mail ou mot de passe incorrect.', [], 0, $e);
        }

        $cookie = $request->cookies->getString(self::TRUST_COOKIE);

        if ('' !== $cookie && true === $this->queryBus->ask(new IsDeviceTrusted($identity->id, $cookie))) {
            $request->attributes->set(self::METHOD_ATTRIBUTE, 'Appareil de confiance');

            return $this->passport($identity->email);
        }

        $status = $this->queryBus->ask(new GetSecondFactorStatus($identity->id));
        \assert($status instanceof SecondFactorStatus);

        new PendingSignIn($identity->id, $identity->email, $this->clock->now()->getTimestamp())->store($request->getSession());
        $request->getSession()->remove(self::ENROLLMENT_SECRET);

        throw new SecondFactorRequiredException($status->enabled);
    }

    private function secondFactor(Request $request): Passport
    {
        $session = $request->getSession();
        $pending = $this->pending($request);

        $check = $this->commandBus->dispatch(new VerifySecondFactor($pending->userId, $request->request->getString('code')));
        \assert($check instanceof SecondFactorCheck);

        if (!$check->isAccepted()) {
            throw new InvalidSecondFactorException(null === $pending->failed($session));
        }

        $request->attributes->set(self::METHOD_ATTRIBUTE, SecondFactorCheck::BACKUP_CODE === $check ? 'Code de secours' : 'Application d’authentification');
        $request->attributes->set(self::TRUST_ATTRIBUTE, $request->request->getBoolean('trust_device'));

        return $this->passport($pending->email);
    }

    private function enrollment(Request $request): Passport
    {
        $session = $request->getSession();
        $pending = $this->pending($request);
        $secret  = $session->get(self::ENROLLMENT_SECRET);

        if (!\is_string($secret)) {
            throw new CustomUserMessageAuthenticationException(self::EXPIRED);
        }

        try {
            $codes = $this->commandBus->dispatch(new EnableSecondFactor($pending->userId, $secret, $request->request->getString('code')));
        } catch (InvalidSecondFactorCodeException) {
            throw new InvalidSecondFactorException(null === $pending->failed($session));
        }

        self::keepFreshBackupCodes($session, $codes);
        $session->remove(self::ENROLLMENT_SECRET);
        $request->attributes->set(self::METHOD_ATTRIBUTE, 'Application d’authentification (configurée)');
        $request->attributes->set(self::ENROLLED_ATTRIBUTE, true);

        return $this->passport($pending->email);
    }

    private function pending(Request $request): PendingSignIn
    {
        $pending = PendingSignIn::from($request->getSession(), $this->clock->now()->getTimestamp());

        if (null === $pending) {
            throw new CustomUserMessageAuthenticationException(self::EXPIRED);
        }

        $request->attributes->set(SecurityRequestAttributes::LAST_USERNAME, 'backoffice:' . $pending->email);

        return $pending;
    }

    private function passport(string $email): Passport
    {
        return new SelfValidatingPassport(new UserBadge($email));
    }

    private function userId(TokenInterface $token): string
    {
        $user = $token->getUser();
        \assert($user instanceof SecurityUser);

        return $user->id();
    }
}
