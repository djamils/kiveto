<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Application\Command\RegisterUser;

use App\IdentityAccess\Application\Command\RegisterUser\RegisterUser;
use App\IdentityAccess\Application\Command\RegisterUser\RegisterUserHandler;
use App\IdentityAccess\Domain\Event\UserRegistered;
use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\ValueObject\UserType;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\BackofficeUser;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\ClinicUser;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\PortalUser;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Factory\DoctrineUserFactory;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class RegisterUserHandlerTest extends TestCase
{
    public function testRegistersUserAndRecordsEvent(): void
    {
        $savedUser  = null;
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (User $user) use (&$savedUser): bool {
                $savedUser = $user;

                return true;
            }))
        ;

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

        $handler = new RegisterUserHandler(
            $repository,
            $uuidGenerator,
            $clock,
            $passwordHasher,
            new DoctrineUserFactory(),
        );

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())
            ->method('publish')
            ->with([], self::isInstanceOf(UserRegistered::class))
        ;

        $eventPublisher = new DomainEventPublisher($eventBus);
        $handler->setDomainEventPublisher($eventPublisher);

        $userId = $handler(new RegisterUser(
            'user@example.com',
            'plain-password',
            UserType::CLINIC,
        ));

        self::assertInstanceOf(User::class, $savedUser);
        self::assertSame($userId, $savedUser->id()->toString());

        self::assertSame('user@example.com', $savedUser->email());
    }

    #[DataProvider('providePasswordHasherReceivesCorrectDoctrineUserForTypeCases')]
    public function testPasswordHasherReceivesCorrectDoctrineUserForType(UserType $type, string $expectedEntity): void
    {
        $savedUser  = null;
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (User $user) use (&$savedUser): void {
                $savedUser = $user;
            })
        ;

        $uuidGenerator = new class implements UuidGeneratorInterface {
            public function generate(): string
            {
                return '11111111-1111-1111-1111-111111111111';
            }
        };

        $clock = new FrozenClock(new \DateTimeImmutable('2025-01-02T10:00:00+00:00'));

        $capturedClass  = null;
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::once())
            ->method('hashPassword')
            ->with(
                self::callback(function (PasswordAuthenticatedUserInterface $user) use (&$capturedClass): bool {
                    $capturedClass = $user::class;

                    return true;
                }),
                'pw-' . $type->value,
            )
            ->willReturn('hashed-pw-' . $type->value)
        ;

        $handler = new RegisterUserHandler(
            $repository,
            $uuidGenerator,
            $clock,
            $passwordHasher,
            new DoctrineUserFactory(),
        );

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())
            ->method('publish')
            ->with([], self::isInstanceOf(UserRegistered::class))
        ;

        $eventPublisher = new DomainEventPublisher($eventBus);
        $handler->setDomainEventPublisher($eventPublisher);

        $userId = $handler(new RegisterUser(
            'user+' . $type->value . '@example.com',
            'pw-' . $type->value,
            $type,
        ));

        self::assertSame($expectedEntity, $capturedClass);

        self::assertNotNull($savedUser);
        self::assertSame('hashed-pw-' . $type->value, $savedUser->passwordHash());
    }

    /**
     * @return array<string, array{UserType, class-string}>
     */
    public static function providePasswordHasherReceivesCorrectDoctrineUserForTypeCases(): iterable
    {
        return [
            'clinic'     => [UserType::CLINIC, ClinicUser::class],
            'portal'     => [UserType::PORTAL, PortalUser::class],
            'backoffice' => [UserType::BACKOFFICE, BackofficeUser::class],
        ];
    }
}
