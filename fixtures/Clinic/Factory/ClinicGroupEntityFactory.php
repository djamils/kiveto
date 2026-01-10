<?php

declare(strict_types=1);

namespace App\Fixtures\Clinic\Factory;

use App\Clinic\Domain\ValueObject\ClinicGroupStatus;
use App\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicGroupEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ClinicGroupEntity>
 */
final class ClinicGroupEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ClinicGroupEntity::class;
    }

    public function withId(string $id): self
    {
        return $this->with(['id' => Uuid::fromString($id)]);
    }

    public function withName(string $name): self
    {
        return $this->with(['name' => $name]);
    }

    public function suspended(): self
    {
        return $this->with(['status' => ClinicGroupStatus::SUSPENDED]);
    }

    protected function defaults(): array
    {
        return [
            'id'        => Uuid::v7(),
            'name'      => self::faker()->company(),
            'status'    => ClinicGroupStatus::ACTIVE,
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-2 years')),
        ];
    }
}
