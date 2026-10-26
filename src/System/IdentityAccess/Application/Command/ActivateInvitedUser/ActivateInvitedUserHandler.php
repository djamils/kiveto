<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ActivateInvitedUser;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Exception\InvalidAccountTokenException;
use App\System\IdentityAccess\Application\Exception\UnacceptablePasswordException;
use App\System\IdentityAccess\Application\Port\PasswordHasherInterface;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\Token\AccountToken;
use App\System\IdentityAccess\Domain\Token\AccountTokenRepositoryInterface;
use App\System\IdentityAccess\Domain\Token\TokenPurpose;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ActivateInvitedUserHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private AccountTokenRepositoryInterface $tokens,
        private PasswordHasherInterface $hasher,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(ActivateInvitedUser $command): ActivationResult
    {
        $now   = $this->clock->now();
        $token = $this->tokens->findByHashForUpdate(AccountToken::hash($command->token));

        if (null === $token || TokenPurpose::INVITATION !== $token->purpose() || !$token->isUsable($now)) {
            throw new InvalidAccountTokenException();
        }

        $user = $this->users->findById(UserId::fromString($token->userId())) ?? throw new InvalidAccountTokenException();

        if (UserStatus::ACTIVE === $user->status()) {
            $token->markUsed($now);
            $this->tokens->save($token);

            return ActivationResult::ALREADY_ACTIVE;
        }

        if (UserStatus::PENDING !== $user->status()) {
            throw new InvalidAccountTokenException();
        }

        UnacceptablePasswordException::check($command->plainPassword);

        $user->activate($this->hasher->hash($user->type(), $command->plainPassword), $now);
        $this->users->save($user);
        $token->markUsed($now);
        $this->tokens->save($token);
        $this->domainEventPublisher->publish($user);

        return ActivationResult::ACTIVATED;
    }
}
