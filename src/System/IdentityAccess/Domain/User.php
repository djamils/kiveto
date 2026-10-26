<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\System\IdentityAccess\Domain\Event\UserActivated;
use App\System\IdentityAccess\Domain\Event\UserInvited;
use App\System\IdentityAccess\Domain\Event\UserPasswordChanged;
use App\System\IdentityAccess\Domain\Event\UserRegistered;
use App\System\IdentityAccess\Domain\Exception\AccountStateException;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;

final class User extends AggregateRoot
{
    /** No password hasher produces a hash starting with « ! ». */
    private const string UNUSABLE_PASSWORD_PREFIX = '!';

    private UserId $id;
    private string $email;
    private string $passwordHash;
    private \DateTimeImmutable $createdAt;
    private UserStatus $status;
    private ?\DateTimeImmutable $emailVerifiedAt = null;
    private UserType $type;

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

    public static function reconstitute(
        UserId $id,
        string $email,
        string $passwordHash,
        \DateTimeImmutable $createdAt,
        UserStatus $status,
        ?\DateTimeImmutable $emailVerifiedAt = null,
        ?UserType $type = null,
    ): self {
        $user                  = new self();
        $user->id              = $id;
        $user->email           = $email;
        $user->passwordHash    = $passwordHash;
        $user->createdAt       = $createdAt;
        $user->status          = $status;
        $user->emailVerifiedAt = $emailVerifiedAt;
        $user->type            = $type ?? UserType::CLINIC;

        return $user;
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
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
     * A short digest of the password hash, kept in the session: when the
     * password changes, every session holding the old digest is closed.
     */
    public function passwordFingerprint(): string
    {
        return substr(hash('sha256', $this->passwordHash), 0, 16);
    }
}
