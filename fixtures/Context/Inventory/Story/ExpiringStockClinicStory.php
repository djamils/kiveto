<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Inventory\Story;

use App\Fixtures\Context\Inventory\Factory\LotEntityFactory;
use App\Fixtures\Context\Inventory\Factory\StockItemEntityFactory;
use Zenstruck\Foundry\Story;

/**
 * One clinic with 3 stock items: lots expiring in <7d (CRITICAL), <14d (WARNING), <30d (INFO).
 */
final class ExpiringStockClinicStory extends Story
{
    public const string CLINIC_ID = '01970000-0000-7000-8000-000000000004';

    public function build(): void
    {
        // CRITICAL: expires in 5 days
        $critical = StockItemEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withTotalOnHand('10.000000', 'UNIT')
            ->create()
        ;

        LotEntityFactory::new()
            ->expiringIn(5)
            ->create([
                'stockItem'      => $critical->_real(),
                'quantityAmount' => '10.000000',
            ])
        ;

        // WARNING: expires in 12 days
        $warning = StockItemEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withTotalOnHand('8.000000', 'TABLET')
            ->create(['totalOnHandUnit' => 'TABLET', 'thresholdUnit' => 'TABLET'])
        ;

        LotEntityFactory::new()
            ->expiringIn(12)
            ->create([
                'stockItem'      => $warning->_real(),
                'quantityAmount' => '8.000000',
                'quantityUnit'   => 'TABLET',
            ])
        ;

        // INFO: expires in 25 days
        $info = StockItemEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withTotalOnHand('15.000000', 'ML')
            ->create(['totalOnHandUnit' => 'ML', 'thresholdUnit' => 'ML'])
        ;

        LotEntityFactory::new()
            ->expiringIn(25)
            ->create([
                'stockItem'      => $info->_real(),
                'quantityAmount' => '15.000000',
                'quantityUnit'   => 'ML',
            ])
        ;
    }
}
