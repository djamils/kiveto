<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Token;

interface AccountTokenRepositoryInterface
{
    /** Inserts a new token, or records that an existing one was used. */
    public function save(AccountToken $token): void;

    /** The token whose secret has this sha256, locked until the end of the transaction. */
    public function findByHashForUpdate(string $tokenHash): ?AccountToken;

    public function findByHash(string $tokenHash): ?AccountToken;

    /** Marks used every unused invitation of this user for this clinic; returns how many. */
    public function invalidateInvitations(string $userId, string $clinicId, \DateTimeImmutable $at): int;

    /** Marks used every unused password-reset token of this user; returns how many. */
    public function invalidatePasswordResets(string $userId, \DateTimeImmutable $at): int;

    /**
     * Among these users, those holding an invitation of this clinic still usable at $now.
     *
     * @param list<string> $userIds
     *
     * @return list<string>
     */
    public function usersWithUsableInvitation(string $clinicId, array $userIds, \DateTimeImmutable $now): array;
}
