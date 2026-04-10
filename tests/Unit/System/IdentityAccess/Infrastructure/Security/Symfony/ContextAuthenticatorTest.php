<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Security\Symfony;

use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\AccessibleClinic;
use App\Context\Clinic\Application\Query\Clinic\ResolveActiveClinic\ActiveClinicResult;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Application\Port\Security\PasswordHashVerifierInterface;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\AuthenticateUserHandler;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\Exception\InvalidCredentialsException;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\ContextAuthenticator;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
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

    public function testOnAuthenticationFailureReturnsGenericErrorForDomainException(): void
    {
        $authenticator = $this->authenticatorFor(UserType::CLINIC);
        $request       = Request::create('https://clinic.example/login', 'POST');
        $response      = $authenticator->onAuthenticationFailure(
            $request,
            new AuthenticationException(previous: new InvalidCredentialsException()),
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('AUTHENTICATION_FAILED', (string) $response->getContent());
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

        $response = $authenticator->onAuthenticationSuccess(
            Request::create('https://clinic.example/login', 'POST', server: ['HTTP_HOST' => 'clinic.example']),
            $this->tokenWithUser(),
            'main',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testOnAuthenticationSuccessReturnsJsonResponseWhenAcceptHeaderIsJson(): void
    {
        $authenticator = $this->authenticatorFor(UserType::PORTAL);

        $request = Request::create(
            'https://portal.example/login',
            'POST',
            server: ['HTTP_HOST' => 'portal.example'],
        );
        $request->headers->set('Accept', 'application/json');

        $response = $authenticator->onAuthenticationSuccess($request, $this->tokenWithUser('user-456', UserType::PORTAL), 'main');

        self::assertInstanceOf(JsonResponse::class, $response);
        $payload = json_decode((string) $response->getContent(), true);
        self::assertSame(['success' => true, 'redirect' => '/portal_home'], $payload);
    }

    public function testOnAuthenticationSuccessReturnsJsonResponseWhenContentTypeIsJson(): void
    {
        $authenticator = $this->authenticatorFor(UserType::BACKOFFICE);

        $request = Request::create(
            'https://backoffice.example/login',
            'POST',
            server: [
                'HTTP_HOST'    => 'backoffice.example',
                'CONTENT_TYPE' => 'application/json',
            ],
        );

        $response = $authenticator->onAuthenticationSuccess($request, $this->tokenWithUser('user-789', UserType::BACKOFFICE), 'main');

        self::assertInstanceOf(JsonResponse::class, $response);
        $payload = json_decode((string) $response->getContent(), true);
        self::assertSame(['success' => true, 'redirect' => '/backoffice_home'], $payload);
    }

    public function testOnAuthenticationSuccessWithEmptyUserId(): void
    {
        $authenticator = $this->authenticatorFor(UserType::CLINIC);

        $response = $authenticator->onAuthenticationSuccess(
            Request::create('https://clinic.example/login', 'POST', server: ['HTTP_HOST' => 'clinic.example']),
            $this->tokenWithoutUser(),
            'main',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/clinic_login', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessWithEmptyUserIdForPortal(): void
    {
        $authenticator = $this->authenticatorFor(UserType::PORTAL);

        $response = $authenticator->onAuthenticationSuccess(
            Request::create('https://portal.example/login', 'POST', server: ['HTTP_HOST' => 'portal.example']),
            $this->tokenWithoutUser(),
            'main',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/portal_login', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessWithEmptyUserIdForBackoffice(): void
    {
        $authenticator = $this->authenticatorFor(UserType::BACKOFFICE);

        $response = $authenticator->onAuthenticationSuccess(
            Request::create('https://backoffice.example/login', 'POST', server: ['HTTP_HOST' => 'backoffice.example']),
            $this->tokenWithoutUser(),
            'main',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/backoffice_login', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessForPortalContext(): void
    {
        $authenticator = $this->authenticatorFor(UserType::PORTAL);

        $response = $authenticator->onAuthenticationSuccess(
            Request::create('https://portal.example/login', 'POST', server: ['HTTP_HOST' => 'portal.example']),
            $this->tokenWithUser('user-456', UserType::PORTAL),
            'main',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/portal_home', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessForBackofficeContext(): void
    {
        $authenticator = $this->authenticatorFor(UserType::BACKOFFICE);

        $response = $authenticator->onAuthenticationSuccess(
            Request::create('https://backoffice.example/login', 'POST', server: ['HTTP_HOST' => 'backoffice.example']),
            $this->tokenWithUser('user-789', UserType::BACKOFFICE),
            'main',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/backoffice_home', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessWithSingleClinic(): void
    {
        $clinic = new AccessibleClinic(
            clinicId: '11111111-1111-1111-1111-111111111111',
            clinicName: 'Test Clinic',
            clinicSlug: 'test-clinic',
            clinicStatus: 'active',
            memberRole: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable(),
            validUntil: null,
            isDefault: false,
        );

        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn(ActiveClinicResult::single($clinic));

        $currentClinicContext = $this->createMock(CurrentClinicContextInterface::class);
        $currentClinicContext->expects(self::once())
            ->method('setCurrentClinicId')
            ->with(self::callback(static function (mixed $clinicId): bool {
                return $clinicId instanceof \App\Context\Clinic\Domain\ValueObject\ClinicId
                    && '11111111-1111-1111-1111-111111111111' === $clinicId->toString();
            }))
        ;

        $authenticator = new ContextAuthenticator(
            $this->handlerFor(UserType::CLINIC),
            $this->urlGenerator(),
            $queryBus,
            $currentClinicContext
        );

        $response = $authenticator->onAuthenticationSuccess(
            Request::create('https://clinic.example/login', 'POST', server: ['HTTP_HOST' => 'clinic.example']),
            $this->tokenWithUser(),
            'main',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/clinic_dashboard', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessWithMultipleClinics(): void
    {
        $clinic1 = new AccessibleClinic(
            clinicId: '11111111-1111-1111-1111-111111111111',
            clinicName: 'Clinic 1',
            clinicSlug: 'clinic-1',
            clinicStatus: 'active',
            memberRole: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable(),
            validUntil: null,
            isDefault: false,
        );

        $clinic2 = new AccessibleClinic(
            clinicId: '22222222-2222-2222-2222-222222222222',
            clinicName: 'Clinic 2',
            clinicSlug: 'clinic-2',
            clinicStatus: 'active',
            memberRole: ClinicMemberRole::MANAGER,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
            validFrom: new \DateTimeImmutable(),
            validUntil: null,
            isDefault: false,
        );

        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn(ActiveClinicResult::multiple([$clinic1, $clinic2]));

        $currentClinicContext = $this->createStub(CurrentClinicContextInterface::class);

        $authenticator = new ContextAuthenticator(
            $this->handlerFor(UserType::CLINIC),
            $this->urlGenerator(),
            $queryBus,
            $currentClinicContext
        );

        $response = $authenticator->onAuthenticationSuccess(
            Request::create('https://clinic.example/login', 'POST', server: ['HTTP_HOST' => 'clinic.example']),
            $this->tokenWithUser(),
            'main',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/clinic_select_clinic', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessWithNoClinics(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn(ActiveClinicResult::none());

        $currentClinicContext = $this->createStub(CurrentClinicContextInterface::class);

        $authenticator = new ContextAuthenticator(
            $this->handlerFor(UserType::CLINIC),
            $this->urlGenerator(),
            $queryBus,
            $currentClinicContext
        );

        $response = $authenticator->onAuthenticationSuccess(
            Request::create('https://clinic.example/login', 'POST', server: ['HTTP_HOST' => 'clinic.example']),
            $this->tokenWithUser(),
            'main',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/clinic_no_access', $response->getTargetUrl());
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

    private function tokenWithUser(string $userId = 'user-123', UserType $type = UserType::CLINIC): TokenInterface
    {
        $securityUser = new SecurityUser($userId, 'test@example.com', $type);
        $token        = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($securityUser);
        $token->method('getUserIdentifier')->willReturn('test@example.com');

        return $token;
    }

    private function tokenWithoutUser(): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        $token->method('getUserIdentifier')->willReturn('');

        return $token;
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
