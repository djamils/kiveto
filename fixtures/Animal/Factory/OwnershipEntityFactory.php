<?php

declare(strict_types=1);

namespace App\Fixtures\Animal\Factory;

use App\Animal\Domain\ValueObject\OwnershipRole;
use App\Animal\Domain\ValueObject\OwnershipStatus;
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
            $animal = AnimalEntityFactory::repository()->find(['id' => Uuid::fromString($animalId)]);
            if (null === $animal) {
                throw new \RuntimeException(\sprintf('Animal with ID "%s" not found', $animalId));
            }
            $ownership->setAnimal($animal->_real());
        });
    }

    public function withClientId(string $clientId): self
    {
        return $this->afterInstantiate(function (OwnershipEntity $ownership) use ($clientId): void {
            $ownership->setClientId(Uuid::fromString($clientId));
        });
    }

    public function primary(): self
    {
        return $this->afterInstantiate(function (OwnershipEntity $ownership): void {
            $ownership->setRole(OwnershipRole::PRIMARY);
        });
    }

    public function secondary(): self
    {
        return $this->afterInstantiate(function (OwnershipEntity $ownership): void {
            $ownership->setRole(OwnershipRole::SECONDARY);
        });
    }

    public function active(): self
    {
        return $this->afterInstantiate(function (OwnershipEntity $ownership): void {
            $ownership->setStatus(OwnershipStatus::ACTIVE);
            $ownership->setEndedAt(null);
        });
    }

    public function ended(): self
    {
        $endedAt = \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-6 months'));

        return $this->afterInstantiate(function (OwnershipEntity $ownership) use ($endedAt): void {
            $ownership->setStatus(OwnershipStatus::ENDED);
            $ownership->setEndedAt($endedAt);
        });
    }

    protected function defaults(): array|callable
    {
        /** @var OwnershipRole $role */
        $role = self::faker()->randomElement([OwnershipRole::PRIMARY, OwnershipRole::SECONDARY]);

        return [
            'id'        => Uuid::v7(),
            'animal'    => AnimalEntityFactory::new(),
            'clientId'  => Uuid::v7(),
            'role'      => $role,
            'status'    => OwnershipStatus::ACTIVE,
            'startedAt' => \DateTimeImmutable::createFromMutable(
                self::faker()->dateTimeBetween('-2 years', '-1 month')
            ),
            'endedAt' => null,
        ];
    }
}
