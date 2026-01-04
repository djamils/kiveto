<?php

declare(strict_types=1);

namespace App\Fixtures\IdentityAccess\Factory;

use App\IdentityAccess\Domain\ValueObject\UserStatus;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @template T of User
 * @extends PersistentObjectFactory<T>
 */
abstract class AbstractUserFactory extends PersistentObjectFactory
{
    public function __construct(protected readonly UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
    }

    protected function defaults(): array|callable
    {
        return [
            'id'           => Uuid::v7()->toRfc4122(),
            'createdAt'    => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'email'        => self::faker()->unique()->safeEmail(),
            'passwordHash' => self::faker()->sha256(),
            'status'       => UserStatus::ACTIVE,
        ];
    }

    public function active(): static
    {
        return $this->with(['status' => UserStatus::ACTIVE]);
    }

    public function disabled(): static
    {
        return $this->with(['status' => UserStatus::DISABLED]);
    }

    public function withEmail(string $email): static
    {
        return $this->with(['email' => $email]);
    }

    public function withPlainPassword(string $plain): static
    {
        return $this->afterInstantiate(function(User $user) use ($plain): void {
            $user->setPasswordHash($this->hasher->hashPassword($user, $plain));
        });
    }
}
