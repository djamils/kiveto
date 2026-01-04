<?php

declare(strict_types=1);

namespace App\Fixtures\Translation\Factory;

use App\Translation\Infrastructure\Persistence\Doctrine\Entity\TranslationEntryEntity;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<TranslationEntryEntity>
 */
final class TranslationEntryEntityFactory extends PersistentObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
    }

    #[\Override]
    public static function class(): string
    {
        return TranslationEntryEntity::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'appScope'         => self::faker()->text(32),
            'createdAt'        => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'domain'           => self::faker()->text(64),
            'locale'           => self::faker()->text(16),
            'translationKey'   => self::faker()->text(190),
            'translationValue' => self::faker()->text(),
            'updatedAt'        => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this;
        // ->afterInstantiate(function(TranslationEntryEntity $translationEntryEntity): void {})
    }
}
