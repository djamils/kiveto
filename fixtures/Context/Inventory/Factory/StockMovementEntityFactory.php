<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Inventory\Factory;

use App\Context\Inventory\Infrastructure\Persistence\Doctrine\Entity\StockMovementEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<StockMovementEntity>
 */
final class StockMovementEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return StockMovementEntity::class;
    }

    protected function defaults(): array
    {
        $now = new \DateTimeImmutable();

        return [
            'id'             => Uuid::v7(),
            'clinicId'       => Uuid::v7(),
            'articleId'      => Uuid::v7(),
            'lotId'          => null,
            'type'           => 'IN',
            'reason'         => 'RECEIPT_OPENING',
            'quantityAmount' => '5.000000',
            'quantityUnit'   => 'UNIT',
            'occurredAt'     => $now,
            'reference'      => null,
            'performedBy'    => null,
            'note'           => null,
            'createdAt'      => $now,
        ];
    }
}
