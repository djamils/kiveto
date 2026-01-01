<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Infrastructure\Security\Symfony;

use App\IdentityAccess\Domain\ValueObject\UserType;
use App\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use PHPUnit\Framework\TestCase;

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
}
