<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Support;

use App\System\IdentityAccess\Domain\Session\RefreshToken;
use App\System\IdentityAccess\Domain\Session\RefreshTokenFamilyId;
use App\System\IdentityAccess\Domain\Session\RefreshTokenId;
use App\System\IdentityAccess\Domain\Session\RefreshTokenRepositoryInterface;

/**
 * Revocation rewrites the stored token through reconstitute(): the domain
 * object has no revoke method, the database does it in one UPDATE.
 */
final class InMemoryRefreshTokens implements RefreshTokenRepositoryInterface
{
    /** @var array<string, RefreshToken> by id */
    public array $tokens = [];

    /** @var list<string> digests read with a lock, in order */
    public array $locked = [];

    public function save(RefreshToken $token): void
    {
        $this->tokens[$token->id()->toString()] = $token;
    }

    public function findByHashForUpdate(string $tokenHash): ?RefreshToken
    {
        $this->locked[] = $tokenHash;

        foreach ($this->tokens as $token) {
            if ($token->tokenHash() === $tokenHash) {
                return $token;
            }
        }

        return null;
    }

    public function findById(RefreshTokenId $id): ?RefreshToken
    {
        return $this->tokens[$id->toString()] ?? null;
    }

    public function revokeFamily(RefreshTokenFamilyId $familyId, \DateTimeImmutable $at): int
    {
        $count = 0;

        foreach ($this->tokens as $id => $token) {
            if ($token->familyId()->equals($familyId) && !$token->isRevoked()) {
                $this->tokens[$id] = RefreshToken::reconstitute(
                    $token->id(),
                    $token->familyId(),
                    $token->userId(),
                    $token->tokenHash(),
                    $token->createdAt(),
                    $token->lastUsedAt(),
                    $token->expiresAt(),
                    $token->rotatedAt(),
                    $token->rotatedToId(),
                    $token->graceSuccessorId(),
                    $at,
                );
                ++$count;
            }
        }

        return $count;
    }

    public function revokeAllForUser(string $userId, \DateTimeImmutable $at): int
    {
        $count = 0;

        foreach ($this->tokens as $token) {
            if ($token->userId() === $userId && !$token->isRevoked()) {
                $count += $this->revokeFamily($token->familyId(), $at);
            }
        }

        return $count;
    }

    /**
     * The tokens of a family, in insertion order.
     *
     * @return list<RefreshToken>
     */
    public function family(RefreshTokenFamilyId $familyId): array
    {
        return array_values(array_filter($this->tokens, static fn (RefreshToken $t): bool => $t->familyId()->equals($familyId)));
    }
}
