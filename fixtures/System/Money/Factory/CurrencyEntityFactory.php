<?php

declare(strict_types=1);

namespace App\Fixtures\System\Money\Factory;

use App\System\Money\Infrastructure\Persistence\Doctrine\Entity\CurrencyEntity;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<CurrencyEntity>
 */
final class CurrencyEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return CurrencyEntity::class;
    }

    protected function defaults(): array|callable
    {
        $now = new \DateTimeImmutable();

        return [
            'code'        => self::faker()->unique()->regexify('[A-Z]{3}'),
            'symbol'      => self::faker()->currencyCode(),
            'decimals'    => 2,
            'displayName' => self::faker()->word(),
            'active'      => true,
            'createdAt'   => $now,
            'updatedAt'   => $now,
        ];
    }
}
