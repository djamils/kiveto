<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article\ValueObject;

final class ArticleCode
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[A-Z0-9_-]{2,20}$/', $value)) {
            throw new \DomainException(\sprintf(
                'Invalid article code: "%s". Expected format: ^[A-Z0-9_-]{2,20}$',
                $value,
            ));
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
