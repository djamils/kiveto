<?php

declare(strict_types=1);

namespace App\Context\Patient\Domain\ValueObject;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class PatientId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
