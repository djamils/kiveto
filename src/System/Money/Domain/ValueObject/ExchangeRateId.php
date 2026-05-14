<?php

declare(strict_types=1);

namespace App\System\Money\Domain\ValueObject;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;

/**
 * UUIDv7 identifier of an ExchangeRate aggregate.
 *
 * Generated via UuidGeneratorInterface to preserve testability.
 */
final class ExchangeRateId
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function generate(UuidGeneratorInterface $generator): self
    {
        return new self($generator->generate());
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
