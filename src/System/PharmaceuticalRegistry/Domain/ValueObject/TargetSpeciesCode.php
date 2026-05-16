<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

final class TargetSpeciesCode
{
    private string $value;

    private function __construct(string $value)
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{1,32}$/', $value)) {
            throw new \InvalidArgumentException(\sprintf('Invalid target species code: "%s".', $value));
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
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
