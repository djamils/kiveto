<?php

declare(strict_types=1);

namespace App\System\Money\Domain\ValueObject;

/**
 * Point-in-time exchange rate between two currencies at a given effective date.
 *
 * Returned by rate providers (ECB, fixed, etc.) and converted to an
 * ExchangeRate aggregate on import. The rate is stored as a bcmath decimal
 * string (e.g. "0.9523") to prevent any floating-point precision loss.
 */
final class HistoricalRate
{
    private ExchangeRatePair $pair;
    private string $rate;
    private \DateTimeImmutable $effectiveDate;
    private string $source;

    private function __construct(
        ExchangeRatePair $pair,
        string $rate,
        \DateTimeImmutable $effectiveDate,
        string $source,
    ) {
        $this->pair          = $pair;
        $this->rate          = $rate;
        $this->effectiveDate = $effectiveDate;
        $this->source        = $source;
    }

    public static function of(
        ExchangeRatePair $pair,
        string $rate,
        \DateTimeImmutable $effectiveDate,
        string $source,
    ): self {
        return new self($pair, $rate, $effectiveDate, $source);
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
}
