<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Session;

interface RefreshTokenRepositoryInterface
{
    /** Inserts a new token or writes the state of a known one. */
    public function save(RefreshToken $token): void;

    /** Locked until the end of the transaction: concurrent refreshes of one token queue. */
    public function findByHashForUpdate(string $tokenHash): ?RefreshToken;

    public function findById(RefreshTokenId $id): ?RefreshToken;

    /** Revokes every token of the family (grace pairs included); returns how many. */
    public function revokeFamily(RefreshTokenFamilyId $familyId, \DateTimeImmutable $at): int;

    /** Revokes every live token of the user, whatever its family; returns how many. */
    public function revokeAllForUser(string $userId, \DateTimeImmutable $at): int;
}
