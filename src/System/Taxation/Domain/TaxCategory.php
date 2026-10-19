<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Taxation\Domain\Event\TaxCategoryRegistered;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxCategoryNature;

final class TaxCategory extends AggregateRoot
{
    private TaxCategoryCode $code;
    private string $displayName;
    private ?string $description;
    private TaxCategoryNature $nature;
    private bool $active;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function create(
        TaxCategoryCode $code,
        string $displayName,
        ?string $description,
        TaxCategoryNature $nature,
        ClockInterface $clock,
    ): self {
        $now            = $clock->now();
        $c              = new self();
        $c->code        = $code;
        $c->displayName = $displayName;
        $c->description = $description;
        $c->nature      = $nature;
        $c->active      = true;
        $c->createdAt   = $now;
        $c->updatedAt   = $now;
        $c->recordDomainEvent(new TaxCategoryRegistered($code->toString(), $displayName));

        return $c;
    }

    public static function reconstitute(
        TaxCategoryCode $code,
        string $displayName,
        ?string $description,
        TaxCategoryNature $nature,
        bool $active,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $c              = new self();
        $c->code        = $code;
        $c->displayName = $displayName;
        $c->description = $description;
        $c->nature      = $nature;
        $c->active      = $active;
        $c->createdAt   = $createdAt;
        $c->updatedAt   = $updatedAt;

        return $c;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function update(string $displayName, ?string $description, TaxCategoryNature $nature, ClockInterface $clock): void
    {
        $this->displayName = $displayName;
        $this->description = $description;
        $this->nature      = $nature;
        $this->updatedAt   = $clock->now();
    }

    public function code(): TaxCategoryCode
    {
        return $this->code;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function nature(): TaxCategoryNature
    {
        return $this->nature;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
