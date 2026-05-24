<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article\ValueObject;

final class ArticleName
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ('' === $value) {
            throw new \DomainException('Article name cannot be empty.');
        }

        if (mb_strlen($value) > 150) {
            throw new \DomainException('Article name cannot exceed 150 characters.');
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
