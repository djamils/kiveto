<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table]
class TaxRegimeEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 16)]
    private string $id;

    #[ORM\Column(type: 'string', length: 128)]
    private string $name;

    #[ORM\Column(name: 'country_code', type: 'string', length: 2)]
    private string $countryCode;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $active;

    #[ORM\Column(name: 'loaded_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $loadedAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): void
    {
        $this->countryCode = $countryCode;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getLoadedAt(): \DateTimeImmutable
    {
        return $this->loadedAt;
    }

    public function setLoadedAt(\DateTimeImmutable $loadedAt): void
    {
        $this->loadedAt = $loadedAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
