<?php

declare(strict_types=1);

namespace App\Fixtures\Animal\Factory;

use App\Animal\Domain\Enum\OwnershipRole;
use App\Animal\Domain\Enum\OwnershipStatus;
use App\Animal\Infrastructure\Persistence\Doctrine\Entity\AnimalEntity;
use App\Animal\Infrastructure\Persistence\Doctrine\Entity\OwnershipEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<OwnershipEntity>
 */
final class OwnershipEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return OwnershipEntity::class;
    }

    public function withAnimalId(string $animalId): self
    {
        return $this->afterInstantiate(function (OwnershipEntity $ownership) use ($animalId): void {
            // Find the AnimalEntity by ID and set it
            $animal = AnimalEntityFactory::repository()->find(['id' => $animalId]);
            if (null === $animal) {
                throw new \RuntimeException(\sprintf('Animal with ID "%s" not found', $animalId));
            }
            $ownership->animal = $animal->_real();
        });
    }

    public function withClientId(string $clientId): self
    {
        return $this->with(['clientId' => $clientId]);
    }

    public function primary(): self
    {
        return $this->with(['role' => OwnershipRole::PRIMARY->value]);
    }

    public function secondary(): self
    {
        return $this->with(['role' => OwnershipRole::SECONDARY->value]);
    }

    public function active(): self
    {
        return $this->with([
            'status'  => OwnershipStatus::ACTIVE->value,
            'endedAt' => null,
        ]);
    }

    public function ended(): self
    {
        return $this->with([
            'status'  => OwnershipStatus::ENDED->value,
            'endedAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-6 months')),
        ]);
    }

    protected function defaults(): array|callable
    {
        /** @var OwnershipRole $role */
        $role = self::faker()->randomElement([OwnershipRole::PRIMARY, OwnershipRole::SECONDARY]);

        return [
            'animal'    => AnimalEntityFactory::new(),
            'clientId'  => Uuid::v7()->toString(),
            'role'      => $role->value,
            'status'    => OwnershipStatus::ACTIVE->value,
            'startedAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-2 years', '-1 month')),
            'endedAt'   => null,
        ];
    }
}
