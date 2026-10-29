<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Security\Symfony;

use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Factory\DoctrineUserFactory;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SymfonyPasswordHasher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class SymfonyPasswordHasherTest extends TestCase
{
    public function testTheConfiguredHasherHashesThePassword(): void
    {
        $factory = new PasswordHasherFactory([PasswordAuthenticatedUserInterface::class => ['algorithm' => 'bcrypt', 'cost' => 4]]);
        $hasher  = new SymfonyPasswordHasher(new UserPasswordHasher($factory), new DoctrineUserFactory());

        $hash = $hasher->hash(UserType::CLINIC, 'un-mot-de-passe');

        self::assertStringStartsWith('$2y$04$', $hash);
        self::assertTrue($factory->getPasswordHasher(PasswordAuthenticatedUserInterface::class)->verify($hash, 'un-mot-de-passe'));
    }
}
