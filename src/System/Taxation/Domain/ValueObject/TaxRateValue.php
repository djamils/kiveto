<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

use App\System\Taxation\Domain\Exception\InvalidTaxRateValueException;

final class TaxRateValue
{
    private function __construct(private readonly int $basisPoints)
    {
    }

    public static function ofBasisPoints(int $bp): self
    {
        if ($bp < 0 || $bp > 10000) {
            throw new InvalidTaxRateValueException($bp);
        }

        return new self($bp);
    }

    public static function ofPercentString(string $percent): self
    {
        if (1 !== preg_match('/^\d+(\.\d{1,2})?$/', $percent)) {
            throw new InvalidTaxRateValueException(-1);
        }

        /** @var numeric-string $percent */
        $bp = (int) bcmul($percent, '100', 0);

        return self::ofBasisPoints($bp);
    }

    public function toPercentString(): string
    {
        return number_format($this->basisPoints / 100, 2, '.', '');
    }

    public function toBcmathFactor(): string
    {
        return bcdiv((string) $this->basisPoints, '10000', 4);
    }

    public function basisPoints(): int
    {
        return $this->basisPoints;
    }

    public function equals(self $other): bool
    {
        return $this->basisPoints === $other->basisPoints;
    }
}
