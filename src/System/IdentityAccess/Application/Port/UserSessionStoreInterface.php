<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

use App\System\IdentityAccess\Application\Query\ListUserSessions\UserSessionView;

/**
 * The signed-in browsers of staff and back-office accounts, one row per web
 * session (its id is stored as a sha256 digest). A revoked row ends its
 * session at its next request.
 */
interface UserSessionStoreInterface
{
    public function find(string $sessionHash): ?UserSessionRecord;

    public function open(string $id, string $userId, string $sessionHash, ?string $userAgent, ?string $ipAddress, \DateTimeImmutable $at): void;

    public function touch(string $sessionHash, ?string $ipAddress, \DateTimeImmutable $at): void;

    /**
     * The session ended by its holder (logout): it leaves the list.
     */
    public function end(string $sessionHash, \DateTimeImmutable $at): void;

    public function revoke(string $userId, string $id, \DateTimeImmutable $at): bool;

    /**
     * @return int how many were revoked
     */
    public function revokeAll(string $userId, ?string $exceptSessionHash, \DateTimeImmutable $at): int;

    /**
     * The sessions not revoked nor ended, seen since `$since`, newest first.
     *
     * @return list<UserSessionView>
     */
    public function listActive(string $userId, \DateTimeImmutable $since, ?string $currentSessionHash): array;
}
