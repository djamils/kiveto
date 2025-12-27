<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain;

use App\IdentityAccess\Domain\Event\UserRegistered;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\UserStatus;
use App\IdentityAccess\Domain\ValueObject\UserType;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class User extends AggregateRoot
{
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

    public static function reconstitute(
        UserId $id,
        string $email,
        string $passwordHash,
        \DateTimeImmutable $createdAt,
        UserStatus $status,
        ?\DateTimeImmutable $emailVerifiedAt = null,
        ?UserType $type = null,
    ): self {
        $user               = new self();
        $user->id           = $id;
        $user->email        = $email;
        $user->passwordHash = $passwordHash;
        $user->createdAt    = $createdAt;
        $user->status       = $status;
        $user->emailVerifiedAt = $emailVerifiedAt;
        $user->type         = $type ?? UserType::CLINIC;

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
}
