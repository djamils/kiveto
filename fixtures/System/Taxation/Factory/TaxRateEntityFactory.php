<?php

declare(strict_types=1);

namespace App\Fixtures\System\Taxation\Factory;

use App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity\TaxRateEntity;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<TaxRateEntity>
 */
final class TaxRateEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return TaxRateEntity::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id'            => self::faker()->unique()->regexify('[a-z]{2}\.[a-z_]+'),
            'regimeId'      => 'FR',
            'valueBp'       => 2000,
            'validFrom'     => new \DateTimeImmutable('2014-01-01'),
            'validTo'       => null,
            'conditionJson' => [
                'categories'        => [],
                'regions'           => [],
                'customer_statuses' => [],
                'animal_usages'     => [],
                'clinic_liability'  => null,
            ],
            'createdAt' => new \DateTimeImmutable(),
        ];
    }
}
