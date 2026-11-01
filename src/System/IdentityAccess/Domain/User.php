<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Domain\Event\PortalUserRegistered;
use App\System\IdentityAccess\Domain\Event\UserActivated;
use App\System\IdentityAccess\Domain\Event\UserInvited;
use App\System\IdentityAccess\Domain\Event\UserPasswordChanged;
use App\System\IdentityAccess\Domain\Event\UserRegistered;
use App\System\IdentityAccess\Domain\Exception\AccountStateException;
use App\System\IdentityAccess\Domain\Exception\EmailRequiredException;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;

/**
 * A Kiveto account. Staff accounts (CLINIC, BACKOFFICE) sign in with their
 * e-mail address and a password. Owner accounts (PORTAL) sign in with their
 * mobile phone and a one-time code: no password, the e-mail is optional.
 * Creation rules live in the named constructors only; reconstitute() checks
 * nothing, so that legacy rows always load.
 */
final class User extends AggregateRoot
{
    /** No password hasher produces a hash starting with « ! ». */
    private const string UNUSABLE_PASSWORD_PREFIX = '!';

    private UserId $id;
    private ?string $email;
    private ?PhoneNumber $phone                  = null;
    private ?\DateTimeImmutable $phoneVerifiedAt = null;
    private string $passwordHash;
    private \DateTimeImmutable $createdAt;
    private UserStatus $status;
    private ?\DateTimeImmutable $emailVerifiedAt = null;
    private UserType $type;
    private ?\DateTimeImmutable $anonymisedAt = null;

    private function __construct()
    {
    }

    public static function register(
        UserId $id,
        string $email,
        string $passwordHash,
        \DateTimeImmutable $createdAt,
        UserType $type,
    ): self {
        self::assertStaffEmail($type, $email);

        $user               = new self();
        $user->id           = $id;
        $user->email        = $email;
        $user->passwordHash = $passwordHash;
        $user->createdAt    = $createdAt;
        $user->status       = UserStatus::ACTIVE;
        $user->type         = $type;

        $user->recordDomainEvent(new UserRegistered(
            userId: $id->toString(),
            email: $email,
        ));

        return $user;
    }

    /**
     * A person invited to a clinic: the account exists, PENDING, with a hash
     * no password matches, until they choose their own password.
     */
    public static function invite(
        UserId $id,
        string $email,
        \DateTimeImmutable $createdAt,
        UserType $type,
    ): self {
        self::assertStaffEmail($type, $email);

        $user               = new self();
        $user->id           = $id;
        $user->email        = $email;
        $user->passwordHash = self::UNUSABLE_PASSWORD_PREFIX . bin2hex(random_bytes(16));
        $user->createdAt    = $createdAt;
        $user->status       = UserStatus::PENDING;
        $user->type         = $type;

        $user->recordDomainEvent(new UserInvited(
            userId: $id->toString(),
            email: $email,
        ));

        return $user;
    }

    /**
     * An owner who proved their mobile phone (one-time code): ACTIVE at once,
     * without password. The e-mail, copied from a clinic's client file, is
     * not verified.
     */
    public static function registerPortal(
        UserId $id,
        PhoneNumber $phone,
        ?string $email,
        \DateTimeImmutable $at,
    ): self {
        $user                  = new self();
        $user->id              = $id;
        $user->email           = $email;
        $user->phone           = $phone;
        $user->phoneVerifiedAt = $at;
        $user->passwordHash    = self::UNUSABLE_PASSWORD_PREFIX . bin2hex(random_bytes(16));
        $user->createdAt       = $at;
        $user->status          = UserStatus::ACTIVE;
        $user->type            = UserType::PORTAL;

        $user->recordDomainEvent(new PortalUserRegistered(
            userId: $id->toString(),
            phone: $phone->toString(),
        ));

        return $user;
    }

    public static function reconstitute(
        UserId $id,
        ?string $email,
        string $passwordHash,
        \DateTimeImmutable $createdAt,
        UserStatus $status,
        ?\DateTimeImmutable $emailVerifiedAt = null,
        ?UserType $type = null,
        ?PhoneNumber $phone = null,
        ?\DateTimeImmutable $phoneVerifiedAt = null,
        ?\DateTimeImmutable $anonymisedAt = null,
    ): self {
        $user                  = new self();
        $user->id              = $id;
        $user->email           = $email;
        $user->passwordHash    = $passwordHash;
        $user->createdAt       = $createdAt;
        $user->status          = $status;
        $user->emailVerifiedAt = $emailVerifiedAt;
        $user->type            = $type ?? UserType::CLINIC;
        $user->phone           = $phone;
        $user->phoneVerifiedAt = $phoneVerifiedAt;
        $user->anonymisedAt    = $anonymisedAt;

        return $user;
    }

