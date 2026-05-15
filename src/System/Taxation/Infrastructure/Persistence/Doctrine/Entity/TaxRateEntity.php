<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_taxation_rates_regime_validity', columns: ['regime_id', 'valid_from', 'valid_to'])]
class TaxRateEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 64)]
    private string $id;

    #[ORM\Column(name: 'regime_id', type: 'string', length: 16)]
    private string $regimeId;

    #[ORM\Column(name: 'value_bp', type: 'integer')]
    private int $valueBp;

    #[ORM\Column(name: 'valid_from', type: 'date_immutable')]
    private \DateTimeImmutable $validFrom;

    #[ORM\Column(name: 'valid_to', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validTo;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'condition_json', type: 'json')]
    private array $conditionJson;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
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

    public function getValueBp(): int
    {
        return $this->valueBp;
    }

    public function setValueBp(int $valueBp): void
    {
        $this->valueBp = $valueBp;
    }

    public function getValidFrom(): \DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(\DateTimeImmutable $validFrom): void
    {
        $this->validFrom = $validFrom;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function setValidTo(?\DateTimeImmutable $validTo): void
    {
        $this->validTo = $validTo;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
