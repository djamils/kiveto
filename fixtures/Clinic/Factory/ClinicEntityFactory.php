<?php

declare(strict_types=1);

namespace App\Fixtures\Clinic\Factory;

use App\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicEntity;
use App\Clinic\Domain\ValueObject\ClinicStatus;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ClinicEntity>
 */
final class ClinicEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ClinicEntity::class;
    }

    protected function defaults(): array
    {
        $now = \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-1 year'));

        return [
            'id' => Uuid::v7(),
            'slug' => self::faker()->unique()->slug(2),
            'name' => self::faker()->company() . ' Clinic',
            'status' => ClinicStatus::ACTIVE,
            'timeZone' => self::faker()->randomElement(['Europe/Paris', 'Europe/London', 'America/New_York', 'UTC']),
            'locale' => self::faker()->randomElement(['fr', 'en', 'fr_FR', 'en_US']),
            'clinicGroupId' => null,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
    }

    public function withId(string $id): self
    {
        return $this->with(['id' => Uuid::fromString($id)]);
    }

    public function withSlug(string $slug): self
    {
        return $this->with(['slug' => $slug]);
    }

    public function withName(string $name): self
    {
        return $this->with(['name' => $name]);
    }

    public function withGroupId(string $groupId): self
    {
        return $this->with(['clinicGroupId' => Uuid::fromString($groupId)]);
    }

    public function suspended(): self
    {
        return $this->with(['status' => ClinicStatus::SUSPENDED]);
    }

    public function closed(): self
    {
        return $this->with(['status' => ClinicStatus::CLOSED]);
    }
}
