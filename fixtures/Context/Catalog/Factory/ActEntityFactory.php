<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Catalog\Factory;

use App\Context\Catalog\Domain\Act\ValueObject\ActStatus;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ActEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ActEntity>
 */
final class ActEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ActEntity::class;
    }

    public function archived(): self
    {
        return $this->with(['status' => ActStatus::ARCHIVED]);
    }

    public function forClinic(string $clinicId): self
    {
        return $this->with(['tenantId' => Uuid::fromString($clinicId)]);
    }

    public function withCode(string $code): self
    {
        return $this->with(['code' => $code]);
    }

    protected function defaults(): array
    {
        $now = \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-6 months'));

        return [
            'id'          => Uuid::v7(),
            'tenantId'    => Uuid::v7(),
            'name'        => self::faker()->sentence(3),
            'code'        => strtoupper(self::faker()->unique()->lexify('??-???')),
            'description' => self::faker()->optional()->sentence(),
            'category'    => self::faker()->randomElement([
                'CONSULTATION', 'SURGERY', 'IMAGING', 'LABORATORY', 'HOSPITALIZATION', 'VACCINATION',
            ]),
            'taxCategoryCode'          => 'veterinary.act.consultation',
            'basePriceMinorUnits'      => self::faker()->numberBetween(1000, 50000),
            'basePriceCurrency'        => 'EUR',
            'estimatedDurationMinutes' => self::faker()->randomElement([15, 20, 30, 45, 60, 90]),
            'requiresAnesthesia'       => self::faker()->boolean(15),
            'status'                   => ActStatus::ACTIVE,
            'createdAt'                => $now,
            'updatedAt'                => $now,
        ];
    }
}
