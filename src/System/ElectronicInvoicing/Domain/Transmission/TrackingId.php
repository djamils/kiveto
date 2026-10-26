<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Transmission;

/**
 * The id the issuing context knows a document by; a transmission is unique on it.
 */
final class TrackingId
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = mb_trim($value);

        if ('' === $value || mb_strlen($value) > 64) {
            throw new \InvalidArgumentException(\sprintf('Invalid tracking id "%s".', $value));
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
