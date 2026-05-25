<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\ValueObject;

use App\Context\Inventory\Domain\StockItem\Exception\NegativeCoefficientException;
use App\Context\Inventory\Domain\StockItem\Exception\NegativeQuantityException;
use App\Context\Inventory\Domain\StockItem\Exception\UnitMismatchException;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;

final class Quantity
{
    private const string AMOUNT_REGEX = '/^\d+(\.\d{1,6})?$/';
    private const int    SCALE        = 6;

    /** @phpstan-var numeric-string */
    private readonly string $amount;

    private readonly UnitOfMeasure $unit;

    /** @phpstan-param numeric-string $amount */
    private function __construct(string $amount, UnitOfMeasure $unit)
    {
        $this->amount = $amount;
        $this->unit   = $unit;
    }

    public static function of(string $amount, UnitOfMeasure $unit): self
    {
        if (1 !== preg_match(self::AMOUNT_REGEX, $amount)) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid quantity amount: "%s". Must be a non-negative decimal with up to 6 decimal places.',
                $amount
            ));
        }

        \assert(is_numeric($amount));

        return new self($amount, $unit);
    }

    public static function zero(UnitOfMeasure $unit): self
    {
        /** @var numeric-string $zero */
        $zero = '0.000000';

        return new self($zero, $unit);
    }

    public function add(self $other): self
    {
        $this->assertSameUnit($other);

        /** @var numeric-string $result */
        $result = bcadd($this->amount, $other->amount, self::SCALE);

        return new self($result, $this->unit);
    }

    public function subtract(self $other): self
    {
        $this->assertSameUnit($other);

        /** @var numeric-string $result */
        $result = bcsub($this->amount, $other->amount, self::SCALE);

        if (-1 === bccomp($result, '0', self::SCALE)) {
            throw new NegativeQuantityException();
        }

        return new self($result, $this->unit);
    }

    public function multiplyByCoefficient(string $coefficient): self
    {
        if (1 !== preg_match(self::AMOUNT_REGEX, $coefficient)) {
            throw new NegativeCoefficientException($coefficient);
        }

        /** @var numeric-string $coefficient */
        if (-1 === bccomp($coefficient, '0', self::SCALE) || 0 === bccomp($coefficient, '0', self::SCALE)) {
            throw new NegativeCoefficientException($coefficient);
        }

        /** @var numeric-string $result */
        $result = bcmul($this->amount, $coefficient, self::SCALE);

        if (-1 === bccomp($result, '0', self::SCALE)) {
            throw new NegativeQuantityException();
        }

        return new self($result, $this->unit);
    }

    public function isZero(): bool
    {
        return 0 === bccomp($this->amount, '0', self::SCALE);
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameUnit($other);

        return 1 === bccomp($this->amount, $other->amount, self::SCALE);
    }

    public function isGreaterOrEqual(self $other): bool
    {
        $this->assertSameUnit($other);

        return bccomp($this->amount, $other->amount, self::SCALE) >= 0;
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameUnit($other);

        return -1 === bccomp($this->amount, $other->amount, self::SCALE);
    }

    public function equals(self $other): bool
    {
        return $this->unit->equals($other->unit)
            && 0 === bccomp($this->amount, $other->amount, self::SCALE);
    }

    /** @phpstan-return numeric-string */
    public function amount(): string
    {
        return $this->amount;
    }

    public function unit(): UnitOfMeasure
    {
        return $this->unit;
    }

    public function toString(): string
    {
        return \sprintf('%s %s', $this->amount, $this->unit->toString());
    }

    private function assertSameUnit(self $other): void
    {
        if (!$this->unit->equals($other->unit)) {
            throw new UnitMismatchException($this->unit->toString(), $other->unit->toString());
        }
    }
}
