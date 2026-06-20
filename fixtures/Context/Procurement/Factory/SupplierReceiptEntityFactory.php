<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Procurement\Factory;

use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierReceiptEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<SupplierReceiptEntity>
 */
final class SupplierReceiptEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return SupplierReceiptEntity::class;
    }

    public function pendingReview(): self
    {
        return $this->with(['status' => 'PENDING_REVIEW']);
    }

    public function validated(): self
    {
        return $this->with([
            'status'      => 'VALIDATED',
            'validatedAt' => new \DateTimeImmutable(),
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
            'id'                    => Uuid::v7(),
            'clinicId'              => Uuid::v7(),
            'supplierId'            => Uuid::v7(),
            'purchaseOrderId'       => Uuid::v7(),
            'deliveryNoteReference' => 'DNR-' . self::faker()->numerify('########'),
            'matchType'             => 'AUTO_MATCHED',
            'status'                => 'PENDING_REVIEW',
            'validatedAt'           => null,
            'receivedBy'            => null,
            'comment'               => null,
            'version'               => 1,
            'createdAt'             => $now,
            'updatedAt'             => $now,
        ];
    }
}
