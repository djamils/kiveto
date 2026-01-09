<?php

declare(strict_types=1);

namespace App\Fixtures\Translation\Factory;

use App\Translation\Infrastructure\Persistence\Doctrine\Entity\TranslationEntryEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<TranslationEntryEntity>
 */
final class TranslationEntryEntityFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return TranslationEntryEntity::class;
    }

    protected function defaults(): array|callable
    {
        $now = \DateTimeImmutable::createFromMutable(self::faker()->dateTime());

        return [
            'id'               => Uuid::v7(),
            'appScope'         => 'shared',
            'locale'           => 'fr_FR',
            'domain'           => 'messages',
            'translationKey'   => self::faker()->unique()->slug(3),
            'translationValue' => self::faker()->sentence(),
            'description'      => self::faker()->boolean(30) ? self::faker()->sentence(6) : null,
            'createdAt'        => $now,
            'createdBy'        => null,
            'updatedAt'        => $now,
            'updatedBy'        => null,
        ];
    }
}
