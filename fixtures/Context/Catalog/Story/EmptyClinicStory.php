<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Catalog\Story;

use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListStatus;
use App\Fixtures\Context\Catalog\Factory\PriceListEntityFactory;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Story for an empty clinic catalog — no acts, no articles, no packages.
 * Only a default price list is created to cover the UI empty-state.
 */
final class EmptyClinicStory extends Story
{
    public const string CLINIC_ID = '01950000-0000-7000-0000-000000000002';

    public function build(): void
    {
        $clinicUuid = Uuid::fromString(self::CLINIC_ID);

        // Only a default price list — covers the "empty catalog" UI state
        PriceListEntityFactory::createOne([
            'tenantId'  => $clinicUuid,
            'name'      => 'Tarifs standard',
            'isDefault' => true,
            'status'    => PriceListStatus::ACTIVE,
        ]);
    }
}
