<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Inventory\Factory;

use App\Context\Inventory\Infrastructure\Persistence\Doctrine\Entity\LotEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<LotEntity>
 */
final class LotEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return LotEntity::class;
    }

    public function active(): self
    {
        return $this->with(['status' => 'ACTIVE']);
    }

    public function expired(): self
    {
        return $this->with([
            'status'     => 'EXPIRED',
            'expiryDate' => new \DateTimeImmutable('-1 month'),
        ]);
    }

    public function expiringIn(int $days): self
    {
        return $this->with([
            'expiryDate' => new \DateTimeImmutable("+{$days} days"),
        ]);
    }

    protected function defaults(): array
    {
        $now = new \DateTimeImmutable();

        return [
            'id'             => Uuid::v7(),
            'lotNumber'      => 'LOT-' . strtoupper(self::faker()->lexify('??????')),
            'quantityAmount' => '10.000000',
            'quantityUnit'   => 'UNIT',
            'expiryDate'     => new \DateTimeImmutable('+6 months'),
            'receivedAt'     => $now,
            'sourceSupplier' => null,
            'status'         => 'ACTIVE',
            'createdAt'      => $now,
            'updatedAt'      => $now,
        ];
    }
}
