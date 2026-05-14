<?php

declare(strict_types=1);

namespace App\System\Money\Domain;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\System\Money\Domain\Event\ExchangeRateImported;
use App\System\Money\Domain\ValueObject\ExchangeRateId;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;

/**
 * Historical exchange rate between two currencies at an effective date.
 *
 * The rate is stored as a bcmath decimal string (e.g. "0.9523") to guarantee
 * precision; it must be strictly positive. The source field identifies the
 * rate origin ("ECB", "MANUAL", "FIXED").
 *
 * A unique constraint enforces one row per (from, to, effective_date, source).
 */
final class ExchangeRate extends AggregateRoot
{
    private ExchangeRateId $id;
    private ExchangeRatePair $pair;
    private string $rate;
    private \DateTimeImmutable $effectiveDate;
    private string $source;
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function create(
        ExchangeRateId $id,
        ExchangeRatePair $pair,
        string $rate,
        \DateTimeImmutable $effectiveDate,
        string $source,
        \DateTimeImmutable $createdAt,
    ): self {
        if (!is_numeric($rate) || bccomp($rate, '0', 10) <= 0) {
            throw new \InvalidArgumentException(\sprintf('Exchange rate must be positive, got "%s".', $rate));
        }

        $exchangeRate                = new self();
        $exchangeRate->id            = $id;
        $exchangeRate->pair          = $pair;
        $exchangeRate->rate          = $rate;
        $exchangeRate->effectiveDate = $effectiveDate;
        $exchangeRate->source        = $source;
        $exchangeRate->createdAt     = $createdAt;

        $exchangeRate->recordDomainEvent(new ExchangeRateImported(
            $id->toString(),
            $pair->from()->toString(),
            $pair->to()->toString(),
            $rate,
            $effectiveDate->format('Y-m-d'),
            $source,
        ));

        return $exchangeRate;
    }

    public static function reconstitute(
        ExchangeRateId $id,
        ExchangeRatePair $pair,
        string $rate,
        \DateTimeImmutable $effectiveDate,
        string $source,
        \DateTimeImmutable $createdAt,
    ): self {
        $exchangeRate                = new self();
        $exchangeRate->id            = $id;
        $exchangeRate->pair          = $pair;
        $exchangeRate->rate          = $rate;
        $exchangeRate->effectiveDate = $effectiveDate;
        $exchangeRate->source        = $source;
        $exchangeRate->createdAt     = $createdAt;

        return $exchangeRate;
    }

    public function id(): ExchangeRateId
    {
        return $this->id;
    }

    public function pair(): ExchangeRatePair
    {
        return $this->pair;
    }

    public function rate(): string
    {
        return $this->rate;
    }

    public function effectiveDate(): \DateTimeImmutable
    {
        return $this->effectiveDate;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
