<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\CheckAccountToken;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\Token\AccountToken;
use App\System\IdentityAccess\Domain\Token\AccountTokenRepositoryInterface;
use App\System\IdentityAccess\Domain\Token\TokenPurpose;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CheckAccountTokenHandler
{
    public function __construct(
        private AccountTokenRepositoryInterface $tokens,
        private UserRepositoryInterface $users,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CheckAccountToken $query): AccountTokenCheck
    {
        $token = $this->tokens->findByHash(AccountToken::hash($query->token));

        if (null === $token || TokenPurpose::from($query->purpose) !== $token->purpose() || !$token->isUsable($this->clock->now())) {
            return new AccountTokenCheck(AccountTokenCheck::INVALID);
        }

        $user     = $this->users->findById(UserId::fromString($token->userId()));
        $expected = TokenPurpose::INVITATION === $token->purpose() ? UserStatus::PENDING : UserStatus::ACTIVE;

        return match (true) {
            null === $user                                                                           => new AccountTokenCheck(AccountTokenCheck::INVALID),
            TokenPurpose::INVITATION === $token->purpose() && UserStatus::ACTIVE === $user->status() => new AccountTokenCheck(AccountTokenCheck::ALREADY_ACTIVE),
            $expected !== $user->status()                                                            => new AccountTokenCheck(AccountTokenCheck::INVALID),
            default                                                                                  => new AccountTokenCheck(AccountTokenCheck::VALID, $user->email()),
        };
    }
}
