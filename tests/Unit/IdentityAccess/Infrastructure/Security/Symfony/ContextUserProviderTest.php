<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Infrastructure\Security\Symfony;

use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\UserType;
use App\IdentityAccess\Infrastructure\Security\Symfony\ContextUserProvider;
use App\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

final class ContextUserProviderTest extends TestCase
{
    public function testLoadUserByIdentifierReturnsSecurityUser(): void
    {
        $user = User::register(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            'clinic@example.com',
            '$hash',
            new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            UserType::CLINIC,
        );
        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findByEmailAndType')->willReturn($user);

        $provider = new ContextUserProvider($repo, UserType::CLINIC);

        $securityUser = $provider->loadUserByIdentifier('clinic@example.com');

        self::assertInstanceOf(SecurityUser::class, $securityUser);
        self::assertSame('clinic@example.com', $securityUser->getUserIdentifier());
        self::assertSame(UserType::CLINIC, $securityUser->type());
    }

    public function testLoadUserByIdentifierThrowsWhenNotFound(): void
    {
        $provider = new ContextUserProvider($this->createStub(UserRepositoryInterface::class), UserType::CLINIC);

        $this->expectException(UserNotFoundException::class);
        $provider->loadUserByIdentifier('missing@example.com');
    }

    public function testRefreshUserSupportsOnlySecurityUser(): void
    {
        $user = User::register(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            'clinic@example.com',
            '$hash',
            new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            UserType::CLINIC,
        );
        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findByEmailAndType')->willReturn($user);
        $provider = new ContextUserProvider($repo, UserType::CLINIC);

        $refreshed = $provider->refreshUser(new SecurityUser(
            id: $user->id()->toString(),
            email: $user->email(),
            type: $user->type(),
        ));

        self::assertInstanceOf(SecurityUser::class, $refreshed);

        $this->expectException(UnsupportedUserException::class);
        $provider->refreshUser(new class implements UserInterface {
            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'x';
            }
        });
    }

    public function testSupportsClass(): void
    {
        $provider = new ContextUserProvider($this->createStub(UserRepositoryInterface::class), UserType::CLINIC);

        self::assertTrue($provider->supportsClass(SecurityUser::class));
        self::assertFalse($provider->supportsClass(\stdClass::class));
    }
}
