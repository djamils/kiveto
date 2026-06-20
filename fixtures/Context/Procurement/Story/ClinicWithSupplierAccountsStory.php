<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Procurement\Story;

use App\Fixtures\Context\Procurement\Factory\SupplierAccountEntityFactory;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Seeds one clinic with three active supplier accounts linked to distinct suppliers.
 */
final class ClinicWithSupplierAccountsStory extends Story
{
    /** Demo clinic UUID — shared across procurement demo fixtures. */
    public const string CLINIC_ID = '01970000-0000-7000-8000-000000000010';

    public function build(): void
    {
        $clinicId = Uuid::fromString(self::CLINIC_ID);

        // Three supplier accounts for the same clinic, each with a distinct customer code prefix
        SupplierAccountEntityFactory::createOne([
            'clinicId'     => $clinicId,
            'supplierId'   => Uuid::v7(),
            'customerCode' => 'CVT-' . str_pad((string) random_int(1, 99999999), 8, '0', \STR_PAD_LEFT),
            'status'       => 'ACTIVE',
        ]);

        SupplierAccountEntityFactory::createOne([
            'clinicId'     => $clinicId,
            'supplierId'   => Uuid::v7(),
            'customerCode' => 'ALC-' . str_pad((string) random_int(1, 99999999), 8, '0', \STR_PAD_LEFT),
            'status'       => 'ACTIVE',
        ]);

        SupplierAccountEntityFactory::createOne([
            'clinicId'     => $clinicId,
            'supplierId'   => Uuid::v7(),
            'customerCode' => 'HIP-' . str_pad((string) random_int(1, 99999999), 8, '0', \STR_PAD_LEFT),
            'status'       => 'ACTIVE',
        ]);
    }
}
