<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\RegisterUser;

use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Factory\DoctrineUserFactory;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\DependencyInjection\DomainEventPublisherAware;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler]
final class RegisterUserHandler
{
    use DomainEventPublisherAware;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UuidGeneratorInterface $uuidGenerator,
        private readonly ClockInterface $clock,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly DoctrineUserFactory $doctrineUserFactory,
    ) {
    }

    public function __invoke(RegisterUser $command): string
    {
        $userId = UserId::fromString($this->uuidGenerator->generate());
        $now    = $this->clock->now();

        $passwordHash = $this->passwordHasher->hashPassword(
            $this->doctrineUserFactory->createForType($command->type), // transient user just for hashing context
            $command->plainPassword,
        );

        $user = User::register(
            $userId,
            $command->email,
            $passwordHash,
            $now,
            $command->type,
        );

        $this->userRepository->save($user);

        $this->eventPublisher->publishFrom($user, $now);

        return $userId->toString();
    }
}
