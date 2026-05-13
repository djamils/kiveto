<?php

declare(strict_types=1);

namespace App\System\Money\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CurrencyCode;

final class ExchangeRatePair
{
    private CurrencyCode $from;
    private CurrencyCode $to;

    private function __construct(CurrencyCode $from, CurrencyCode $to)
    {
        $this->from = $from;
        $this->to   = $to;
    }

    public static function of(CurrencyCode $from, CurrencyCode $to): self
    {
        if ($from->equals($to)) {
            throw new \InvalidArgumentException(\sprintf(
                'Exchange rate pair cannot have the same currency on both sides: "%s".',
                $from->toString(),
            ));
        }

        return new self($from, $to);
    }

    public function from(): CurrencyCode
    {
        return $this->from;
    }

    public function to(): CurrencyCode
    {
        return $this->to;
    }

    public function inverse(): self
    {
        return new self($this->to, $this->from);
    }

    public function toString(): string
    {
        return $this->from->toString() . '/' . $this->to->toString();
    }

    public function equals(self $other): bool
    {
        return $this->from->equals($other->from) && $this->to->equals($other->to);
    }
}
