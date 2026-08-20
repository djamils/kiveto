<?php

declare(strict_types=1);

namespace App\Fixtures\System\PharmaceuticalRegistry\Factory;

use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\CompositionEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<CompositionEntity>
 */
final class CompositionEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return CompositionEntity::class;
    }

    protected function defaults(): array
    {
        return [
            'id'                => Uuid::v7(),
            'quantityValue'     => null,
            'quantityUnitLabel' => null,
            'quantityUnitCode'  => null,
            'isExcipient'       => false,
        ];
    }
}
