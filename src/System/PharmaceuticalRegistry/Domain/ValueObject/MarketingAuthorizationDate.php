<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

final class MarketingAuthorizationDate
{
    private \DateTimeImmutable $value;

    private function __construct(\DateTimeImmutable $value)
    {
        $this->value = $value;
    }

    public static function fromDateTime(\DateTimeImmutable $value): self
    {
        return new self($value);
    }

    public function toDateTime(): \DateTimeImmutable
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value->format('Y-m-d');
    }

    public function equals(self $other): bool
    {
        return $this->value->format('Y-m-d') === $other->value->format('Y-m-d');
    }
}
