<?php

declare(strict_types=1);

namespace App\System\Money\Domain;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Event\CurrencyActivated;
use App\System\Money\Domain\Event\CurrencyDeactivated;
use App\System\Money\Domain\Event\CurrencyRegistered;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\CurrencySymbol;

final class Currency extends AggregateRoot
{
    private CurrencyCode $code;
    private CurrencySymbol $symbol;
    private CurrencyDecimals $decimals;
    private string $displayName;
    private bool $active;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function create(
        CurrencyCode $code,
        CurrencySymbol $symbol,
        CurrencyDecimals $decimals,
        string $displayName,
        ClockInterface $clock,
    ): self {
        $now = $clock->now();

        $currency              = new self();
        $currency->code        = $code;
        $currency->symbol      = $symbol;
        $currency->decimals    = $decimals;
        $currency->displayName = $displayName;
        $currency->active      = true;
        $currency->createdAt   = $now;
        $currency->updatedAt   = $now;

        $currency->recordDomainEvent(new CurrencyRegistered(
            $code->toString(),
            $symbol->toString(),
            $decimals->value(),
            $displayName,
        ));

        return $currency;
    }

    public static function reconstitute(
        CurrencyCode $code,
        CurrencySymbol $symbol,
        CurrencyDecimals $decimals,
        string $displayName,
        bool $active,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $currency              = new self();
        $currency->code        = $code;
        $currency->symbol      = $symbol;
        $currency->decimals    = $decimals;
        $currency->displayName = $displayName;
        $currency->active      = $active;
        $currency->createdAt   = $createdAt;
        $currency->updatedAt   = $updatedAt;

        return $currency;
    }

    public function activate(): void
    {
        if ($this->active) {
            return;
        }

        $this->active = true;
        $this->recordDomainEvent(new CurrencyActivated($this->code->toString()));
    }

    public function deactivate(): void
    {
        if (!$this->active) {
            return;
        }

        $this->active = false;
        $this->recordDomainEvent(new CurrencyDeactivated($this->code->toString()));
    }

    public function updateDisplay(CurrencySymbol $symbol, CurrencyDecimals $decimals, string $displayName): void
    {
        $this->symbol      = $symbol;
        $this->decimals    = $decimals;
        $this->displayName = $displayName;
    }

    public function code(): CurrencyCode
    {
        return $this->code;
    }

    public function symbol(): CurrencySymbol
    {
        return $this->symbol;
    }

    public function decimals(): CurrencyDecimals
    {
        return $this->decimals;
    }

    public function displayName(): string
    {
        return $this->displayName;
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
