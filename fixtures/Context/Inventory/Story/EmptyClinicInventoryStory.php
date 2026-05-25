<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Inventory\Story;

use App\Fixtures\Context\Inventory\Factory\StockItemEntityFactory;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * One clinic with 3 stock items all at zero stock (no lots).
 */
final class EmptyClinicInventoryStory extends Story
{
    public const string CLINIC_ID    = '01970000-0000-7000-8000-000000000001';
    public const string ARTICLE_1    = '01970000-0000-7000-8000-000000000011';
    public const string ARTICLE_2    = '01970000-0000-7000-8000-000000000012';
    public const string ARTICLE_3    = '01970000-0000-7000-8000-000000000013';
    public const string STOCK_ITEM_1 = '01970001-0000-7000-8000-000000000001';
    public const string STOCK_ITEM_2 = '01970001-0000-7000-8000-000000000002';
    public const string STOCK_ITEM_3 = '01970001-0000-7000-8000-000000000003';

    public function build(): void
    {
        StockItemEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withArticleId(self::ARTICLE_1)
            ->withTotalOnHand('0.000000', 'UNIT')
            ->create(['id' => Uuid::fromString(self::STOCK_ITEM_1)])
        ;

        StockItemEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withArticleId(self::ARTICLE_2)
            ->withTotalOnHand('0.000000', 'TABLET')
            ->create(['id' => Uuid::fromString(self::STOCK_ITEM_2), 'totalOnHandUnit' => 'TABLET', 'thresholdUnit' => 'TABLET'])
        ;

        StockItemEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withArticleId(self::ARTICLE_3)
            ->withTotalOnHand('0.000000', 'ML')
            ->create(['id' => Uuid::fromString(self::STOCK_ITEM_3), 'totalOnHandUnit' => 'ML', 'thresholdUnit' => 'ML'])
        ;
    }
}
