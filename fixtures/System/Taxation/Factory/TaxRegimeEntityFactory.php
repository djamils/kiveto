<?php

declare(strict_types=1);

namespace App\Fixtures\System\Taxation\Factory;

use App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity\TaxRegimeEntity;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<TaxRegimeEntity>
 */
final class TaxRegimeEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return TaxRegimeEntity::class;
    }

    protected function defaults(): array|callable
    {
        $now = new \DateTimeImmutable();

        return [
            'id'          => self::faker()->unique()->regexify('[A-Z]{2}'),
            'name'        => self::faker()->words(3, true),
            'countryCode' => self::faker()->countryCode(),
            'active'      => true,
            'loadedAt'    => $now,
            'updatedAt'   => $now,
        ];
    }
}
