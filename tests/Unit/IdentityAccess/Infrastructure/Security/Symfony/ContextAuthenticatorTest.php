<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Infrastructure\Security\Symfony;

use App\AccessControl\Application\Query\ResolveActiveClinic\ActiveClinicResult;
use App\IdentityAccess\Application\Port\Security\PasswordHashVerifierInterface;
use App\IdentityAccess\Application\Query\AuthenticateUser\AuthenticateUserHandler;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\InvalidCredentialsException;
use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\UserStatus;
use App\IdentityAccess\Domain\ValueObject\UserType;
use App\IdentityAccess\Infrastructure\Security\Symfony\ContextAuthenticator;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final class ContextAuthenticatorTest extends TestCase
{
    public function testSupportsOnlyLoginPost(): void
    {
        $authenticator = $this->authenticatorFor(UserType::CLINIC);

        self::assertTrue($authenticator->supports(Request::create('/login', 'POST')));
        self::assertFalse($authenticator->supports(Request::create('/login', 'GET')));
        self::assertFalse($authenticator->supports(Request::create('/other', 'POST')));
    }

    public function testAuthenticateReturnsPassport(): void
    {
        $authenticator = $this->authenticatorFor(UserType::CLINIC);
        $request       = Request::create(
            'https://clinic.example/login',
            'POST',
            server: ['HTTP_HOST' => 'clinic.example'],
            content: json_encode(['email' => 'user@example.com', 'password' => 'secret'], \JSON_THROW_ON_ERROR),
        );

        $passport = $authenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        self::assertNotNull($userBadge);
        self::assertSame('11111111-1111-1111-1111-111111111111', $userBadge->getUserIdentifier());
    }

    public function testAuthenticateThrowsOnInvalidJson(): void
    {
        $authenticator = $this->authenticatorFor(UserType::CLINIC);
        $request       = Request::create(
            'https://clinic.example/login',
            'POST',
            server: ['HTTP_HOST' => 'clinic.example'],
            content: '{bad json',
        );

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsOnInvalidPayloadStructure(): void
    {
        $authenticator = $this->authenticatorFor(UserType::CLINIC);
        $request       = Request::create(
            'https://clinic.example/login',
            'POST',
            server: ['HTTP_HOST' => 'clinic.example'],
            content: json_encode('not-an-array', \JSON_THROW_ON_ERROR),
        );

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsOnContextError(): void
    {
        $authenticator = $this->authenticatorFor(UserType::CLINIC);
        $request       = Request::create(
            'https://unknown.example/login',
            'POST',
            server: ['HTTP_HOST' => 'unknown.example'],
            content: json_encode(['email' => 'user@example.com', 'password' => 'secret'], \JSON_THROW_ON_ERROR),
        );

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $authenticator->authenticate($request);
    }

    public function testOnAuthenticationFailureForDomainException(): void
    {
        $authenticator = $this->authenticatorFor(UserType::CLINIC);
        $request       = Request::create('https://clinic.example/login', 'POST');
        $response      = $authenticator->onAuthenticationFailure(
            $request,
            new AuthenticationException(previous: new InvalidCredentialsException()),
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('INVALID_CREDENTIALS', (string) $response->getContent());
    }

    public function testOnAuthenticationFailureDefault(): void
    {
        $authenticator = $this->authenticatorFor(UserType::CLINIC);
        $request       = Request::create('https://clinic.example/login', 'POST');
        $response      = $authenticator->onAuthenticationFailure($request, new AuthenticationException());

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('AUTHENTICATION_FAILED', (string) $response->getContent());
    }

    public function testOnAuthenticationSuccess(): void
    {
        $authenticator = $this->authenticatorFor(UserType::CLINIC);

        $token = $this->createStub(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);
        $token->method('getUserIdentifier')->willReturn('user-123');

        $response = $authenticator->onAuthenticationSuccess(
            Request::create('https://clinic.example/login', 'POST', server: ['HTTP_HOST' => 'clinic.example']),
            $token,
            'main',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testAuthenticateThrowsOnInvalidCredentialsPayload(): void
    {
        $authenticator = $this->authenticatorFor(UserType::CLINIC);
        $request       = Request::create(
            'https://clinic.example/login',
            'POST',
            server: ['HTTP_HOST' => 'clinic.example'],
            content: json_encode(['email' => '', 'password' => 'secret'], \JSON_THROW_ON_ERROR),
        );

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsOnAuthenticationDeniedWrapped(): void
    {
        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn(null); // will trigger InvalidCredentialsException

        $verifier = $this->createStub(PasswordHashVerifierInterface::class);
        $handler  = new AuthenticateUserHandler($repo, $verifier);

        $queryBus             = $this->createStub(QueryBusInterface::class);
        $currentClinicContext = $this->createStub(CurrentClinicContextInterface::class);

        $authenticator = new ContextAuthenticator($handler, $this->urlGenerator(), $queryBus, $currentClinicContext);
        $request       = Request::create(
            'https://clinic.example/login',
            'POST',
            server: ['HTTP_HOST' => 'clinic.example'],
            content: json_encode(['email' => 'user@example.com', 'password' => 'secret'], \JSON_THROW_ON_ERROR),
        );

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $authenticator->authenticate($request);
    }

    public function testAuthenticateSupportsPortalAndBackofficeContexts(): void
    {
        $authenticatorPortal = $this->authenticatorFor(UserType::PORTAL);
        $passportPortal      = $authenticatorPortal->authenticate(Request::create(
            'https://portal.example/login',
            'POST',
            server: ['HTTP_HOST' => 'portal.example'],
            content: json_encode(['email' => 'portal@example.com', 'password' => 'secret'], \JSON_THROW_ON_ERROR),
        ));

        self::assertNotNull($passportPortal->getBadge(UserBadge::class));

        $authenticatorBo = $this->authenticatorFor(UserType::BACKOFFICE);
        $passportBo      = $authenticatorBo->authenticate(Request::create(
            'https://backoffice.example/login',
            'POST',
            server: ['HTTP_HOST' => 'backoffice.example'],
            content: json_encode(['email' => 'bo@example.com', 'password' => 'secret'], \JSON_THROW_ON_ERROR),
        ));

        self::assertNotNull($passportBo->getBadge(UserBadge::class));
    }

    public function testStartRedirectsToClinicLoginForClinicHost(): void
    {
        $authenticator = $this->authenticatorFor(UserType::CLINIC);
        $request       = Request::create(
            'https://clinic.example/protected',
            server: ['HTTP_HOST' => 'clinic.example']
        );

        $response = $authenticator->start($request);

        self::assertSame('/clinic_login', $response->getTargetUrl());
        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testStartRedirectsToPortalLoginForPortalHost(): void
    {
        $authenticator = $this->authenticatorFor(UserType::PORTAL);
        $request       = Request::create(
            'https://portal.example/protected',
            server: ['HTTP_HOST' => 'portal.example']
        );

        $response = $authenticator->start($request);

        self::assertSame('/portal_login', $response->getTargetUrl());
        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testStartRedirectsToBackofficeLoginForBackofficeHost(): void
    {
        $authenticator = $this->authenticatorFor(UserType::BACKOFFICE);
        $request       = Request::create(
            'https://backoffice.example/protected',
            server: ['HTTP_HOST' => 'backoffice.example']
        );

        $response = $authenticator->start($request);

        self::assertSame('/backoffice_login', $response->getTargetUrl());
        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testStartRedirectsToClinicLoginByDefaultForUnknownHost(): void
    {
        $authenticator = $this->authenticatorFor(UserType::CLINIC);
        $request       = Request::create(
            'https://unknown.example/protected',
            server: ['HTTP_HOST' => 'unknown.example']
        );

        $response = $authenticator->start($request);

        self::assertSame('/clinic_login', $response->getTargetUrl());
        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testStartCanHandleAuthenticationException(): void
    {
        $authenticator = $this->authenticatorFor(UserType::CLINIC);
        $request       = Request::create(
            'https://clinic.example/protected',
            server: ['HTTP_HOST' => 'clinic.example']
        );
        $authException = new AuthenticationException('Test exception');

        $response = $authenticator->start($request, $authException);

        self::assertSame('/clinic_login', $response->getTargetUrl());
        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    private function handlerFor(UserType $type): AuthenticateUserHandler
    {
        $user = User::reconstitute(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            match ($type) {
                UserType::CLINIC     => 'user@example.com',
                UserType::PORTAL     => 'portal@example.com',
                UserType::BACKOFFICE => 'bo@example.com',
            },
            '$hash',
            new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            UserStatus::ACTIVE,
            new \DateTimeImmutable('2025-01-02T10:00:00+00:00'),
            $type,
        );

        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn($user);

        $verifier = new class implements PasswordHashVerifierInterface {
            public function verify(string $plainPassword, string $passwordHash): bool
            {
                return 'secret' === $plainPassword && '$hash' === $passwordHash;
            }
        };

        return new AuthenticateUserHandler($repo, $verifier);
    }

    private function authenticatorFor(UserType $type): ContextAuthenticator
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn(ActiveClinicResult::none());

        $currentClinicContext = $this->createStub(CurrentClinicContextInterface::class);

        return new ContextAuthenticator(
            $this->handlerFor($type),
            $this->urlGenerator(),
            $queryBus,
            $currentClinicContext
        );
    }

    private function urlGenerator(): UrlGeneratorInterface
    {
        return new class implements UrlGeneratorInterface {
            /**
             * @param array<string, string|int> $parameters
             */
            public function generate(
                string $name,
                array $parameters = [],
                int $referenceType = self::ABSOLUTE_PATH,
            ): string {
                return '/' . $name;
            }

            public function setContext(RequestContext $context): void
            {
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }
        };
    }
}
