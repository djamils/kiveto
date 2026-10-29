<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Security\Symfony;

use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class SecurityUserTest extends TestCase
{
    public function testExposesIdentifierAndType(): void
    {
        $user = new SecurityUser('id-1', 'user@example.com', UserType::PORTAL, ['ROLE_USER', 'ROLE_PORTAL']);

        self::assertSame('user@example.com', $user->getUserIdentifier());
        self::assertSame(['ROLE_USER', 'ROLE_PORTAL'], $user->getRoles());
        self::assertSame('id-1', $user->id());
        self::assertSame(UserType::PORTAL, $user->type());
        self::assertSame('', $user->getPassword());
    }

    public function testEmptyIdentifierThrows(): void
    {
        $user = new SecurityUser('id-1', '', UserType::CLINIC);

        $this->expectException(\LogicException::class);
        $user->getUserIdentifier();
    }

    public function testEraseCredentialsIsNoOp(): void
    {
        $user = new SecurityUser('id-1', 'user@example.com', UserType::CLINIC, ['ROLE_USER']);
        $user->eraseCredentials(); // should not throw

        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testASessionEndsWhenThePasswordFingerprintChanges(): void
    {
        $user = new SecurityUser('id-1', 'user@example.com', UserType::CLINIC, ['ROLE_USER'], 'abc');

        self::assertTrue($user->isEqualTo(new SecurityUser('id-1', 'other@example.com', UserType::CLINIC, ['ROLE_USER'], 'abc')));
        self::assertFalse($user->isEqualTo(new SecurityUser('id-1', 'user@example.com', UserType::CLINIC, ['ROLE_USER'], 'def')), 'password reset elsewhere');
        self::assertFalse($user->isEqualTo(new SecurityUser('id-2', 'user@example.com', UserType::CLINIC, ['ROLE_USER'], 'abc')));
        self::assertFalse($user->isEqualTo(new SecurityUser('id-1', 'user@example.com', UserType::PORTAL, ['ROLE_USER'], 'abc')));
        self::assertFalse($user->isEqualTo(new InMemoryUser('user@example.com', null)));
    }

    public function testASessionSavedBeforeTheFingerprintExistedEndsOnce(): void
    {
        $legacy = new \ReflectionClass(SecurityUser::class)->newInstanceWithoutConstructor();

        self::assertFalse($legacy->isEqualTo(new SecurityUser('id-1', 'user@example.com', UserType::CLINIC)));
    }
}
