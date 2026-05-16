<?php

declare(strict_types=1);

namespace App\Fixtures\System\PharmaceuticalRegistry\Factory;

use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\ActiveSubstanceEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ActiveSubstanceEntity>
 */
final class ActiveSubstanceEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ActiveSubstanceEntity::class;
    }

    protected function defaults(): array|callable
    {
        return static function (): array {
            $words = self::faker()->words(2);
            \assert(\is_array($words));
            $label = implode(' ', $words);

            return [
                'id'              => Uuid::v7(),
                'label'           => $label,
                'labelNormalized' => mb_strtolower(mb_trim($label)),
                'innCode'         => null,
                'createdAt'       => new \DateTimeImmutable(),
                'updatedAt'       => new \DateTimeImmutable(),
            ];
        };
    }
}
