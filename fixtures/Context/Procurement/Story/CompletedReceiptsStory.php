<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Procurement\Story;

use App\Fixtures\Context\Procurement\Factory\PurchaseOrderEntityFactory;
use App\Fixtures\Context\Procurement\Factory\SupplierReceiptEntityFactory;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Seeds a fully received purchase order with its validated supplier receipt.
 * Covers the RECEIVED PO status and VALIDATED receipt status in the UI.
 */
final class CompletedReceiptsStory extends Story
{
    /** Demo clinic UUID — same as ClinicWithSupplierAccountsStory for consistency. */
    public const string CLINIC_ID = '01970000-0000-7000-8000-000000000010';

    public function build(): void
    {
        $clinicId   = Uuid::fromString(self::CLINIC_ID);
        $supplierId = Uuid::v7();

        // Fully received purchase order
        $po = PurchaseOrderEntityFactory::createOne([
            'clinicId'    => $clinicId,
            'supplierId'  => $supplierId,
            'status'      => 'RECEIVED',
            'submittedAt' => new \DateTimeImmutable('-10 days'),
            'confirmedAt' => new \DateTimeImmutable('-9 days'),
        ]);

        // Validated receipt linked to the PO above
        SupplierReceiptEntityFactory::createOne([
            'clinicId'        => $clinicId,
            'supplierId'      => $supplierId,
            'purchaseOrderId' => $po->getId(),
            'matchType'       => 'AUTO_MATCHED',
            'status'          => 'VALIDATED',
            'validatedAt'     => new \DateTimeImmutable('-8 days'),
        ]);
    }
}
