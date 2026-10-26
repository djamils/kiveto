<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Support;

use App\System\IdentityAccess\Domain\Token\AccountToken;
use App\System\IdentityAccess\Domain\Token\AccountTokenRepositoryInterface;
use App\System\IdentityAccess\Domain\Token\TokenPurpose;

final class InMemoryAccountTokens implements AccountTokenRepositoryInterface
{
    /** @var array<string, AccountToken> by id */
    public array $tokens = [];

    public function save(AccountToken $token): void
    {
        $this->tokens[$token->id()->toString()] = $token;
    }

    public function findByHashForUpdate(string $tokenHash): ?AccountToken
    {
        return $this->findByHash($tokenHash);
    }

    public function findByHash(string $tokenHash): ?AccountToken
    {
        foreach ($this->tokens as $token) {
            if ($token->tokenHash() === $tokenHash) {
                return $token;
            }
        }

        return null;
    }

    public function invalidateInvitations(string $userId, string $clinicId, \DateTimeImmutable $at): int
    {
        return $this->invalidate(static fn (AccountToken $t): bool => $t->userId() === $userId && TokenPurpose::INVITATION === $t->purpose() && $t->clinicId() === $clinicId, $at);
    }

    public function invalidatePasswordResets(string $userId, \DateTimeImmutable $at): int
    {
        return $this->invalidate(static fn (AccountToken $t): bool => $t->userId() === $userId && TokenPurpose::PASSWORD_RESET === $t->purpose(), $at);
    }

    public function usersWithUsableInvitation(string $clinicId, array $userIds, \DateTimeImmutable $now): array
    {
        $users = [];

        foreach ($this->tokens as $token) {
            if (TokenPurpose::INVITATION === $token->purpose() && $token->clinicId() === $clinicId && \in_array($token->userId(), $userIds, true) && $token->isUsable($now)) {
                $users[] = $token->userId();
            }
        }

        return array_values(array_unique($users));
    }

    /**
     * The tokens of this user and purpose, oldest first.
     *
     * @return list<AccountToken>
     */
    public function of(string $userId, TokenPurpose $purpose): array
    {
        return array_values(array_filter($this->tokens, static fn (AccountToken $t): bool => $t->userId() === $userId && $t->purpose() === $purpose));
    }

    /**
     * @param callable(AccountToken): bool $matches
     */
    private function invalidate(callable $matches, \DateTimeImmutable $at): int
    {
        $count = 0;

        foreach ($this->tokens as $token) {
            if ($matches($token) && null === $token->usedAt()) {
                $token->markUsed($at);
                ++$count;
            }
        }

        return $count;
    }
}
