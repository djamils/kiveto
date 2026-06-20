<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Procurement\Factory;

use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\PurchaseOrderEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\PurchaseOrderLineEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<PurchaseOrderLineEntity>
 */
final class PurchaseOrderLineEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return PurchaseOrderLineEntity::class;
    }

    public function active(): self
    {
        return $this->with(['status' => 'ACTIVE']);
    }

    public function cancelled(): self
    {
        return $this->with(['status' => 'CANCELLED']);
    }

    public function forOrder(PurchaseOrderEntity $purchaseOrder): self
    {
        return $this->with(['purchaseOrder' => $purchaseOrder]);
    }

    protected function defaults(): array
    {
        return [
            'id'                => Uuid::v7(),
            'articleId'         => Uuid::v7(),
            'catalogEntryId'    => Uuid::v7(),
            'orderedAmount'     => '1',
            'orderedUnit'       => 'UNITE',
            'unitPriceMinor'    => self::faker()->numberBetween(100, 5000),
            'unitPriceCurrency' => 'EUR',
            'receivedAmount'    => '0',
            'status'            => 'ACTIVE',
            'note'              => null,
        ];
    }
}
