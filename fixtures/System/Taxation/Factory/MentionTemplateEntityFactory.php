<?php

declare(strict_types=1);

namespace App\Fixtures\System\Taxation\Factory;

use App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity\MentionTemplateEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<MentionTemplateEntity>
 */
final class MentionTemplateEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return MentionTemplateEntity::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id'            => Uuid::v4(),
            'regimeId'      => 'FR',
            'code'          => self::faker()->unique()->regexify('[A-Z_]{8,20}'),
            'conditionJson' => [
                'regions'           => [],
                'customer_statuses' => [],
                'animal_usages'     => [],
                'clinic_liability'  => null,
            ],
            'template'  => self::faker()->sentence(),
            'locale'    => 'fr_FR',
            'active'    => true,
            'createdAt' => new \DateTimeImmutable(),
        ];
    }
}
