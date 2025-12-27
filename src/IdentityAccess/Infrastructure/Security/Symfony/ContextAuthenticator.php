<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security\Symfony;

use App\IdentityAccess\Application\Query\AuthenticateUser\AuthenticateUserHandler;
use App\IdentityAccess\Application\Query\AuthenticateUser\AuthenticateUserQuery;
use App\IdentityAccess\Application\Query\AuthenticateUser\AuthenticationContext;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\AccountStatusNotAllowedException;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\AuthenticationContextMismatchException;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\EmailVerificationRequiredException;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\InvalidCredentialsException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class ContextAuthenticator extends AbstractAuthenticator
{
    public function __construct(private AuthenticateUserHandler $handler)
    {
    }

    public function supports(Request $request): ?bool
    {
        return 'POST' === $request->getMethod() && '/login' === $request->getPathInfo();
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $context = $this->resolveContext($request);
            $payload = json_decode((string) $request->getContent(), true, flags: \JSON_THROW_ON_ERROR);

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
        } catch (InvalidCredentialsException $e) {
            throw new CustomUserMessageAuthenticationException($e->getMessage(), [], 0, $e);
        } catch (AuthenticationContextMismatchException|AccountStatusNotAllowedException|EmailVerificationRequiredException $e) {
            throw new CustomUserMessageAuthenticationException($e->getMessage(), [], 0, $e);
        }

        return new SelfValidatingPassport(
            new UserBadge(
                $identity->id,
                fn () => new SecurityUser($identity->id, $identity->email, $identity->type, $identity->roles),
            ),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new JsonResponse(['message' => 'Authenticated'], JsonResponse::HTTP_OK);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $status = $this->statusCodeFor($exception);
        $message = $exception->getMessageKey();

        return new JsonResponse(['message' => $message], $status);
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

    private function statusCodeFor(AuthenticationException $exception): int
    {
        $previous = $exception->getPrevious();

        if ($previous instanceof InvalidCredentialsException) {
            return JsonResponse::HTTP_UNAUTHORIZED;
        }

        if ($previous instanceof AuthenticationContextMismatchException || $previous instanceof AccountStatusNotAllowedException || $previous instanceof EmailVerificationRequiredException) {
            return JsonResponse::HTTP_FORBIDDEN;
        }

        return JsonResponse::HTTP_UNAUTHORIZED;
    }
}

