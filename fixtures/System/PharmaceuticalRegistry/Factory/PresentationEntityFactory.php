<?php

declare(strict_types=1);

namespace App\Fixtures\System\PharmaceuticalRegistry\Factory;

use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\PresentationEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<PresentationEntity>
 */
final class PresentationEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return PresentationEntity::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id'                            => Uuid::v7(),
            'description'                   => self::faker()->words(4, true),
            'unitCount'                     => null,
            'packaging'                     => null,
            'gtin'                          => self::faker()->numerify('##############'),
            'euPackIdentifier'              => null,
            'prescriptionRequired'          => false,
            'prescriptionClass'             => 'NONE',
            'prescriptionRetentionYears'    => null,
            'prescriptionJurisJurisdiction' => null,
            'prescriptionJurisCode'         => null,
        ];
    }
}
