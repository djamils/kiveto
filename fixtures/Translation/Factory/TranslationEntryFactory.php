<?php

declare(strict_types=1);

namespace App\Fixtures\Translation\Factory;

use App\Translation\Infrastructure\Persistence\Doctrine\Entity\TranslationEntryEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<TranslationEntryEntity>
 */
final class TranslationEntryFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return TranslationEntryEntity::class;
    }

    public function forScope(string $scope): static
    {
        return $this->with(['appScope' => $scope]);
    }

    public function forLocale(string $locale): static
    {
        return $this->with(['locale' => $locale]);
    }

    public function forDomain(string $domain): static
    {
        return $this->with(['domain' => $domain]);
    }

    public function withKey(string $key): static
    {
        return $this->with(['translationKey' => $key]);
    }

    public function withValue(string $value): static
    {
        return $this->with(['translationValue' => $value]);
    }

    public function withDescription(?string $description): static
    {
        return $this->with(['description' => $description]);
    }

    protected function defaults(): array|callable
    {
        $now = new \DateTimeImmutable();

        return [
            'id'               => Uuid::v7()->toBinary(),
            'appScope'         => self::faker()->randomElement(['clinic', 'portal', 'backoffice', 'shared']),
            'locale'           => self::faker()->randomElement(['fr', 'en', 'de', 'es']),
            'domain'           => self::faker()->randomElement(['messages', 'validators', 'forms', 'emails']),
            'translationKey'   => self::faker()->unique()->word() . '.' . self::faker()->word(),
            'translationValue' => self::faker()->sentence(),
            'description'      => self::faker()->optional()->sentence(),
            'createdAt'        => $now,
            'createdBy'        => null,
            'updatedAt'        => $now,
            'updatedBy'        => null,
        ];
    }
}
