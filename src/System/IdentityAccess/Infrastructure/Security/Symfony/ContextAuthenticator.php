<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Security\Symfony;

use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\AccessControl\Application\Query\ResolveActiveClinic\ActiveClinicResult;
use App\System\AccessControl\Application\Query\ResolveActiveClinic\ActiveClinicResultType;
use App\System\AccessControl\Application\Query\ResolveActiveClinic\ResolveActiveClinic;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\AuthenticateUserHandler;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\AuthenticateUserQuery;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\AuthenticationContext;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\Exception\AuthenticationDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class ContextAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly AuthenticateUserHandler $handler,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
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
                fn () => new SecurityUser($identity->id, $identity->email, $identity->type, $identity->roles),
            ),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        $authContext = $this->resolveContext($request);
        $userId      = trim($token->getUserIdentifier());

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
        $previous = $exception->getPrevious();

        if ($previous instanceof AuthenticationDeniedException) {
            return new JsonResponse([
                'error' => [
                    'code'    => $previous->errorCode(),
                    'message' => $previous->getMessage(),
                ],
            ], $previous->httpStatusCode());
        }

        return new JsonResponse([
            'error' => [
                'code'    => 'AUTHENTICATION_FAILED',
                'message' => 'Authentication failed.',
            ],
        ], JsonResponse::HTTP_UNAUTHORIZED);
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
