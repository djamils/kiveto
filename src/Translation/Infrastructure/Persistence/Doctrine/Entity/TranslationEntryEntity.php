<?php

declare(strict_types=1);

namespace App\Translation\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'translation_entry',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_translation_entry_scope_locale_domain_key',
            columns: ['app_scope', 'locale', 'domain', 'translation_key'],
        ),
    ],
    indexes: [
        new ORM\Index(columns: ['app_scope', 'locale', 'domain'], name: 'idx_translation_scope_locale_domain'),
        new ORM\Index(columns: ['translation_key'], name: 'idx_translation_key'),
        new ORM\Index(columns: ['domain'], name: 'idx_translation_domain'),
        new ORM\Index(columns: ['locale'], name: 'idx_translation_locale'),
        new ORM\Index(columns: ['updated_at'], name: 'idx_translation_updated_at'),
    ],
)]
class TranslationEntryEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'binary', length: 16)]
    private string $id;

    #[ORM\Column(type: 'string', length: 32)]
    private string $appScope;

    #[ORM\Column(type: 'string', length: 16)]
    private string $locale;

    #[ORM\Column(type: 'string', length: 64)]
    private string $domain;

    #[ORM\Column(type: 'string', length: 190)]
    private string $translationKey;

    #[ORM\Column(type: 'text')]
    private string $translationValue;

    #[ORM\Column(type: 'datetime_immutable', precision: 6)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'binary', length: 16, nullable: true)]
    private ?string $updatedBy = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getAppScope(): string
    {
        return $this->appScope;
    }

    public function setAppScope(string $appScope): void
    {
        $this->appScope = $appScope;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    public function setTranslationKey(string $translationKey): void
    {
        $this->translationKey = $translationKey;
    }

    public function getTranslationValue(): string
    {
        return $this->translationValue;
    }

    public function setTranslationValue(string $translationValue): void
    {
        $this->translationValue = $translationValue;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?string $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }
}
