<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Procurement\Story;

use App\Fixtures\Context\Procurement\Factory\PurchaseOrderEntityFactory;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Seeds purchase orders in every relevant status to cover all UI states:
 * DRAFT, SUBMITTED, CONFIRMED, PARTIALLY_RECEIVED.
 */
final class ActivePurchaseOrdersStory extends Story
{
    /** Demo clinic UUID — same as ClinicWithSupplierAccountsStory for consistency. */
    public const string CLINIC_ID = '01970000-0000-7000-8000-000000000010';

    public function build(): void
    {
        $clinicId = Uuid::fromString(self::CLINIC_ID);

        // Draft — just created, no submission yet
        PurchaseOrderEntityFactory::createOne([
            'clinicId' => $clinicId,
            'status'   => 'DRAFT',
        ]);

        // Submitted — sent to supplier, awaiting confirmation
        PurchaseOrderEntityFactory::createOne([
            'clinicId'                    => $clinicId,
            'status'                      => 'SUBMITTED',
            'submittedAt'                 => new \DateTimeImmutable('-1 day'),
            'externalReferenceValue'      => 'EXT-001',
            'externalReferenceProvidedBy' => 'simulated',
        ]);

        // Confirmed — supplier acknowledged the order
        PurchaseOrderEntityFactory::createOne([
            'clinicId'    => $clinicId,
            'status'      => 'CONFIRMED',
            'submittedAt' => new \DateTimeImmutable('-3 days'),
            'confirmedAt' => new \DateTimeImmutable('-2 days'),
        ]);

        // Partially received — delivery in progress
        PurchaseOrderEntityFactory::createOne([
            'clinicId'    => $clinicId,
            'status'      => 'PARTIALLY_RECEIVED',
            'submittedAt' => new \DateTimeImmutable('-7 days'),
            'confirmedAt' => new \DateTimeImmutable('-6 days'),
        ]);
    }
}
