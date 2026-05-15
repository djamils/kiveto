<?php

declare(strict_types=1);

namespace App\Fixtures\System\Taxation\Factory;

use App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity\TaxCategoryEntity;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<TaxCategoryEntity>
 */
final class TaxCategoryEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return TaxCategoryEntity::class;
    }

    protected function defaults(): array|callable
    {
        $now = new \DateTimeImmutable();

        return [
            'code'        => 'veterinary.' . self::faker()->unique()->word() . '.' . self::faker()->word(),
            'displayName' => self::faker()->sentence(3),
            'description' => null,
            'active'      => true,
            'createdAt'   => $now,
            'updatedAt'   => $now,
        ];
    }
}
