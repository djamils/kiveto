<?php

declare(strict_types=1);

namespace App\Translation\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_translation_scope_locale_domain', columns: ['app_scope', 'locale', 'domain'])]
#[ORM\Index(name: 'idx_translation_key', columns: ['translation_key'])]
#[ORM\Index(name: 'idx_translation_domain', columns: ['domain'])]
#[ORM\Index(name: 'idx_translation_locale', columns: ['locale'])]
#[ORM\Index(name: 'idx_translation_updated_at', columns: ['updated_at'])]
#[ORM\UniqueConstraint(
    name: 'uniq_translation_entry_scope_locale_domain_key',
    columns: ['app_scope', 'locale', 'domain', 'translation_key'],
)]
class TranslationEntryEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

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

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime_immutable', precision: 6)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $createdBy = null;

    #[ORM\Column(type: 'datetime_immutable', precision: 6)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $updatedBy = null;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getCreatedBy(): ?Uuid
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Uuid $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getUpdatedBy(): ?Uuid
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?Uuid $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }
}
