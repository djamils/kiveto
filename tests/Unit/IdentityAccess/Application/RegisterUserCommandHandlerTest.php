<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Application;

use App\IdentityAccess\Application\Command\RegisterUser\RegisterUser;
use App\IdentityAccess\Application\Command\RegisterUser\RegisterUserHandler;
use App\IdentityAccess\Domain\ValueObject\UserType;
use App\IdentityAccess\Infrastructure\Repository\InMemoryUserRepository;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class RegisterUserCommandHandlerTest extends TestCase
{
    public function testRegistersUserAndRecordsEvent(): void
    {
        $repository = new InMemoryUserRepository();

        $uuidGenerator = new class implements UuidGeneratorInterface {
            private int $counter = 0;

            public function generate(): string
            {
                ++$this->counter;

                return \sprintf('00000000-0000-0000-0000-%012d', $this->counter);
            }
        };

        $clock = new FrozenClock(new \DateTimeImmutable('2025-01-01T10:00:00+00:00'));

        $passwordHasher = new class implements UserPasswordHasherInterface {
            public function hashPassword(PasswordAuthenticatedUserInterface $user, string $plainPassword): string
            {
                return '$hashed-password';
            }

            public function isPasswordValid(PasswordAuthenticatedUserInterface $user, string $plainPassword): bool
            {
                return true;
            }

            public function needsRehash(PasswordAuthenticatedUserInterface $user): bool
            {
                return false;
            }
        };

        $handler = new RegisterUserHandler($repository, $uuidGenerator, $clock, $passwordHasher);

        $userId = $handler(new RegisterUser(
            'user@example.com',
            'plain-password',
            UserType::CLINIC,
        ));

        $stored = $repository->findById(\App\IdentityAccess\Domain\ValueObject\UserId::fromString($userId));

        self::assertNotNull($stored);
        self::assertSame('user@example.com', $stored->email());
        self::assertCount(1, $stored->recordedDomainEvents());
        self::assertSame('identity-access.user.registered.v1', $stored->recordedDomainEvents()[0]->type());
    }
}
