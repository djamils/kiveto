<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Inventory\Factory;

use App\Context\Inventory\Infrastructure\Persistence\Doctrine\Entity\StockItemEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<StockItemEntity>
 */
final class StockItemEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return StockItemEntity::class;
    }

    public function withClinicId(string $clinicId): self
    {
        return $this->with(['clinicId' => Uuid::fromString($clinicId)]);
    }

    public function withArticleId(string $articleId): self
    {
        return $this->with(['articleId' => Uuid::fromString($articleId)]);
    }

    public function active(): self
    {
        return $this->with(['status' => 'ACTIVE']);
    }

    public function archived(): self
    {
        return $this->with(['status' => 'ARCHIVED']);
    }

    public function withTotalOnHand(string $amount, string $unit): self
    {
        return $this->with([
            'totalOnHandAmount' => $amount,
            'totalOnHandUnit'   => $unit,
        ]);
    }

    public function noTracking(): self
    {
        return $this->with(['trackStock' => false]);
    }

    protected function defaults(): array
    {
        $now = new \DateTimeImmutable();

        return [
            'id'                => Uuid::v7(),
            'clinicId'          => Uuid::v7(),
            'articleId'         => Uuid::v7(),
            'totalOnHandAmount' => '0.000000',
            'totalOnHandUnit'   => 'UNIT',
            'reservedAmount'    => '0.000000',
            'thresholdAmount'   => '5.000000',
            'thresholdUnit'     => 'UNIT',
            'thresholdType'     => 'ABSOLUTE',
            'trackStock'        => true,
            'status'            => 'ACTIVE',
            'createdAt'         => $now,
            'updatedAt'         => $now,
        ];
    }
}
