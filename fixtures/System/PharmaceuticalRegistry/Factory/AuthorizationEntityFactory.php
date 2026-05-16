<?php

declare(strict_types=1);

namespace App\Fixtures\System\PharmaceuticalRegistry\Factory;

use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\AuthorizationEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<AuthorizationEntity>
 */
final class AuthorizationEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return AuthorizationEntity::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id'                              => Uuid::v7(),
            'commercialName'                  => self::faker()->words(3, true),
            'holderLaboratory'                => self::faker()->company(),
            'status'                          => 'ACTIVE',
            'authorizationDate'               => new \DateTimeImmutable('2010-01-01'),
            'nature'                          => 'CHEMICAL',
            'pharmaceuticalForm'              => 'Solution for injection',
            'atcVetCode'                      => null,
            'permanentIdentifier'             => null,
            'contentHash'                     => str_repeat('0', 64),
            'lastImportSource'                => 'ANMV',
            'lastImportedAt'                  => new \DateTimeImmutable(),
            'createdAt'                       => new \DateTimeImmutable(),
            'updatedAt'                       => new \DateTimeImmutable(),
            'controlledSubstanceClass'        => null,
            'controlledSubstanceJurisdiction' => null,
            'controlledSubstanceCode'         => null,
            'controlledSubstanceRestrictions' => null,
        ];
    }
}
