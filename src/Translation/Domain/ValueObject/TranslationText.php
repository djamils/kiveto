<?php

declare(strict_types=1);

namespace App\Translation\Domain\ValueObject;

final class TranslationText
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if ('' === $value) {
            throw new \InvalidArgumentException('Translation text cannot be empty.');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
