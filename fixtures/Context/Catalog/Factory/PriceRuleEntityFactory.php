<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Catalog\Factory;

use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PriceRuleEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<PriceRuleEntity>
 */
final class PriceRuleEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return PriceRuleEntity::class;
    }

    protected function defaults(): array
    {
        return [
            'id'            => Uuid::v7(),
            'priceListId'   => Uuid::v7(),
            'ruleType'      => 'URGENCY_COEFFICIENT',
            'conditionJson' => json_encode([
                'appliesToAllItems' => true,
                'itemRefs'          => [],
                'isUrgency'         => true,
                'animalSize'        => null,
                'speciesGroup'      => null,
                'discountCode'      => null,
            ]),
            'adjustmentJson' => json_encode([
                'type'  => 'COEFFICIENT',
                'value' => '1.5',
            ]),
        ];
    }
}
