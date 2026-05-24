<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article\ValueObject;

final class MarketingAuthorizationRef
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ('' === $value) {
            throw new \DomainException('Marketing authorization ref cannot be empty.');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
