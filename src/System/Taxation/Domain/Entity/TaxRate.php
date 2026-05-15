<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Entity;

use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxRateCondition;
use App\System\Taxation\Domain\ValueObject\TaxRateId;
use App\System\Taxation\Domain\ValueObject\TaxRateValue;
use App\System\Taxation\Domain\ValueObject\ValidityPeriod;

final class TaxRate
{
    private function __construct(
        private readonly TaxRateId $id,
        private readonly TaxRateValue $value,
        private readonly ValidityPeriod $validityPeriod,
        private readonly TaxRateCondition $appliesTo,
    ) {
    }

    public static function of(
        TaxRateId $id,
        TaxRateValue $value,
        ValidityPeriod $validityPeriod,
        TaxRateCondition $appliesTo,
    ): self {
        return new self($id, $value, $validityPeriod, $appliesTo);
    }

    public function id(): TaxRateId
    {
        return $this->id;
    }

    public function value(): TaxRateValue
    {
        return $this->value;
    }

    public function validityPeriod(): ValidityPeriod
    {
        return $this->validityPeriod;
    }

    public function appliesTo(): TaxRateCondition
    {
        return $this->appliesTo;
    }

    public function isValidOn(\DateTimeImmutable $date): bool
    {
        return $this->validityPeriod->contains($date);
    }

    public function matchesContext(TaxCategoryCode $cat, FiscalContext $ctx): bool
    {
        return $this->appliesTo->matches($cat, $ctx);
    }
}
