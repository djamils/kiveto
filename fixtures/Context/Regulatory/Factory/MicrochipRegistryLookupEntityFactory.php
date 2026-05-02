<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Regulatory\Factory;

use App\Context\Regulatory\Domain\ValueObject\MicrochipRegistryLookupStatus;
use App\Context\Regulatory\Infrastructure\Persistence\Doctrine\Entity\MicrochipRegistryLookupEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<MicrochipRegistryLookupEntity>
 */
final class MicrochipRegistryLookupEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return MicrochipRegistryLookupEntity::class;
    }

    public function withId(string $id): self
    {
        return $this->afterInstantiate(function (MicrochipRegistryLookupEntity $entity) use ($id): void {
            $entity->setId(Uuid::fromString($id));
        });
    }

    public function withClinicId(string $clinicId): self
    {
        return $this->afterInstantiate(function (MicrochipRegistryLookupEntity $entity) use ($clinicId): void {
            $entity->setClinicId(Uuid::fromString($clinicId));
        });
    }

    public function withStatus(MicrochipRegistryLookupStatus $status): self
    {
        return $this->afterInstantiate(function (MicrochipRegistryLookupEntity $entity) use ($status): void {
            $entity->setStatus($status);
        });
    }

    protected function defaults(): array|callable
    {
        return [
            'id'             => Uuid::v7(),
            'chipNumber'     => self::faker()->numerify('###############'),
            'clinicId'       => Uuid::v7(),
            'status'         => MicrochipRegistryLookupStatus::Pending,
            'registryAnimalData' => null,
            'errorMessage'   => null,
            'version'        => 1,
            'initiatedAt'    => \DateTimeImmutable::createFromMutable(
                self::faker()->dateTimeBetween('-3 months')
            ),
            'createdAt' => \DateTimeImmutable::createFromMutable(
                self::faker()->dateTimeBetween('-3 months')
            ),
            'updatedAt' => \DateTimeImmutable::createFromMutable(
                self::faker()->dateTimeBetween('-1 month')
            ),
        ];
    }
}
