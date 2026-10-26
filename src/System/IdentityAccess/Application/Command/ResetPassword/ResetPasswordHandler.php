<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ResetPassword;

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

/**
 * The new password replaces the old one; the link and every other reset
 * link of the user are spent. The changed password fingerprint closes the
 * user's open sessions (SecurityUser).
 */
#[AsMessageHandler]
final readonly class ResetPasswordHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private AccountTokenRepositoryInterface $tokens,
        private PasswordHasherInterface $hasher,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(ResetPassword $command): void
    {
        $now   = $this->clock->now();
        $token = $this->tokens->findByHashForUpdate(AccountToken::hash($command->token));

        if (null === $token || TokenPurpose::PASSWORD_RESET !== $token->purpose() || !$token->isUsable($now)) {
            throw new InvalidAccountTokenException();
        }

        $user = $this->users->findById(UserId::fromString($token->userId()));

        if (null === $user || UserStatus::ACTIVE !== $user->status()) {
            throw new InvalidAccountTokenException();
        }

        UnacceptablePasswordException::check($command->plainPassword);

        $user->changePassword($this->hasher->hash($user->type(), $command->plainPassword), $now);
        $this->users->save($user);
        $token->markUsed($now);
        $this->tokens->save($token);
        $this->tokens->invalidatePasswordResets($token->userId(), $now);
        $this->domainEventPublisher->publish($user);
    }
}
