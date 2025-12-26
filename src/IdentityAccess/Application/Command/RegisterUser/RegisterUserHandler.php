<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\RegisterUser;

use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\UserId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RegisterUser $command): string
    {
        $userId    = UserId::fromString($this->uuidGenerator->generate());
        $createdAt = $this->clock->now();

        $user = User::register(
            $userId,
            $command->email,
            $command->passwordHash,
            $createdAt,
        );

        $this->userRepository->save($user);

        return $userId->toString();
    }
}
