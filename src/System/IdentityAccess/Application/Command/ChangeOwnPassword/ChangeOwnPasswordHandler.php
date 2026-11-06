<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ChangeOwnPassword;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Exception\UnacceptablePasswordException;
use App\System\IdentityAccess\Application\Port\PasswordHasherInterface;
use App\System\IdentityAccess\Application\Port\Security\PasswordHashVerifierInterface;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\Exception\InvalidCredentialsException;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangeOwnPasswordHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHashVerifierInterface $verifier,
        private PasswordHasherInterface $hasher,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(ChangeOwnPassword $command): void
    {
        $user = $this->users->findByIdForUpdate(UserId::fromString($command->userId));

        if (null === $user || UserStatus::ACTIVE !== $user->status() || !$this->verifier->verify($command->currentPassword, $user->passwordHash())) {
            throw new InvalidCredentialsException();
        }

        UnacceptablePasswordException::check($command->newPassword);

        $user->changePassword($this->hasher->hash($user->type(), $command->newPassword), $this->clock->now());
        $this->users->save($user);
        $this->domainEventPublisher->publish($user);
    }
}