    public function id(): UserId
    {
        return $this->id;
    }

    /** Required for staff accounts, optional for owner (PORTAL) accounts. */
    public function email(): ?string
    {
        return $this->email;
    }

    /** The e-mail of an account that must have one (staff paths). */
    public function requireEmail(): string
    {
        return $this->email ?? throw new EmailRequiredException();
    }

    public function phone(): ?PhoneNumber
    {
        return $this->phone;
    }

    public function phoneVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->phoneVerifiedAt;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function status(): UserStatus
    {
        return $this->status;
    }

    public function emailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function type(): UserType
    {
        return $this->type;
    }

    /** When the owner account was erased after its deletion request; null otherwise. */
    public function anonymisedAt(): ?\DateTimeImmutable
    {
        return $this->anonymisedAt;
    }

    /**
     * The invited user chose their password: the account becomes ACTIVE and
     * the address is proven (the link reached its mailbox).
     */
    public function activate(string $passwordHash, \DateTimeImmutable $at): void
    {
        if (UserStatus::PENDING !== $this->status) {
            throw AccountStateException::notPending($this->status);
        }

        $this->passwordHash    = $passwordHash;
        $this->status          = UserStatus::ACTIVE;
        $this->emailVerifiedAt = $at;

        $this->recordDomainEvent(new UserActivated(
            userId: $this->id->toString(),
            occurredAt: $at->format(\DATE_ATOM),
        ));
    }

    public function changePassword(string $passwordHash, \DateTimeImmutable $at): void
    {
        if (UserStatus::ACTIVE !== $this->status) {
            throw AccountStateException::notActive($this->status);
        }

        $this->passwordHash = $passwordHash;

        $this->recordDomainEvent(new UserPasswordChanged(
            userId: $this->id->toString(),
            occurredAt: $at->format(\DATE_ATOM),
        ));
    }

    /**
     * An owner proved this address with a code sent to it: it becomes the
     * account's verified e-mail (and a second sign-in factor).
     */
    public function confirmEmail(string $email, \DateTimeImmutable $at): void
    {
        if (UserType::PORTAL !== $this->type) {
            throw new \LogicException('Only an owner account verifies its e-mail with a code.');
        }

        $this->email           = $email;
        $this->emailVerifiedAt = $at;
    }

    /**
     * Another owner proved the address this account held without having
     * verified it: the account loses it. A verified address is never taken.
     */
    public function releaseUnverifiedEmail(): void
    {
        if (null !== $this->emailVerifiedAt) {
            throw new \LogicException('A verified e-mail address is never released to another account.');
        }

        $this->email = null;
    }

    /**
     * The owner asked for the deletion of their account: it is switched off
     * at once (no access token, refresh or sign-in is accepted any more), its
     * data erased later by anonymisePortal(). A disabled account stays so.
     */
    public function disablePortal(): void
    {
        self::assertPortal($this->type);

        $this->status = UserStatus::DISABLED;
    }

    /**
     * Erases what identifies the owner of a disabled account: phone, e-mail
     * and their verification dates. The row stays (the id is referenced by
     * other contexts); the phone is free for a new account. Done once.
     */
    public function anonymisePortal(\DateTimeImmutable $at): void
    {
        self::assertPortal($this->type);

        if (UserStatus::DISABLED !== $this->status) {
            throw new \LogicException('An owner account is disabled before it is anonymised.');
        }

        if (null !== $this->anonymisedAt) {
            return;
        }

        $this->phone           = null;
        $this->phoneVerifiedAt = null;
        $this->email           = null;
        $this->emailVerifiedAt = null;
        $this->anonymisedAt    = $at;
    }

    /** The account has an e-mail address its owner proved. */
    public function hasVerifiedEmail(): bool
    {
        return null !== $this->email && null !== $this->emailVerifiedAt;
    }

    /**
     * A short digest of the password hash, kept in the session: when the
     * password changes, every session holding the old digest is closed.
     */
    public function passwordFingerprint(): string
    {
        return substr(hash('sha256', $this->passwordHash), 0, 16);
    }

    private static function assertPortal(UserType $type): void
    {
        if (UserType::PORTAL !== $type) {
            throw new \LogicException('Only an owner account is deleted from the Portal.');
        }
    }

    /** Staff accounts sign in by e-mail; owner accounts are created by registerPortal(). */
    private static function assertStaffEmail(UserType $type, string $email): void
    {
        if (UserType::PORTAL === $type) {
            throw new \InvalidArgumentException('An owner account is created by User::registerPortal(), with a phone.');
        }

        if ('' === trim($email)) {
            throw new EmailRequiredException();
        }
    }
}
