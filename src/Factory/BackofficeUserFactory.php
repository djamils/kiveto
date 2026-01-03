<?php

namespace App\Factory;

use App\IdentityAccess\Domain\ValueObject\UserStatus;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\BackofficeUser;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<BackofficeUser>
 */
final class BackofficeUserFactory extends PersistentObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
    }

    #[\Override]
    public static function class(): string
    {
        return BackofficeUser::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'id' => Uuid::v7()->toRfc4122(),
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'email' => self::faker()->unique()->safeEmail(),
            'passwordHash' => '$2y$13$dSCsQpIYdXhxsQy7/A3JNuktm.08Dj.aHTdyZupOchXMx5a32W/4.',
            'status' => UserStatus::ACTIVE,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(BackofficeUser $backofficeUser): void {})
        ;
    }
}
