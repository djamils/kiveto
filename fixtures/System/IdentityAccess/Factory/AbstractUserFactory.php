<?php

declare(strict_types=1);

namespace App\Fixtures\System\IdentityAccess\Factory;

use App\Shared\Infrastructure\Deployment\Deployment;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @template T of UserEntity
 *
 * @extends PersistentObjectFactory<T>
 */
abstract class AbstractUserFactory extends PersistentObjectFactory
{
    /**
     * STAGING and DEMO are public: their seeded accounts (docker/production/
     * sandbox/kiveto-seed) never get the trivial passwords of the stories.
     */
    public const int SEED_PASSWORD_MIN_LENGTH = 16;

    public function __construct(
        protected readonly UserPasswordHasherInterface $hasher,
        #[Autowire(env: 'enum:App\Shared\Infrastructure\Deployment\Deployment:KIVETO_DEPLOYMENT')]
        private readonly Deployment $deployment,
        #[Autowire(env: 'default::KIVETO_SEED_PASSWORD')]
        private readonly ?string $seedPassword,
        #[Autowire(env: 'default::KIVETO_SEED_BACKOFFICE_PASSWORD')]
        private readonly ?string $seedBackofficePassword,
    ) {
        parent::__construct();
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

    /**
     * The given password on workstations and in the test suite; on any other
     * deployment, the seed password of the deployment instead.
     */
    public function withPlainPassword(string $plain): static
    {
        $password = Deployment::DEV === $this->deployment ? $plain : $this->deploymentSeedPassword();

        return $this->afterInstantiate(function (UserEntity $user) use ($password): void {
            $user->setPasswordHash($this->hasher->hashPassword($user, $password));
        });
    }

    /**
     * Back-office accounts have their own seed password: the clinic one is
     * handed to the people a DEMO is shown to.
     */
    protected function usesBackofficeSeedPassword(): bool
    {
        return false;
    }

    protected function defaults(): array|callable
    {
        return [
            'id'           => Uuid::v7(),
            'createdAt'    => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'email'        => self::faker()->unique()->safeEmail(),
            'passwordHash' => self::faker()->sha256(),
            'status'       => UserStatus::ACTIVE,
        ];
    }

    private function deploymentSeedPassword(): string
    {
        [$variable, $password] = $this->usesBackofficeSeedPassword()
            ? ['KIVETO_SEED_BACKOFFICE_PASSWORD', $this->seedBackofficePassword]
            : ['KIVETO_SEED_PASSWORD', $this->seedPassword];

        if (null === $password || mb_strlen($password) < self::SEED_PASSWORD_MIN_LENGTH) {
            throw new \LogicException(\sprintf(
                '%s must hold at least %d characters to seed accounts on the %s deployment.',
                $variable,
                self::SEED_PASSWORD_MIN_LENGTH,
                $this->deployment->value,
            ));
        }

        return $password;
    }
}
