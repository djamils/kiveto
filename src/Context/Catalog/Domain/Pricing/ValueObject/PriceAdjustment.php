<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\ValueObject;

use App\Context\Catalog\Domain\Exception\InvalidPriceAdjustmentException;
use App\Shared\Money\Domain\ValueObject\Money;

final class PriceAdjustment
{
    private function __construct(
        private AdjustmentType $type,
        private string $decimalValue,
        private ?Money $money,
    ) {
    }

    public static function coefficient(string $decimalValue): self
    {
        if (!is_numeric($decimalValue)) {
            throw new InvalidPriceAdjustmentException(\sprintf('Coefficient must be numeric, got "%s".', $decimalValue));
        }

        return new self(AdjustmentType::COEFFICIENT, $decimalValue, null);
    }

    public static function fixedAmountDiscount(Money $money): self
    {
        return new self(AdjustmentType::FIXED_AMOUNT_DISCOUNT, (string) $money->minorUnits(), $money);
    }

    public function type(): AdjustmentType
    {
        return $this->type;
    }

    public function decimalValue(): string
    {
        return $this->decimalValue;
    }

    public function money(): ?Money
    {
        return $this->money;
    }

    /** @return array{type: string, value: string} */
    public function toArray(): array
    {
        return [
            'type'  => $this->type->value,
            'value' => $this->decimalValue,
        ];
    }

    /**
     * @param array{type: string, value: string} $data
     */
    public static function fromArray(array $data): self
    {
        $type = AdjustmentType::from($data['type']);

        return new self($type, $data['value'], null);
    }
}
