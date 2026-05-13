<?php

declare(strict_types=1);

namespace App\System\Money\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_money_xr_from_to_date_src', columns: ['currency_from', 'currency_to', 'effective_date', 'source'])]
#[ORM\Index(name: 'idx_money_xr_from_to_date', columns: ['currency_from', 'currency_to', 'effective_date'])]
class ExchangeRateEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'currency_from', type: 'string', length: 3)]
    private string $currencyFrom;

    #[ORM\Column(name: 'currency_to', type: 'string', length: 3)]
    private string $currencyTo;

    #[ORM\Column(type: 'decimal', precision: 20, scale: 10)]
    private string $rate;

    #[ORM\Column(name: 'effective_date', type: 'date_immutable')]
    private \DateTimeImmutable $effectiveDate;

    #[ORM\Column(type: 'string', length: 32)]
    private string $source;

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

    public function getCurrencyFrom(): string
    {
        return $this->currencyFrom;
    }

    public function setCurrencyFrom(string $currencyFrom): void
    {
        $this->currencyFrom = $currencyFrom;
    }

    public function getCurrencyTo(): string
    {
        return $this->currencyTo;
    }

    public function setCurrencyTo(string $currencyTo): void
    {
        $this->currencyTo = $currencyTo;
    }

    public function getRate(): string
    {
        return $this->rate;
    }

    public function setRate(string $rate): void
    {
        $this->rate = $rate;
    }

    public function getEffectiveDate(): \DateTimeImmutable
    {
        return $this->effectiveDate;
    }

    public function setEffectiveDate(\DateTimeImmutable $effectiveDate): void
    {
        $this->effectiveDate = $effectiveDate;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): void
    {
        $this->source = $source;
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
