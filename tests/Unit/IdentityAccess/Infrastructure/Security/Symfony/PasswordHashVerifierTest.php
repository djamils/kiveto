<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Infrastructure\Security\Symfony;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\IdentityAccess\Infrastructure\Security\Symfony\PasswordHashVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final class PasswordHashVerifierTest extends TestCase
{
    public function testVerifyDelegatesToHasher(): void
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects(self::once())
            ->method('verify')
            ->with('$hash', 'plain')
            ->willReturn(true)
        ;

        $factory = $this->createMock(PasswordHasherFactoryInterface::class);
        $factory->expects(self::once())
            ->method('getPasswordHasher')
            ->with(User::class)
            ->willReturn($hasher)
        ;

        $verifier = new PasswordHashVerifier($factory);

        self::assertTrue($verifier->verify('plain', '$hash'));
    }
}
