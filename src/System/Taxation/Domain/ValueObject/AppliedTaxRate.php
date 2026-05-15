<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

final class AppliedTaxRate
{
    private function __construct(
        private readonly TaxRateId $rateId,
        private readonly TaxRegimeId $regimeId,
        private readonly TaxRateValue $value,
        private readonly \DateTimeImmutable $effectiveFrom,
        private readonly string $source,
    ) {
    }

    public static function of(
        TaxRateId $rateId,
        TaxRegimeId $regimeId,
        TaxRateValue $value,
        \DateTimeImmutable $effectiveFrom,
        string $source,
    ): self {
        return new self($rateId, $regimeId, $value, $effectiveFrom, $source);
    }

    public function rateId(): TaxRateId
    {
        return $this->rateId;
    }

    public function regimeId(): TaxRegimeId
    {
        return $this->regimeId;
    }

    public function value(): TaxRateValue
    {
        return $this->value;
    }

    public function effectiveFrom(): \DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function source(): string
    {
        return $this->source;
    }
}
