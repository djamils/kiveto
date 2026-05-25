<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Inventory\Story;

use App\Fixtures\Context\Inventory\Factory\LotEntityFactory;
use App\Fixtures\Context\Inventory\Factory\StockItemEntityFactory;
use Zenstruck\Foundry\Story;

/**
 * One clinic with 3 stock items below threshold; one item is completely out of stock (no lots).
 */
final class LowStockClinicStory extends Story
{
    public const string CLINIC_ID = '01970000-0000-7000-8000-000000000003';

    public function build(): void
    {
        // One completely out of stock (zero totalOnHand, no lots)
        StockItemEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withTotalOnHand('0.000000', 'UNIT')
            ->create(['thresholdAmount' => '5.000000'])
        ;

        // Two below threshold with a small lot each
        for ($i = 0; $i < 2; ++$i) {
            $stockItem = StockItemEntityFactory::new()
                ->withClinicId(self::CLINIC_ID)
                ->withTotalOnHand('2.000000', 'UNIT')
                ->create(['thresholdAmount' => '5.000000'])
            ;

            LotEntityFactory::new()
                ->expiringIn(90)
                ->create([
                    'stockItem'      => $stockItem->_real(),
                    'quantityAmount' => '2.000000',
                ])
            ;
        }
    }
}
