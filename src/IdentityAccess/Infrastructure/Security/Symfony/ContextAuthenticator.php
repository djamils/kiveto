<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security\Symfony;

use App\IdentityAccess\Application\Query\AuthenticateUser\AuthenticateUserHandler;
use App\IdentityAccess\Application\Query\AuthenticateUser\AuthenticateUserQuery;
use App\IdentityAccess\Application\Query\AuthenticateUser\AuthenticationContext;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\AuthenticationDeniedException;
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
    )
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): RedirectResponse
    {
        $host = $request->getHost();

        $route = match (true) {
            str_starts_with($host, 'clinic.') => 'clinic_login',
            str_starts_with($host, 'portal.') => 'portal_login',
            str_starts_with($host, 'backoffice.') => 'backoffice_login',
            default => 'clinic_login',
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
            $context = $this->resolveContext($request);
            $payload = json_decode((string) $request->getContent(), true, flags: \JSON_THROW_ON_ERROR);

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
                context: $context,
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
        return new JsonResponse(['message' => 'Authenticated'], JsonResponse::HTTP_OK);
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
}
