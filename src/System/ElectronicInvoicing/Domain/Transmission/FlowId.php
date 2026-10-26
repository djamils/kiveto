<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Transmission;

/**
 * The id the platform gives a deposited flow.
 */
final class FlowId
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = mb_trim($value);

        if ('' === $value || mb_strlen($value) > 64) {
            throw new \InvalidArgumentException(\sprintf('Invalid flow id "%s".', $value));
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
