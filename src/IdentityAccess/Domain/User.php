<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain;

use App\IdentityAccess\Domain\Event\UserRegistered;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class User extends AggregateRoot
{
    private UserId $id;
    private string $email;
    private string $passwordHash;
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function register(
        UserId $id,
        string $email,
        string $passwordHash,
        \DateTimeImmutable $createdAt,
    ): self {
        $user               = new self();
        $user->id           = $id;
        $user->email        = $email;
        $user->passwordHash = $passwordHash;
        $user->createdAt    = $createdAt;

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
    ): self {
        $user               = new self();
        $user->id           = $id;
        $user->email        = $email;
        $user->passwordHash = $passwordHash;
        $user->createdAt    = $createdAt;

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
}
