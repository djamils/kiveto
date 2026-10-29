<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Repository;

use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserType;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    /**
     * Inserts a new account unless its e-mail address is already taken —
     * by an invitation of the same address committed meanwhile: false then.
     */
    public function addIfEmailFree(User $user): bool;

    public function findById(UserId $id): ?User;

    /**
     * The account, its row locked until the end of the transaction: who
     * issues a link (invitation, reset) waits for whoever is issuing one.
     */
    public function findByIdForUpdate(UserId $id): ?User;

    /** The account of this address, read past the transaction's snapshot and locked. */
    public function findByEmailForUpdate(string $email): ?User;

    public function findByEmail(string $email): ?User;

    public function findByEmailAndType(string $email, UserType $type): ?User;

    /**
     * Stamps the moment the user last opened a session. Kept out of the
     * aggregate: it is a trace of use, not a rule about the account.
     */
    public function recordLogin(UserId $id, \DateTimeImmutable $at): void;
}
