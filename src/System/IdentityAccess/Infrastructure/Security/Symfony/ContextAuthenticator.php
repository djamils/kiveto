<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Security\Symfony;

use App\Context\Clinic\Application\Query\Clinic\ResolveActiveClinic\ActiveClinicResult;
use App\Context\Clinic\Application\Query\Clinic\ResolveActiveClinic\ActiveClinicResultType;
use App\Context\Clinic\Application\Query\Clinic\ResolveActiveClinic\ResolveActiveClinic;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\AuthenticateUserHandler;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\AuthenticateUserQuery;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\AuthenticationContext;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\Exception\AuthenticationDeniedException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class ContextAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly AuthenticateUserHandler $handler,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        #[Autowire(service: 'app.login_rate_limiter')]
        private readonly RequestRateLimiterInterface $loginLimiter,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): RedirectResponse
    {
        $host = $request->getHost();

        $route = match (true) {
            str_starts_with($host, 'clinic.')     => 'clinic_login',
            str_starts_with($host, 'portal.')     => 'portal_login',
            str_starts_with($host, 'backoffice.') => 'backoffice_login',
            default                               => 'clinic_login',
        };

        return new RedirectResponse($this->urlGenerator->generate($route));
    }

    public function supports(Request $request): bool
    {
        return 'POST' === $request->getMethod() && '/login' === $request->getPathInfo();
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $authContext = $this->resolveContext($request);
            $payload     = json_decode((string) $request->getContent(), true, flags: \JSON_THROW_ON_ERROR);

            if (!\is_array($payload)) {
                throw new CustomUserMessageAuthenticationException('Invalid credentials payload.');
            }

            $email    = $payload['email'] ?? null;
            $password = $payload['password'] ?? null;

            if (!\is_string($email) || !\is_string($password) || '' === trim($email) || '' === $password) {
                throw new CustomUserMessageAuthenticationException('Invalid credentials payload.');
            }

            $this->throttle($request, $authContext, $email);

            $identity = ($this->handler)(new AuthenticateUserQuery(
                email: $email,
                plainPassword: $password,
                context: $authContext,
            ));
        } catch (\JsonException $e) {
            throw new CustomUserMessageAuthenticationException('Invalid JSON payload.', [], 0, $e);
        } catch (AuthenticationDeniedException $e) {
            throw new CustomUserMessageAuthenticationException($e->getMessage(), [], 0, $e);
        }

        return new SelfValidatingPassport(
            new UserBadge(
                $identity->id,
                fn () => new SecurityUser($identity->id, $identity->email, $identity->type, $identity->roles, $identity->passwordFingerprint),
            ),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        // The attempts of this account and IP are given back, as login_throttling does.
        $this->loginLimiter->reset($request);

        $authContext = $this->resolveContext($request);
        $user        = $token->getUser();
        $userId      = $user instanceof SecurityUser ? $user->id() : '';

        if ('' === $userId) {
            $redirectUrl = $this->urlGenerator->generate($this->loginRouteForContext($authContext));

            return $this->createSuccessResponse($request, $redirectUrl);
        }

        if (AuthenticationContext::CLINIC !== $authContext) {
            $redirectUrl = $this->urlGenerator->generate($this->successRouteForContext($authContext));

            return $this->createSuccessResponse($request, $redirectUrl);
        }

        $result = $this->queryBus->ask(new ResolveActiveClinic($userId));
        \assert($result instanceof ActiveClinicResult);

        $redirectUrl = match ($result->type) {
            ActiveClinicResultType::NONE     => $this->urlGenerator->generate('clinic_no_access'),
            ActiveClinicResultType::SINGLE   => $this->getUrlForSingleClinic($result),
            ActiveClinicResultType::MULTIPLE => $this->urlGenerator->generate('clinic_select_clinic'),
        };

        return $this->createSuccessResponse($request, $redirectUrl);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse([
                'error' => [
                    'code'    => 'TOO_MANY_ATTEMPTS',
                    'message' => 'Too many sign-in attempts.',
                ],
            ], JsonResponse::HTTP_TOO_MANY_REQUESTS);
        }

        return new JsonResponse([
            'error' => [
                'code'    => 'AUTHENTICATION_FAILED',
                'message' => 'Authentication failed.',
            ],
        ], JsonResponse::HTTP_UNAUTHORIZED);
    }

    /**
     * Counts the attempt before the password is checked. Symfony's
     * login_throttling cannot: it listens to CheckPassportEvent, which a wrong
     * password never reaches here (the handler refuses it in authenticate()).
     * Per sign-in context, account and IP, and per IP (app.login_rate_limiter).
     */
    private function throttle(Request $request, AuthenticationContext $context, string $email): void
    {
        $request->attributes->set(SecurityRequestAttributes::LAST_USERNAME, $context->value . ':' . trim($email));

        if (!$this->loginLimiter->consume($request)->isAccepted()) {
            throw new TooManyLoginAttemptsAuthenticationException();
        }
    }

    private function getUrlForSingleClinic(ActiveClinicResult $result): string
    {
        \assert(null !== $result->clinic);
        $this->currentClinicContext->setCurrentClinicId(ClinicId::fromString($result->clinic->clinicId));

        return $this->urlGenerator->generate('clinic_dashboard');
    }

    private function createSuccessResponse(Request $request, string $redirectUrl): Response
    {
        // Si la requête attend du JSON, renvoyer une réponse JSON
        if ('json' === $request->getContentTypeFormat() || 'application/json' === $request->headers->get('Accept')) {
            return new JsonResponse([
                'success'  => true,
                'redirect' => $redirectUrl,
            ]);
        }

        // Sinon, redirection classique
        return new RedirectResponse($redirectUrl);
    }

    private function resolveContext(Request $request): AuthenticationContext
    {
        $host = $request->getHost();

        if (str_contains($host, 'clinic.')) {
            return AuthenticationContext::CLINIC;
        }
        if (str_contains($host, 'portal.')) {
            return AuthenticationContext::PORTAL;
        }
        if (str_contains($host, 'backoffice.')) {
            return AuthenticationContext::BACKOFFICE;
        }

        throw new CustomUserMessageAuthenticationException('Unknown login context.');
    }

    private function loginRouteForContext(AuthenticationContext $context): string
    {
        return match ($context) {
            AuthenticationContext::CLINIC     => 'clinic_login',
            AuthenticationContext::PORTAL     => 'portal_login',
            AuthenticationContext::BACKOFFICE => 'backoffice_login',
        };
    }

    private function successRouteForContext(AuthenticationContext $context): string
    {
        return match ($context) {
            AuthenticationContext::CLINIC     => 'clinic_dashboard',
            AuthenticationContext::PORTAL     => 'portal_home',
            AuthenticationContext::BACKOFFICE => 'backoffice_home',
        };
    }
}
