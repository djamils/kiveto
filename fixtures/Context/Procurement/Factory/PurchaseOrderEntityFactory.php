<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Procurement\Factory;

use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\PurchaseOrderEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<PurchaseOrderEntity>
 */
final class PurchaseOrderEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return PurchaseOrderEntity::class;
    }

    public function draft(): self
    {
        return $this->with(['status' => 'DRAFT']);
    }

    public function submitted(): self
    {
        return $this->with([
            'status'      => 'SUBMITTED',
            'submittedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function confirmed(): self
    {
        return $this->with([
            'status'      => 'CONFIRMED',
            'submittedAt' => new \DateTimeImmutable('-1 day'),
            'confirmedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function forClinic(Uuid $clinicId): self
    {
        return $this->with(['clinicId' => $clinicId]);
    }

    protected function defaults(): array
    {
        $now = new \DateTimeImmutable();

        return [
            'id'                          => Uuid::v7(),
            'clinicId'                    => Uuid::v7(),
            'supplierId'                  => Uuid::v7(),
            'supplierAccountId'           => Uuid::v7(),
            'orderNumber'                 => 'PO-' . date('Y') . '-' . str_pad((string) self::faker()->numberBetween(1, 999999), 6, '0', \STR_PAD_LEFT),
            'currency'                    => 'EUR',
            'status'                      => 'DRAFT',
            'externalReferenceValue'      => null,
            'externalReferenceProvidedBy' => null,
            'deliveryAddressJson'         => null,
            'pdfFileId'                   => null,
            'submittedAt'                 => null,
            'confirmedAt'                 => null,
            'version'                     => 1,
            'createdAt'                   => $now,
            'updatedAt'                   => $now,
        ];
    }
}
