<?php

declare(strict_types=1);

namespace App\System\Money\Domain\ValueObject;

final readonly class HistoricalRate
{
    private function __construct(
        public readonly ExchangeRatePair $pair,
        public readonly string $rate,
        public readonly \DateTimeImmutable $effectiveDate,
        public readonly string $source,
    ) {
    }

    public static function of(
        ExchangeRatePair $pair,
        string $rate,
        \DateTimeImmutable $effectiveDate,
        string $source,
    ): self {
        return new self($pair, $rate, $effectiveDate, $source);
    }
}
