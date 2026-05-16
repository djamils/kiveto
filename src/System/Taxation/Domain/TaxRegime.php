<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\System\Taxation\Domain\Entity\TaxRate;
use App\System\Taxation\Domain\Event\TaxRateAdded;
use App\System\Taxation\Domain\Event\TaxRegimeActivated;
use App\System\Taxation\Domain\Event\TaxRegimeDeactivated;
use App\System\Taxation\Domain\Event\TaxRegimeLoaded;
use App\System\Taxation\Domain\Exception\EmptyTaxRegimeCannotBeActivatedException;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;

final class TaxRegime extends AggregateRoot
{
    private TaxRegimeId $id;
    private string $name;
    private CountryCode $country;
    /** @var list<TaxRate> */
    private array $rates = [];
    private bool $active;
    private \DateTimeImmutable $loadedAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function create(
        TaxRegimeId $id,
        string $name,
        CountryCode $country,
        ClockInterface $clock,
        bool $active = false,
    ): self {
        $now          = $clock->now();
        $r            = new self();
        $r->id        = $id;
        $r->name      = $name;
        $r->country   = $country;
        $r->rates     = [];
        $r->active    = $active;
        $r->loadedAt  = $now;
        $r->updatedAt = $now;
        $r->recordDomainEvent(new TaxRegimeLoaded($id->toString(), $name, $country->toString()));

        return $r;
    }

    /**
     * @param list<TaxRate> $rates
     */
    public static function reconstitute(
        TaxRegimeId $id,
        string $name,
        CountryCode $country,
        array $rates,
        bool $active,
        \DateTimeImmutable $loadedAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $r            = new self();
        $r->id        = $id;
        $r->name      = $name;
        $r->country   = $country;
        $r->rates     = $rates;
        $r->active    = $active;
        $r->loadedAt  = $loadedAt;
        $r->updatedAt = $updatedAt;

        return $r;
    }

    public function addRate(TaxRate $rate, ClockInterface $clock): void
    {
        $this->rates[]   = $rate;
        $this->updatedAt = $clock->now();
        $this->recordDomainEvent(
            new TaxRateAdded($this->id->toString(), $rate->id()->toString(), $rate->value()->basisPoints()),
        );
    }

    /**
     * @param list<TaxRate> $rates
     */
    public function replaceAllRates(array $rates, ClockInterface $clock): void
    {
        $this->rates     = $rates;
        $this->updatedAt = $clock->now();
    }

    public function activate(ClockInterface $clock): void
    {
        if ([] === $this->rates) {
            throw new EmptyTaxRegimeCannotBeActivatedException($this->id->toString());
        }

        if ($this->active) {
            return;
        }

        $this->active    = true;
        $this->updatedAt = $clock->now();
        $this->recordDomainEvent(new TaxRegimeActivated($this->id->toString()));
    }

    public function deactivate(ClockInterface $clock): void
    {
        if (!$this->active) {
            return;
        }

        $this->active    = false;
        $this->updatedAt = $clock->now();
        $this->recordDomainEvent(new TaxRegimeDeactivated($this->id->toString()));
    }

    /** @return list<TaxRate> */
    public function findCandidateRates(TaxCategoryCode $category, FiscalContext $context): array
    {
        return array_values(array_filter(
            $this->rates,
            static fn (TaxRate $rate): bool => $rate->isValidOn($context->saleDate())
                && $rate->matchesContext($category, $context),
        ));
    }

    public function id(): TaxRegimeId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function country(): CountryCode
    {
        return $this->country;
    }

    /** @return list<TaxRate> */
    public function rates(): array
    {
        return $this->rates;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function loadedAt(): \DateTimeImmutable
    {
        return $this->loadedAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
