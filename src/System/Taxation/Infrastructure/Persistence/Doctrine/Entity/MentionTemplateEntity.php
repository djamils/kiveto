<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_taxation_mentions_regime', columns: ['regime_id'])]
#[ORM\UniqueConstraint(name: 'uniq_taxation_mention_regime_code_locale', columns: ['regime_id', 'code', 'locale'])]
class MentionTemplateEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'regime_id', type: 'string', length: 16)]
    private string $regimeId;

    #[ORM\Column(type: 'string', length: 64)]
    private string $code;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'condition_json', type: 'json')]
    private array $conditionJson;

    #[ORM\Column(type: 'text')]
    private string $template;

    #[ORM\Column(type: 'string', length: 8)]
    private string $locale;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getRegimeId(): string
    {
        return $this->regimeId;
    }

    public function setRegimeId(string $regimeId): void
    {
        $this->regimeId = $regimeId;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    /** @return array<string, mixed> */
    public function getConditionJson(): array
    {
        return $this->conditionJson;
    }

    /** @param array<string, mixed> $conditionJson */
    public function setConditionJson(array $conditionJson): void
    {
        $this->conditionJson = $conditionJson;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setTemplate(string $template): void
    {
        $this->template = $template;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
