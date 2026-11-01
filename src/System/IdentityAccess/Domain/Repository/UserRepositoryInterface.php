<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Repository;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserType;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    /**
     * Inserts a new account unless its e-mail address is already taken by an
     * account of the same type — by an invitation of the same address
     * committed meanwhile: false then.
     */
    public function addIfEmailFree(User $user): bool;

    /**
     * Inserts a new owner account unless its phone — or its e-mail — is
     * already taken by another owner account: false then. Never closes the
     * unit of work.
     */
    public function addPortalIfPhoneFree(User $user): bool;

    /**
     * Takes the per-phone mutex of owner accounts until the transaction ends:
     * concurrent claims of one phone queue here instead of racing (or
     * deadlocking on gap locks). It is the first phone-related lock taken.
     */
    public function lockPortalPhone(PhoneNumber $phone): void;

    /**
     * Deletes the phone's mutex row once nothing refers to the phone any more
     * (account deletion); its lock still lasts until the transaction ends.
     */
    public function deletePortalPhoneLock(PhoneNumber $phone): void;

    public function findById(UserId $id): ?User;

    /**
     * The account, its row locked until the end of the transaction: who
     * issues a link (invitation, reset) waits for whoever is issuing one.
     */
    public function findByIdForUpdate(UserId $id): ?User;

    /** The account of this type and address, read past the transaction's snapshot and locked. */
    public function findByEmailAndTypeForUpdate(string $email, UserType $type): ?User;

    public function findByEmailAndType(string $email, UserType $type): ?User;

    public function findByPhoneAndType(PhoneNumber $phone, UserType $type): ?User;

    /** The account of this type and phone, read past the transaction's snapshot and locked. */
    public function findByPhoneAndTypeForUpdate(PhoneNumber $phone, UserType $type): ?User;

    /**
     * Stamps the moment the user last opened a session. Kept out of the
     * aggregate: it is a trace of use, not a rule about the account.
     */
    public function recordLogin(UserId $id, \DateTimeImmutable $at): void;
}
