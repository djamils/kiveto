<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Animal\Factory;

use App\Context\Animal\Infrastructure\Persistence\Doctrine\Entity\WeightRecordEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<WeightRecordEntity>
 */
final class WeightRecordEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return WeightRecordEntity::class;
    }

    public function forAnimal(string $clinicId, string $animalId): self
    {
        return $this->with([
            'clinicId' => Uuid::fromString($clinicId),
            'animalId' => Uuid::fromString($animalId),
        ]);
    }

    public function weighing(int $grams, \DateTimeImmutable $measuredOn, ?int $bodyConditionScore = null): self
    {
        return $this->with([
            'weightGrams'        => $grams,
            'bodyConditionScore' => $bodyConditionScore,
            'measuredOn'         => $measuredOn->setTime(0, 0),
            'recordedAt'         => $measuredOn,
        ]);
    }

    protected function defaults(): array|callable
    {
        return [
            'id'                 => Uuid::v7(),
            'clinicId'           => Uuid::v7(),
            'animalId'           => Uuid::v7(),
            'weightGrams'        => self::faker()->numberBetween(2_000, 40_000),
            'bodyConditionScore' => null,
            'measuredOn'         => new \DateTimeImmutable('today'),
            'authorName'         => 'Camille Leroy',
            'recordedAt'         => new \DateTimeImmutable(),
        ];
    }
}
