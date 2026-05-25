<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Inventory\Story;

use App\Fixtures\Context\Inventory\Factory\LotEntityFactory;
use App\Fixtures\Context\Inventory\Factory\StockItemEntityFactory;
use Zenstruck\Foundry\Story;

/**
 * One clinic with 5 stock items, each with 1-3 ACTIVE lots, all totalOnHand above threshold.
 */
final class HealthyClinicInventoryStory extends Story
{
    public const string CLINIC_ID = '01970000-0000-7000-8000-000000000002';

    public function build(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $stockItem = StockItemEntityFactory::new()
                ->withClinicId(self::CLINIC_ID)
                ->withTotalOnHand('25.000000', 'UNIT')
                ->create()
            ;

            $lotCount = random_int(1, 3);

            for ($j = 0; $j < $lotCount; ++$j) {
                LotEntityFactory::new()
                    ->expiringIn(random_int(60, 180))
                    ->create(['stockItem' => $stockItem->_real()])
                ;
            }
        }
    }
}
