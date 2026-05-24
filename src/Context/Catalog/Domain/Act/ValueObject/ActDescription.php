<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Act\ValueObject;

final class ActDescription
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (mb_strlen($value) > 1000) {
            throw new \DomainException('Act description cannot exceed 1000 characters.');
        }

        return new self($value);
    }

    public static function fromNullableString(?string $value): ?self
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        return self::fromString($value);
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
