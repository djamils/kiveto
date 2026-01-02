<?php

declare(strict_types=1);

namespace App\Translation\Domain\Model\ValueObject;

final class TranslationText
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        // Do not trim to preserve intentional leading/trailing spaces.
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
