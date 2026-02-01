<?php

declare(strict_types=1);

namespace App\ClinicalCare\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class ConsultationId
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException('Invalid ConsultationId UUID format');
        }

        return new self($value);
    }

    public static function generate(): self
    {
        return new self(Uuid::v7()->toString());
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
