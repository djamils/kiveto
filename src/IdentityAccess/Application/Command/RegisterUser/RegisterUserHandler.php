<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\RegisterUser;

use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\UserType;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\BackofficeUser;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\ClinicUser;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\PortalUser;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User as DoctrineUser;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler]
readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(RegisterUser $command): string
    {
        $userId    = UserId::fromString($this->uuidGenerator->generate());
        $createdAt = $this->clock->now();

        $passwordHash = $this->passwordHasher->hashPassword(
            $this->newEntityForType($command->type), // transient user just for hashing context
            $command->plainPassword,
        );

        $user = User::register(
            $userId,
            $command->email,
            $passwordHash,
            $createdAt,
            $command->type,
        );

        $this->userRepository->save($user);

        return $userId->toString();
    }

    private function newEntityForType(UserType $type): DoctrineUser
    {
        return match ($type) {
            UserType::CLINIC     => new ClinicUser(),
            UserType::PORTAL     => new PortalUser(),
            UserType::BACKOFFICE => new BackofficeUser(),
        };
    }
}
