<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Procurement\Factory;

use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierReceiptEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierReceiptLineEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<SupplierReceiptLineEntity>
 */
final class SupplierReceiptLineEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return SupplierReceiptLineEntity::class;
    }

    public function forReceipt(SupplierReceiptEntity $supplierReceipt): self
    {
        return $this->with(['supplierReceipt' => $supplierReceipt]);
    }

    public function withLot(string $lotNumber, \DateTimeImmutable $expiryDate): self
    {
        return $this->with([
            'lotNumber'     => $lotNumber,
            'lotExpiryDate' => $expiryDate,
        ]);
    }

    protected function defaults(): array
    {
        return [
            'id'                      => Uuid::v7(),
            'purchaseOrderLineId'     => Uuid::v7(),
            'articleId'               => Uuid::v7(),
            'receivedAmount'          => '1',
            'receivedUnit'            => 'UNITE',
            'lotNumber'               => null,
            'lotExpiryDate'           => null,
            'lotManufacturedAt'       => null,
            'actualUnitPriceMinor'    => null,
            'actualUnitPriceCurrency' => null,
            'note'                    => null,
        ];
    }
}
